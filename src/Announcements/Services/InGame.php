<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Enums\ContainerStatus;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Models\GameCommand;
use Simplify\Core\Announcements\Support\GameChat;
use Throwable;

/**
 * "Also send in game": posts an announcement in the chat of running game servers through their console, with the
 * chat command of each game. Sent by the scheduler when it starts and, when asked, again every few minutes while it
 * shows.
 */
final class InGame
{
    /** At most this many servers per announcement and run, so a large panel cannot keep the scheduler busy. */
    public const SERVERS = 200;

    /** @var array{sent: int, offline: int, unsupported: int, failed: int} */
    private const NOTHING = ['sent' => 0, 'offline' => 0, 'unsupported' => 0, 'failed' => 0];

    /** Sends every due announcement. Returns the servers it was posted on. */
    public static function sendDue(): int
    {
        $posted = 0;
        foreach (Announcement::query()->live()->where('in_game', true)->get() as $announcement) {
            if (! GameChat::isDue($announcement->in_game_at?->getTimestamp(), $announcement->in_game_repeat, time())) {
                continue;
            }
            // One run per announcement and minute, even when two schedulers overlap.
            if (! Cache::add('simplify-announcements.in-game.'.$announcement->id.'.'.intdiv(time(), 60), true, 120)) {
                continue;
            }
            // Marked first, so an error halfway never posts it twice in a row.
            $announcement->forceFill(['in_game_at' => now()])->saveQuietly();
            try {
                $result = self::send($announcement);
            } catch (Throwable $error) {
                report($error);
                $result = [...self::NOTHING, 'failed' => 1];
            }
            $announcement->forceFill(['in_game_result' => $result])->saveQuietly();
            $posted += $result['sent'];
        }

        return $posted;
    }

    /** @return array{sent: int, offline: int, unsupported: int, failed: int} */
    public static function send(Announcement $announcement): array
    {
        $result = self::NOTHING;
        $message = GameChat::message(
            __('simplify::announcements.in_game.prefix'),
            $announcement->title,
            $announcement->body,
        );
        $own = GameCommand::query()->pluck('command', 'egg_id')->all();

        foreach (self::targets($announcement) as $server) {
            $command = GameChat::commandFor((string) $server->egg?->name, $own[$server->egg_id] ?? null);
            if ($command === null) {
                $result['unsupported']++;

                continue;
            }
            if ($server->isSuspended() || $server->retrieveStatus() !== ContainerStatus::Running) {
                $result['offline']++;

                continue;
            }
            try {
                $response = $server->send(GameChat::render($command, $message));
                $response->getStatusCode() < 300 ? $result['sent']++ : $result['failed']++;
            } catch (Throwable) {
                $result['failed']++;
            }
        }

        return $result;
    }

    /** @return iterable<Server> */
    private static function targets(Announcement $announcement): iterable
    {
        $picked = $announcement->in_game_servers ?? [];

        return Server::query()
            ->with(['egg', 'node'])
            ->when($picked !== [], fn ($q) => $q->whereKey($picked))
            ->orderBy('id')
            ->limit(self::SERVERS)
            ->get();
    }
}
