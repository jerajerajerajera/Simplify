<?php

declare(strict_types=1);

namespace Simplify\Core\Push;

use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Tells root admins once about a new Pelican or Wings version, as an app notification. The latest versions come from
 * Pelican's own version check, which Pelican already caches; the Wings versions from Pelican's cached node information.
 */
final class UpdateNotices
{
    /** @return int devices that got a notice */
    public static function send(): int
    {
        if (! Push::enabled()) {
            return 0;
        }
        $admins = User::role(Role::ROOT_ADMIN)->get();
        if ($admins->isEmpty()) {
            return 0;
        }
        $versions = app(SoftwareVersionService::class);
        $home = url('/admin');
        $sent = 0;

        $panel = $versions->latestPanelVersion();
        if ($panel !== null && ! $versions->isLatestPanel()) {
            $sent += self::inTheirLanguage($admins, fn (string $locale) => [
                'title' => __('simplify::push.panel_update', ['version' => $panel], $locale),
                'body' => __('simplify::push.panel_update_body', ['current' => $versions->currentPanelVersion()], $locale),
                'url' => $home,
                'tag' => 'pelican-'.$panel,
            ]);
        }

        $wings = $versions->latestWingsVersion();
        $older = [];
        foreach ($wings === null ? [] : Node::query()->get() as $node) {
            try {
                $version = $node->systemInformation()['version'] ?? null;
            } catch (Throwable) {
                $version = null;
            }
            if (is_string($version) && ! $versions->isLatestWings($version)) {
                $older[] = $node->name;
            }
        }
        if ($older !== []) {
            $sent += self::inTheirLanguage($admins, fn (string $locale) => [
                'title' => __('simplify::push.wings_update', ['version' => $wings], $locale),
                'body' => __('simplify::push.wings_update_body', ['nodes' => implode(', ', $older)], $locale),
                'url' => $home,
                'tag' => 'wings-'.$wings,
            ]);
        }

        return $sent;
    }

    /**
     * Each admin gets the notice in the language of their Pelican profile.
     *
     * @param  Collection<int, User>  $admins
     * @param  callable(string): array{title: string, body?: string, url?: ?string, tag?: ?string}  $message
     */
    private static function inTheirLanguage(Collection $admins, callable $message): int
    {
        $sent = 0;
        foreach ($admins->groupBy(fn (User $admin) => $admin->language ?: config('app.locale')) as $locale => $group) {
            $sent += Push::send($group, 'updates', $message((string) $locale));
        }

        return $sent;
    }
}
