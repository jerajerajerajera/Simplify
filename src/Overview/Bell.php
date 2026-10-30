<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Bell as AnnouncementBell;
use Simplify\Core\Support\Preferences;
use Simplify\Core\Support\SafeCache;

/**
 * Pelican's own notifications (backups, installs, subusers) under the bell in Simplify's top bar. Simplify's page
 * hides Pelican's top bar, so without this they would only show on Pelican's pages.
 *
 * Archived (as in 0.7.0): "Clear read" moves every read notification there, ✕ moves one. Nothing is deleted; the
 * account keeps when it last cleared (read before then is archived) and the ids closed one by one.
 *
 * Pinned (as in 0.7.0): an announcement an admin pinned to everyone's notifications stays on top, read or not, until
 * it ends; Clear read and ✕ leave it there.
 */
final class Bell
{
    private const TONES = ['success' => 'ok', 'warning' => 'warn', 'danger' => 'danger', 'info' => 'info'];

    private const PAGE = 20;

    private const ARCHIVE = 'notifications.archive';

    /** How many notifications closed one by one are remembered; older ones are long gone from the first pages. */
    private const ARCHIVE_IDS = 500;

    private const ID = '/^[0-9a-f-]{36}$/i';

    /**
     * @return array{items: list<array<string, mixed>>, pinned: list<array<string, mixed>>, more: bool, unread: int, archived: bool}
     *         newest first; $archived: the ones under Archived
     */
    public static function page(User $user, int $page = 1, bool $archived = false): array
    {
        $page = max(1, min($page, 500));
        $archive = self::archive($user);
        $pinned = self::pinnedAnnouncements();
        // One row more than asked tells whether there is another page, without counting them all.
        $rows = self::archived(self::withoutPinned($user->notifications(), $pinned), $archive, $archived)
            ->latest()->skip(($page - 1) * self::PAGE)->limit(self::PAGE + 1)->get();
        $top = $pinned === [] || $archived ? collect() : self::pinned($user->notifications(), $pinned)->latest()->limit(self::PAGE)->get();

        return [
            'items' => $rows->take(self::PAGE)->map(fn (DatabaseNotification $row) => self::present($row, $archived))->filter()->values()->all(),
            'pinned' => $top->map(fn (DatabaseNotification $row) => [...(self::present($row) ?? []), 'pinned' => true])->filter(fn (array $item) => isset($item['key']))->values()->all(),
            'more' => $rows->count() > self::PAGE,
            'unread' => $user->unreadNotifications()->count(),
            'archived' => $archive['at'] > 0 || $archive['ids'] !== [],
            'storage' => $user->isRootAdmin() ? SafeCache::problem() : null,
        ];
    }

    /** "Clear read" ($ids empty): every read notification moves to Archived. ✕ on one: that one, read. */
    public static function archiveNotifications(User $user, array $ids = []): void
    {
        $archive = self::archive($user);
        $ids = self::ids($ids);
        if ($ids === []) {
            $archive['at'] = now()->getTimestamp();
        } else {
            $user->unreadNotifications()->whereIn('id', $ids)->update(['read_at' => now()]);
            $archive['ids'] = array_slice(array_values(array_unique([...$archive['ids'], ...$ids])), -self::ARCHIVE_IDS);
        }
        Preferences::set($user, self::ARCHIVE, $archive);
    }

    /** @param list<mixed> $ids empty for all of them */
    public static function mark(User $user, array $ids, bool $read): void
    {
        $ids = self::ids($ids);
        $query = $read ? $user->unreadNotifications() : $user->notifications();
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        $query->update(['read_at' => $read ? now() : null]);
        // Mark as unread takes a notification out of Archived again (unread ones never fall under "Clear read").
        if (! $read && $ids !== []) {
            $archive = self::archive($user);
            if (array_intersect($archive['ids'], $ids) !== []) {
                $archive['ids'] = array_values(array_diff($archive['ids'], $ids));
                Preferences::set($user, self::ARCHIVE, $archive);
            }
        }
    }

    /** @return list<int> the live announcements an admin pinned to everyone's notifications */
    private static function pinnedAnnouncements(): array
    {
        return Announcement::query()->live()->where('in_bell', true)->where('bell_pinned', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The bell items of those announcements that went to everyone (not the ones an account got by unpinning a bar).
     *
     * @param  list<int>  $announcements
     */
    private static function pinned(MorphMany|Builder $query, array $announcements): MorphMany|Builder
    {
        return $query->whereIn('data->viewData->simplify_announcement', $announcements)->where('data->viewData->simplify_kind', AnnouncementBell::EVERYONE);
    }

    /** @param  list<int>  $announcements */
    private static function withoutPinned(MorphMany $query, array $announcements): MorphMany
    {
        return $announcements === [] ? $query : $query->whereNotIn('id', fn ($q) => self::pinned($q->select('id')->from('notifications'), $announcements));
    }

    /** @return array{at: int, ids: list<string>} */
    private static function archive(User $user): array
    {
        $saved = Preferences::get($user, self::ARCHIVE);
        $saved = is_array($saved) ? $saved : [];

        return [
            'at' => is_int($saved['at'] ?? null) ? $saved['at'] : 0,
            'ids' => self::ids(is_array($saved['ids'] ?? null) ? $saved['ids'] : []),
        ];
    }

    /**
     * Only the notifications under Archived ($archived), or only the others.
     *
     * @param  array{at: int, ids: list<string>}  $archive
     */
    private static function archived(MorphMany $query, array $archive, bool $archived): MorphMany
    {
        $cutoff = $archive['at'] > 0 ? now()->setTimestamp($archive['at']) : null;
        if ($archived) {
            return $query->where(fn ($q) => $q
                ->when($cutoff, fn ($q) => $q->orWhere(fn ($q) => $q->whereNotNull('read_at')->where('read_at', '<=', $cutoff)))
                ->when($archive['ids'] !== [], fn ($q) => $q->orWhereIn('id', $archive['ids']))
                ->when(! $cutoff && $archive['ids'] === [], fn ($q) => $q->whereRaw('1 = 0')));
        }

        return $query
            ->when($cutoff, fn ($q) => $q->where(fn ($q) => $q->whereNull('read_at')->orWhere('read_at', '>', $cutoff)))
            ->when($archive['ids'] !== [], fn ($q) => $q->whereNotIn('id', $archive['ids']));
    }

    /** @return list<string> */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && preg_match(self::ID, $id) === 1)));
    }

    /** @return array<string, mixed>|null */
    private static function present(DatabaseNotification $row, bool $archived = false): ?array
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
            'archived' => $archived,
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
