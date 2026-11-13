<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Models\Server;
use App\Models\User;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Models\GameCommand;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\StartTrigger;
use Simplify\Core\Push\Push as AppPush;
use Simplify\Core\Support\SafeCache;
use Throwable;

/**
 * "Send: when a server starts": every minute the scheduler looks whether the servers of these announcements came
 * online since the last look, so a start from the console or after a crash counts too. Each start sends the
 * announcement in that server's game chat, in the bell and to devices, as the announcement asks.
 */
final class StartAnnouncements
{
    /** Looks at every live start announcement. Returns the starts it sent an announcement for. */
    public static function runDue(): int
    {
        $sent = 0;
        foreach (Announcement::query()->live()->where('send_on', StartTrigger::START)->get() as $announcement) {
            // One look per announcement and minute, even when two schedulers overlap.
            if (! SafeCache::add('simplify-announcements.start.'.$announcement->id.'.'.intdiv(time(), 60), true, 120)) {
                continue;
            }
            try {
                $sent += self::look($announcement);
            } catch (Throwable $error) {
                report($error);
                Diagnose::trace('start #'.$announcement->id.' failed: '.Diagnose::error($error));
            }
        }

        return $sent;
    }

    private static function look(Announcement $announcement): int
    {
        $servers = Server::query()->with('egg')->whereKey(StartTrigger::servers($announcement->start_servers))->get();
        $running = [];
        foreach ($servers as $server) {
            $running[(int) $server->id] = TaskDelivery::running($server);
        }
        $look = StartTrigger::look(is_array($announcement->start_seen) ? $announcement->start_seen : [], $running);
        // Remembered first, so an error halfway never sends the same start twice.
        $announcement->forceFill(['start_seen' => $look['seen']])->saveQuietly();
        if ($look['started'] !== [] || $look['seen'] !== $announcement->getOriginal('start_seen')) {
            Diagnose::trace('start #'.$announcement->id.' running '.json_encode($running).' started '.json_encode($look['started']));
        }
        foreach ($look['started'] as $id) {
            Diagnose::trace('start #'.$announcement->id.' sending for server '.$id);
            self::send($announcement, $servers->firstWhere('id', $id));
        }
        if ($look['started'] !== [] && $announcement->start_once) {
            $announcement->forceFill(['ends_at' => now()])->save();
        }

        return count($look['started']);
    }

    private static function send(Announcement $announcement, Server $server): void
    {
        if ($announcement->in_game) {
            $own = GameCommand::query()->where('egg_id', $server->egg_id)->value('command');
            $command = GameChat::commandFor((string) $server->egg->name, is_string($own) ? $own : null);
            if ($command !== null) {
                try {
                    $server->send(GameChat::render($command, GameChat::message(__('simplify::announcements.in_game.prefix'), $announcement->title, $announcement->body)));
                } catch (Throwable $error) {
                    // Wings did not answer: the bell and devices still get it.
                    Diagnose::trace('start #'.$announcement->id.' game chat on '.$server->id.' failed: '.Diagnose::error($error));
                }
            }
        }
        if (! $announcement->in_bell && ! $announcement->push) {
            return;
        }
        $title = StartTrigger::title($server->name, $announcement->title);
        $users = User::query()->get()->filter(fn (User $user) => $announcement->audience !== 'admins' || $user->isAdmin())->values();
        if ($announcement->in_bell) {
            // The item of the previous start goes, so the bell holds the latest one.
            Bell::forget($announcement->id, kind: Bell::EVERYONE);
            foreach ($users as $user) {
                Bell::send($announcement, $user, Bell::EVERYONE, $title);
            }
        }
        if ($announcement->push && Push::available()) {
            $reached = AppPush::send($users, 'messages', [
                'title' => $title,
                'body' => (string) $announcement->body,
                'url' => $announcement->button_url ?: url('/server/'.$server->uuid_short),
                'tag' => 'simplify-announcement-'.$announcement->id.'-'.$server->id.'-'.time(),
            ]);
            $announcement->increment('pushed_to', $reached);
        }
    }
}
