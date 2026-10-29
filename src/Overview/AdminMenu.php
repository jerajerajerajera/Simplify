<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Throwable;

/**
 * Pelican's admin pages for the shield in Simplify's top bar, read from the admin panel itself: pages of other plugins
 * show up too, and every item keeps that page's own access check. Clicking one opens Pelican's page.
 */
final class AdminMenu
{
    /** Class name => icon. The dashboard sorts these into coloured sections by their lower-case name. */
    private const ICONS = [
        'ServerResource' => 'host', 'EggResource' => 'egg', 'MountResource' => 'files', 'NodeResource' => 'monitor',
        'DatabaseHostResource' => 'database', 'BackupHostResource' => 'backups', 'UserResource' => 'players',
        'RoleResource' => 'lock', 'ApiKeyResource' => 'key', 'PluginResource' => 'plugins', 'Settings' => 'gear',
        'WebhookResource' => 'link', 'Health' => 'heart', 'ListLogs' => 'activity',
    ];

    /** Pages that need parameters or that the menu does not list. */
    private const SKIP = ['Dashboard', 'ViewLogs', 'Setup'];

    /** @return list<array{label: string, url: string, primary: bool, native: bool}> Pelican's own "create server" form, for admins who may use it */
    public static function createServer(): array
    {
        foreach (Filament::getPanel('admin')->getResources() as $class) {
            try {
                if (class_basename($class) === 'ServerResource' && $class::canCreate()) {
                    return [['label' => '', 'url' => $class::getUrl('create', panel: 'admin'), 'primary' => false, 'native' => true]];
                }
            } catch (Throwable) {
                return [];
            }
        }

        return [];
    }

    /** @return list<array{label: string, items: list<array{key: string, label: string, url: string, icon: string, origin: string}>}> */
    public static function items(): array
    {
        if (! user()?->isAdmin()) {
            return [];
        }
        $items = [];
        foreach ([...Filament::getPanel('admin')->getResources(), ...Filament::getPanel('admin')->getPages()] as $class) {
            $name = class_basename($class);
            if (in_array($name, self::SKIP, true)) {
                continue;
            }
            try {
                $resource = is_subclass_of($class, Resource::class);
                if ($resource ? ! $class::canViewAny() : ! $class::canAccess() || (! isset(self::ICONS[$name]) && ! $class::shouldRegisterNavigation())) {
                    continue;
                }
                $items[] = [
                    'key' => strtolower($name),
                    'label' => (string) $class::getNavigationLabel(),
                    'url' => $resource ? $class::getUrl('index', panel: 'admin') : $class::getUrl(panel: 'admin'),
                    'icon' => self::ICONS[$name] ?? 'plugins',
                    'origin' => self::origin($class),
                ];
            } catch (Throwable) {
                continue;
            }
        }
        usort($items, fn (array $a, array $b) => strnatcasecmp($a['label'], $b['label']));

        return $items === [] ? [] : [['label' => 'admin', 'items' => $items]];
    }

    /** Simplify's own pages are "core", the add-ons' pages (in the Simplify group) "addon"; the rest belongs to other plugins or Pelican. */
    private static function origin(string $class): string
    {
        if (str_starts_with($class, 'Simplify\\Core\\')) {
            return 'core';
        }

        return method_exists($class, 'getNavigationGroup') && $class::getNavigationGroup() === 'Simplify' ? 'addon' : '';
    }
}
