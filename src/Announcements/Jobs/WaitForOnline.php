<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Simplify\Core\Announcements\Services\TaskWaits;
use Simplify\Core\Announcements\Support\TaskAnnouncement;
use Throwable;

/**
 * Kept for jobs that 2.0.0-beta.10 already put on the queue: it hands the wait to TaskWaits, which the scheduler
 * looks at every minute. New announcements wait there right away (K-B64).
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
            if ($server instanceof Server) {
                TaskWaits::add($server, TaskAnnouncement::parse($this->payload), $this->schedule, $this->startedAt);
            }
        } catch (Throwable $error) {
            report($error);
        }
    }
}
