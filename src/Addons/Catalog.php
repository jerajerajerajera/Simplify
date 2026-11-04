<?php

declare(strict_types=1);

namespace Simplify\Core\Addons;

/**
 * The add-ons made for Simplify, so the setup can show what exists. Each is a separate plugin on the Pelican Hub;
 * Simplify only links there and never installs anything itself. An installed add-on describes itself (Addon::name,
 * description, icon); the list below is only for the ones that are not installed yet.
 */
final class Catalog
{
    public const HUB = 'https://hub.pelican.dev/plugins/';

    /** id => [icon, colour of its card]; the Hub page of each is HUB.id */
    public const ADDONS = [
        'simplify-servermaker' => ['tabler-wand', '#6ea8fe'],
        'simplify-toolkit' => ['tabler-cube', '#4ec9a0'],
        'simplify-explain' => ['tabler-message-question', '#e0a050'],
        'simplify-eggupdates' => ['tabler-egg', '#e7c95a'],
        'simplify-hosting' => ['tabler-receipt', '#b48cf2'],
    ];

    /** @return list<array{id: string, icon: string, tone: string, name: string, description: string, url: string, installed: bool}> */
    public static function all(AddonRegistry $registry): array
    {
        $list = [];
        foreach (self::ADDONS as $id => [$icon, $tone]) {
            $key = 'simplify::setup.catalog.'.substr($id, strlen('simplify-'));
            $addon = $registry->find($id);
            $list[] = [
                'id' => $id,
                'icon' => self::own(fn () => $addon?->icon(), $icon),
                'tone' => $tone,
                'name' => self::own(fn () => $addon?->name(), __($key.'.name')),
                'description' => self::own(fn () => $addon?->description(), __($key.'.description')),
                'url' => self::HUB.$id,
                'installed' => $addon !== null,
            ];
        }

        return $list;
    }

    /**
     * What an installed add-on says about itself, or the catalog's own text when it says nothing or fails.
     *
     * @param  \Closure(): ?string  $read
     */
    private static function own(\Closure $read, string $fallback): string
    {
        try {
            $value = trim((string) $read());
        } catch (\Throwable) {
            $value = '';
        }

        return $value === '' || $value === 'tabler-puzzle' ? $fallback : $value;
    }
}
