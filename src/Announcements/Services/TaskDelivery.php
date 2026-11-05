<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Enums\ContainerStatus;
use App\Models\Server;
use App\Models\User;
use Filament\Notifications\Notification;
use Simplify\Core\Announcements\Models\GameCommand;
use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\TaskAnnouncement;
use Simplify\Core\Push\Push as AppPush;
use Throwable;

/**
 * Sends the "Announcement" task of a schedule (AnnouncementTask): in the server's game chat and/or as a notification
 * (bell and app) to the server's owner and the people it is shared with. Never throws, so the schedule goes on.
 */
final class TaskDelivery
{
    /**
     * @param  array{message: string, send: string, game_chat: bool, notify: bool}  $payload
     * @param  bool  $cameOnline  false when "When the server is online" gave up: the people hear that instead
     */
    public static function deliver(Server $server, array $payload, ?string $schedule, bool $cameOnline = true): void
    {
        try {
            $running = $cameOnline && self::running($server);
            if ($cameOnline) {
                self::gameChat($server, $payload, $running);
            }
            if ($payload['notify']) {
                self::notify($server, $payload, $schedule, $cameOnline);
            }
        } catch (Throwable $error) {
            report($error);
        }
    }

    public static function running(Server $server): bool
    {
        try {
            return ! $server->isSuspended() && $server->retrieveStatus() === ContainerStatus::Running;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param  array{message: string, send: string, game_chat: bool, notify: bool}  $payload */
    private static function gameChat(Server $server, array $payload, bool $running): void
    {
        $own = $server->egg_id ? GameCommand::query()->where('egg_id', $server->egg_id)->value('command') : null;
        $command = GameChat::commandFor($server->egg_id ? (string) $server->egg->name : '', is_string($own) ? $own : null);
        if (! TaskAnnouncement::postsInGame($payload['game_chat'], $command, $running) || $command === null) {
            return;
        }
        // The prefix in the panel's language: everyone in the game reads the same line.
        $line = GameChat::message(__('simplify::announcements.in_game.prefix'), $payload['message'], null);
        try {
            $server->send(GameChat::render($command, $line));
        } catch (Throwable) {
            // Offline after all, or Wings did not answer: the notification still goes.
        }
    }

    /** @param  array{message: string, send: string, game_chat: bool, notify: bool}  $payload */
    private static function notify(Server $server, array $payload, ?string $schedule, bool $cameOnline): void
    {
        $ids = TaskAnnouncement::recipients($server->owner_id, $server->subusers()->pluck('user_id')->all());
        $users = User::query()->whereKey($ids)->get()->keyBy('id');
        $title = $schedule !== null && $schedule !== '' ? $server->name.' · '.$schedule : $server->name;
        $languages = $users->map(fn (User $user) => $user->language)->all();

        foreach (TaskAnnouncement::byLanguage($languages, (string) config('app.locale', 'en')) as $language => $group) {
            // What someone typed stays as typed; only Simplify's own words follow each person's language.
            $body = $cameOnline ? $payload['message'] : __('simplify::announcements.task.not_online', ['server' => $server->name], $language);
            $people = $users->only($group)->values();
            foreach ($people as $user) {
                $user->notifyNow(Notification::make()
                    ->title($title)
                    ->body($body)
                    ->icon($cameOnline ? 'tabler-speakerphone' : 'tabler-alert-triangle')
                    ->iconColor($cameOnline ? 'info' : 'warning')
                    ->toDatabase());
            }
            AppPush::send($people, 'messages', [
                'title' => $title,
                'body' => $body,
                'url' => url('/server/'.$server->uuid_short),
                'tag' => 'simplify-task-'.$server->id.'-'.time(),
            ]);
        }
    }
}
