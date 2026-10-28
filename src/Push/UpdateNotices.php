<?php

declare(strict_types=1);

namespace Simplify\Core\Push;

use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\SoftwareVersionService;
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
            $sent += Push::send($admins, 'updates', [
                'title' => __('pelican-simplify::push.panel_update', ['version' => $panel]),
                'body' => __('pelican-simplify::push.panel_update_body', ['current' => $versions->currentPanelVersion()]),
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
            $sent += Push::send($admins, 'updates', [
                'title' => __('pelican-simplify::push.wings_update', ['version' => $wings]),
                'body' => __('pelican-simplify::push.wings_update_body', ['nodes' => implode(', ', $older)]),
                'url' => $home,
                'tag' => 'wings-'.$wings,
            ]);
        }

        return $sent;
    }
}
