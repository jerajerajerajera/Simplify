<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements;

use Simplify\Core\Addons\Addon;
use Simplify\Core\Announcements\Filament\AnnouncementResource;

/** Announcements in Simplify's add-on list, like an add-on. Left out while the part is off (Parts). */
final class AnnouncementsAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-announcements';
    }

    public function name(): string
    {
        return __('pelican-simplify::announcements.addon.name');
    }

    public function description(): string
    {
        return __('pelican-simplify::announcements.addon.description');
    }

    public function icon(): string
    {
        return 'tabler-speakerphone';
    }

    public function settingsUrl(): ?string
    {
        return user()?->isRootAdmin() ? AnnouncementResource::getUrl(panel: 'admin') : null;
    }
}
