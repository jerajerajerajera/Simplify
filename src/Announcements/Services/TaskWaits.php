<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Simplify\Core\Announcements\Support\TaskAnnouncement;
use Throwable;

/**
 * "When the server is online" (AnnouncementTask): the announcement waits as a row, and the scheduler
 * (simplify:announcements, every minute) sends it once the server runs, or after 10 minutes tells the people that
 * it did not come online. No queue worker is needed, so it also works on the sync queue (K-B64).
 */
final class TaskWaits
{
    public const TABLE = 'simplify_core_announcement_waits';

    /** @param  array{message: string, send: string, game_chat: bool, notify: bool}  $payload */
    public static function add(Server $server, array $payload, ?string $schedule, int $startedAt): void
    {
        DB::table(self::TABLE)->insert([
            'server_id' => $server->id,
            'payload' => TaskAnnouncement::encode($payload),
            'schedule' => $schedule !== null ? mb_substr($schedule, 0, 191) : null,
            'started_at' => $startedAt,
        ]);
    }

    /** Sends what is due; returns how many announcements were finished. Never throws. */
    public static function runDue(): int
    {
        try {
            $rows = DB::table(self::TABLE)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

            return TaskAnnouncement::runWaits(
                $rows,
                time(),
                fn (int $id) => Server::query()->find($id),
                fn (Server $server) => TaskDelivery::running($server),
                fn (Server $server, array $payload, ?string $schedule, bool $cameOnline) => TaskDelivery::deliver($server, $payload, $schedule, $cameOnline),
                fn (int $id) => DB::table(self::TABLE)->where('id', $id)->delete(),
            );
        } catch (Throwable $error) {
            report($error);

            return 0;
        }
    }
}
