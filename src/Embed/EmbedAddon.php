<?php

declare(strict_types=1);

namespace Simplify\Core\Embed;

use Simplify\Core\Addons\Addon;
use Simplify\Core\Embed\Filament\EmbedSettings;

/** Embed in Simplify's add-on list, like an add-on. Left out while the part is off (Parts). */
final class EmbedAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-embed';
    }

    public function name(): string
    {
        return __('pelican-simplify::embed.name');
    }

    public function description(): string
    {
        return __('pelican-simplify::embed.description');
    }

    public function icon(): string
    {
        return 'tabler-code';
    }

    public function settingsUrl(): ?string
    {
        return user()?->isRootAdmin() ? EmbedSettings::getUrl(panel: 'admin') : null;
    }
}
