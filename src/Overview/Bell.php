<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Simplify\Core\Support\SafeCache;

/**
 * Pelican's own notifications (backups, installs, subusers) under the bell in Simplify's top bar. Simplify's page
 * hides Pelican's top bar, so without this they would only show on Pelican's pages.
 */
final class Bell
{
    private const TONES = ['success' => 'ok', 'warning' => 'warn', 'danger' => 'danger', 'info' => 'info'];

    private const PAGE = 20;

    /** @return array{items: list<array<string, mixed>>, more: bool, unread: int} newest first */
    public static function page(User $user, int $page = 1): array
    {
        $page = max(1, min($page, 500));
        // One row more than asked tells whether there is another page, without counting them all.
        $rows = $user->notifications()->latest()->skip(($page - 1) * self::PAGE)->limit(self::PAGE + 1)->get();

        return [
            'items' => $rows->take(self::PAGE)->map(fn (DatabaseNotification $row) => self::present($row))->filter()->values()->all(),
            'more' => $rows->count() > self::PAGE,
            'unread' => $user->unreadNotifications()->count(),
            'storage' => $user->isRootAdmin() ? SafeCache::problem() : null,
        ];
    }

    /** @param list<mixed> $ids empty for all of them */
    public static function mark(User $user, array $ids, bool $read): void
    {
        $ids = array_filter($ids, fn ($id) => is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id) === 1);
        $query = $read ? $user->unreadNotifications() : $user->notifications();
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        $query->update(['read_at' => $read ? now() : null]);
    }

    /** @return array<string, mixed>|null */
    private static function present(DatabaseNotification $row): ?array
    {
        $data = is_array($row->data) ? $row->data : [];
        $title = self::text($data['title'] ?? '');
        $body = self::text($data['body'] ?? '');
        if ($title === '' && $body === '') {
            return null;
        }
        $action = null;
        foreach (is_array($data['actions'] ?? null) ? $data['actions'] : [] as $candidate) {
            $url = is_array($candidate) ? ($candidate['url'] ?? null) : null;
            if (is_string($url) && self::local($url)) {
                $action = ['label' => self::text($candidate['label'] ?? '') ?: 'Open', 'url' => $url];
                break;
            }
        }

        return [
            'id' => 'pelican-'.$row->id,
            'key' => (string) $row->id,
            'pelican' => true,
            'tone' => self::TONES[$data['status'] ?? $data['color'] ?? ''] ?? 'info',
            'title' => $title !== '' ? $title : $body,
            'text' => $title !== '' ? $body : '',
            'time' => $row->created_at?->diffForHumans(),
            'unread' => $row->read_at === null,
            'action' => $action,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5)) : '';
    }

    /** Relative links or links to this panel only. */
    private static function local(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) && parse_url($url, PHP_URL_HOST) === request()->getHost();
    }
}
