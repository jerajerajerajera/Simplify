<?php

declare(strict_types=1);

namespace Simplify\Core\Addons;

/**
 * The add-ons made for Simplify, so the setup can show what exists. Each is a separate plugin on the Pelican Hub;
 * Simplify only links there and never installs anything itself.
 */
final class Catalog
{
    public const HUB = 'https://hub.pelican.dev/plugins/';

    /** id => icon */
    public const ADDONS = [
        'simplify-server-maker' => 'tabler-wand',
        'simplify-subdomains' => 'tabler-world',
        'simplify-mods' => 'tabler-cube',
        'simplify-announcements' => 'tabler-speakerphone',
        'simplify-explain' => 'tabler-message-question',
        'simplify-egg-updates' => 'tabler-egg',
        'simplify-embed' => 'tabler-code',
        'simplify-plugins' => 'tabler-world-search',
    ];

    /** @return list<array{id: string, icon: string, name: string, description: string, url: string, installed: bool}> */
    public static function all(AddonRegistry $registry): array
    {
        $list = [];
        foreach (self::ADDONS as $id => $icon) {
            $key = 'pelican-simplify::setup.catalog.'.substr($id, strlen('simplify-'));
            $list[] = [
                'id' => $id,
                'icon' => $icon,
                'name' => __($key.'.name'),
                'description' => __($key.'.description'),
                'url' => self::HUB.$id,
                'installed' => $registry->find($id) !== null,
            ];
        }

        return $list;
    }
}
