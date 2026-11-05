<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Tasks;

use App\Extensions\Tasks\Schemas\TaskSchema;
use App\Models\Task;
use Closure;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Simplify\Core\Announcements\Services\TaskDelivery;
use Simplify\Core\Announcements\Services\TaskWaits;
use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\TaskAnnouncement;
use Throwable;

/**
 * "Announcement" as an action in Pelican's schedules: a message in the server's game chat and/or as a notification
 * to the people of that server, right away or once the server is online. Registered with Pelican's TaskService
 * (SimplifyServiceProvider). canCreate() stays Pelican's own: its signature differs between Pelican versions.
 */
final class AnnouncementTask extends TaskSchema
{
    public const ID = 'simplify_announcement';

    private const T = 'simplify::announcements.task.';

    public function getId(): string
    {
        return self::ID;
    }

    public function getName(): string
    {
        return __(self::T.'name');
    }

    public function runTask(Task $task): void
    {
        try {
            $server = $task->server;
            if ($server === null) {
                return;
            }
            $payload = TaskAnnouncement::parse($task->payload);
            $schedule = $task->schedule->name;
            if ($payload['send'] === TaskAnnouncement::NOW || TaskDelivery::running($server)) {
                TaskDelivery::deliver($server, $payload, $schedule);

                return;
            }
            TaskWaits::add($server, $payload, $schedule, time());
        } catch (Throwable $error) {
            // An announcement never stops the schedule: the next task still runs.
            report($error);
        }
    }

    public function getDefaultPayload(): string
    {
        return TaskAnnouncement::encode(TaskAnnouncement::defaults());
    }

    public function getPayloadLabel(): string
    {
        return __(self::T.'message');
    }

    public function formatPayload(string $payload): string
    {
        $data = TaskAnnouncement::parse($payload);

        return $data['send'] === TaskAnnouncement::ONLINE ? __(self::T.'when_online', ['message' => $data['message']]) : $data['message'];
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [
            // One field for the whole payload (JSON): Pelican stores one payload per task and fills it with
            // getDefaultPayload() when the action is picked, which a set of separate fields would not get.
            ViewField::make('payload')
                ->view('simplify::announcements.task-field')
                ->hiddenLabel()
                ->viewData(['max' => GameChat::MESSAGE_LENGTH])
                ->default($this->getDefaultPayload())
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                    $data = json_decode(is_string($value) ? $value : '', true);
                    $problem = TaskAnnouncement::problem(is_array($data) ? $data : []);
                    if ($problem !== null) {
                        $fail(__(self::T.$problem));
                    }
                })
                ->dehydrateStateUsing(fn (mixed $state) => TaskAnnouncement::encode(TaskAnnouncement::parse(is_string($state) ? $state : null))),
        ];
    }
}
