<?php

declare(strict_types=1);

namespace Simplify\Core\Parts;

use Simplify\Core\Announcements\Filament\AnnouncementResource;
use Simplify\Core\Embed\Filament\EmbedSettings;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Support\PanelSettings;

/** Former add-ons that are now part of Simplify. A root admin can turn each one off. */
final class Parts
{
    /** part => [the part's icon, its admin page, its name and description (translation keys)] */
    public const PARTS = [
        'announcements' => [
            'icon' => 'tabler-speakerphone',
            'page' => AnnouncementResource::class,
            'name' => 'announcements.addon.name',
            'description' => 'announcements.addon.description',
        ],
        'embed' => [
            'icon' => 'tabler-code',
            'page' => EmbedSettings::class,
            'name' => 'embed.name',
            'description' => 'embed.description',
        ],
        'subdomains' => [
            'icon' => 'tabler-world',
            'page' => AutomaticSubdomains::class,
            'name' => 'subdomains.title',
            'description' => 'subdomains.addon.description',
        ],
    ];

    public static function on(string $part): bool
    {
        return (bool) PanelSettings::get('part_'.$part);
    }

    /** The container name under which the part's add-on (a PartAddon) is bound and tagged. */
    public static function addon(string $part): string
    {
        return 'simplify.parts.'.$part;
    }

    /** Leaves out the add-on of a part that is off, so it shows nowhere in Simplify. */
    public static function allows(string $addon): bool
    {
        $part = substr($addon, strlen(self::addon('')));

        return ! isset(self::PARTS[$part]) || self::addon($part) !== $addon || self::on($part);
    }
}
