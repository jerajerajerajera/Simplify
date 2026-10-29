<?php

declare(strict_types=1);

namespace Simplify\Core\Parts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simplify\Core\Announcements\Filament\AnnouncementResource;
use Simplify\Core\Embed\Filament\EmbedSettings;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Support\PanelSettings;
use Throwable;

/**
 * Former add-ons that are now part of Simplify. A root admin can turn each one off. While its separate plugin is
 * still installed, the part stays off so nothing shows twice, and the plugin's data is copied over, so uninstalling
 * the plugin (which drops its tables) loses nothing.
 */
final class Parts
{
    /**
     * part => [the separate plugin's service provider, the part's icon, its admin page, its name and description
     * (translation keys), its tables => Simplify's tables]. Tables are listed parents first.
     */
    public const PARTS = [
        'announcements' => [
            'provider' => 'Simplify\\Announcements\\Providers\\AnnouncementsProvider',
            'icon' => 'tabler-speakerphone',
            'page' => AnnouncementResource::class,
            'name' => 'announcements.addon.name',
            'description' => 'announcements.addon.description',
            'tables' => [
                'simplify_announcements' => 'simplify_core_announcements',
                'simplify_announcement_dismissals' => 'simplify_core_announcement_dismissals',
                'simplify_announcement_commands' => 'simplify_core_announcement_commands',
            ],
        ],
        'embed' => [
            'provider' => 'Simplify\\Embed\\Providers\\EmbedProvider',
            'icon' => 'tabler-code',
            'page' => EmbedSettings::class,
            'name' => 'embed.name',
            'description' => 'embed.description',
            'tables' => [
                'simplify_embed_settings' => 'simplify_core_embed_settings',
                'simplify_embed_servers' => 'simplify_core_embed_servers',
            ],
        ],
        'subdomains' => [
            'provider' => 'Simplify\\Subdomains\\Providers\\SubdomainsServiceProvider',
            'icon' => 'tabler-world',
            'page' => AutomaticSubdomains::class,
            'name' => 'subdomains.title',
            'description' => 'subdomains.addon.description',
            'tables' => [
                'simplify_subdomain_settings' => 'simplify_core_subdomain_settings',
                'simplify_subdomains' => 'simplify_core_subdomains',
            ],
        ],
    ];

    /** True when the part is turned on and its separate plugin is not installed. */
    public static function on(string $part): bool
    {
        return ! self::standalone($part) && (bool) PanelSettings::get('part_'.$part);
    }

    /** True while the separate plugin of this part is installed and turned on. */
    public static function standalone(string $part): bool
    {
        return app()->providerIsLoaded(self::PARTS[$part]['provider']);
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

    /**
     * Replaces the part's data with the separate plugin's, when that plugin's tables exist. Safe to repeat: the part
     * is off while the plugin is installed, so its own tables only ever hold a copy.
     */
    public static function import(string $part): void
    {
        $tables = self::PARTS[$part]['tables'];
        try {
            if (! Schema::hasTable(array_key_first($tables))) {
                return;
            }
            DB::transaction(function () use ($tables): void {
                foreach (array_reverse($tables) as $to) {
                    DB::table($to)->delete();
                }
                foreach ($tables as $from => $to) {
                    if (! Schema::hasTable($from)) {
                        continue;
                    }
                    $columns = array_values(array_intersect(Schema::getColumnListing($from), Schema::getColumnListing($to)));
                    foreach (DB::table($from)->select($columns)->get()->chunk(200) as $rows) {
                        DB::table($to)->insert($rows->map(fn (object $row) => (array) $row)->values()->all());
                    }
                    // Rows keep their ids; PostgreSQL's counter has to be moved past them by hand.
                    if (DB::getDriverName() === 'pgsql' && in_array('id', $columns, true)) {
                        DB::statement("SELECT setval(pg_get_serial_sequence('$to', 'id'), COALESCE((SELECT MAX(id) FROM $to), 0) + 1, false)");
                    }
                }
            });
        } catch (Throwable $error) {
            report($error);
        }
    }
}
