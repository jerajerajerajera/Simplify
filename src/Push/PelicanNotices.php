<?php

declare(strict_types=1);

namespace Simplify\Core\Push;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Pelican's own notifications in the bell, such as a finished backup, also reach the devices of people who chose
 * "Pelican's notifications". Simplify's own bell items (announcements, plugin updates) are pushed by Simplify itself.
 */
final class PelicanNotices
{
    public static function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User || ! $event->response instanceof DatabaseNotification) {
            return;
        }
        $data = is_array($event->response->data) ? $event->response->data : [];
        $view = is_array($data['viewData'] ?? null) ? $data['viewData'] : [];
        if (isset($view['simplify_announcement']) || isset($view['simplify_push'])) {
            return;
        }
        $title = self::text($data['title'] ?? '');
        $body = self::text($data['body'] ?? '');
        if ($title === '' && $body === '') {
            return;
        }
        $url = null;
        foreach (is_array($data['actions'] ?? null) ? $data['actions'] : [] as $action) {
            if (is_array($action) && is_string($action['url'] ?? null)) {
                $url = $action['url'];
                break;
            }
        }
        $user = $event->notifiable;
        $message = ['title' => $title !== '' ? $title : $body, 'body' => $title !== '' ? $body : '', 'url' => $url, 'tag' => 'pelican-'.$event->response->id];
        // After the response (or the job) that made the notification, so a slow push service never holds it up.
        dispatch(fn () => Push::send([$user], 'pelican', $message))->afterResponse();
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5)) : '';
    }
}
