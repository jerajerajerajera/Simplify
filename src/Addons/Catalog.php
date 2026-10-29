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

    /** id => [icon, colour of its card]; the Hub page of each is HUB.id */
    public const ADDONS = [
        'simplify-server-maker' => ['tabler-wand', '#6ea8fe'],
        'simplify-toolkit' => ['tabler-cube', '#4ec9a0'],
        'simplify-explain' => ['tabler-message-question', '#e0a050'],
        'simplify-egg-updates' => ['tabler-egg', '#e7c95a'],
        'simplify-hosting' => ['tabler-receipt', '#b48cf2'],
    ];

    /** @return list<array{id: string, icon: string, tone: string, name: string, description: string, url: string, installed: bool}> */
    public static function all(AddonRegistry $registry): array
    {
        $list = [];
        foreach (self::ADDONS as $id => [$icon, $tone]) {
            $key = 'pelican-simplify::setup.catalog.'.substr($id, strlen('simplify-'));
            $list[] = [
                'id' => $id,
                'icon' => $icon,
                'tone' => $tone,
                'name' => __($key.'.name'),
                'description' => __($key.'.description'),
                'url' => self::HUB.$id,
                'installed' => $registry->find($id) !== null,
            ];
        }

        return $list;
    }
}
