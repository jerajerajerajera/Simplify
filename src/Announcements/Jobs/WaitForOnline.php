<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Simplify\Core\Announcements\Services\TaskDelivery;
use Simplify\Core\Announcements\Support\TaskAnnouncement;
use Throwable;

/**
 * "When the server is online" (AnnouncementTask): looks at the server every 30 seconds, as a new job each time so the
 * queue worker is never held up, and sends the announcement once it runs. After 10 minutes the people hear that the
 * server did not come online.
 */
final class WaitForOnline implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $server,
        public string $payload,
        public ?string $schedule,
        public int $startedAt,
    ) {}

    public function handle(): void
    {
        try {
            $server = Server::query()->find($this->server);
            if (! $server instanceof Server) {
                return;
            }
            $payload = TaskAnnouncement::parse($this->payload);
            $step = TaskAnnouncement::step($this->startedAt, time(), TaskDelivery::running($server));
            if ($step === TaskAnnouncement::WAIT && ! TaskAnnouncement::canWait(self::queueDriver())) {
                $step = TaskAnnouncement::GIVE_UP;
            }
            match ($step) {
                TaskAnnouncement::SEND => TaskDelivery::deliver($server, $payload, $this->schedule),
                TaskAnnouncement::GIVE_UP => TaskDelivery::deliver($server, $payload, $this->schedule, cameOnline: false),
                default => self::dispatch($this->server, $this->payload, $this->schedule, $this->startedAt)->delay(now()->addSeconds(TaskAnnouncement::CHECK_SECONDS)),
            };
        } catch (Throwable $error) {
            report($error);
        }
    }

    /** The driver of the queue this job goes on (sync runs a delayed job at once). */
    public static function queueDriver(): ?string
    {
        $connection = config('queue.default');
        $driver = is_string($connection) ? config('queue.connections.'.$connection.'.driver') : null;

        return is_string($driver) ? $driver : null;
    }
}
