<?php

declare(strict_types=1);

namespace Simplify\Core\Addons;

use App\Models\Server;

/**
 * What an add-on shows inside Simplify. See ADDONS.md.
 *
 * An add-on tags its subclass in its service provider:
 *
 *     $this->app->tag([MyAddon::class], AddonRegistry::TAG);
 *
 * Tagging stores only the class name, so the subclass is loaded only when Simplify asks for it.
 * Without Simplify the add-on keeps working on Pelican's own pages.
 *
 * New methods are added with a default, so an add-on built against an older version keeps working.
 */
abstract class Addon
{
    /** The contract version: 1 in Simplify 1.0, 2 since 1.1 (serverInfo). Raised whenever a method is added. */
    public const API = 2;

    /** The add-on's plugin id, for example "simplify-server-maker". */
    abstract public function id(): string;

    abstract public function name(): string;

    public function description(): string
    {
        return '';
    }

    /** A Tabler icon name, for example "tabler-server". */
    public function icon(): string
    {
        return 'tabler-puzzle';
    }

    /**
     * Buttons in the dashboard toolbar. Return an empty list when the current user may not use them.
     *
     * @return list<Button>
     */
    public function dashboardActions(): array
    {
        return [];
    }

    /** The add-on's own settings page, shown in Simplify's add-on list. */
    public function settingsUrl(): ?string
    {
        return null;
    }

    /**
     * Servers that Simplify leaves out of every list and count, by the start of their external id. For example
     * Egg Updates hides its temporary test servers with "simplify-egg-check-".
     *
     * @return list<string>
     */
    public function hiddenServerPrefixes(): array
    {
        return [];
    }

    /**
     * Short texts on the server's tile, for example its address.
     *
     * @return list<string>
     */
    public function tileBadges(Server $server): array
    {
        return [];
    }

    /**
     * What the add-on knows about a running game, for the server's card and page. Any of: players and max_players
     * (numbers), motd (Minecraft text with § codes), version (as the game reports it), icon (an image URL). The first
     * add-on that knows a value wins.
     *
     * @return array{players?: int, max_players?: int, motd?: string, version?: string, icon?: string}
     */
    public function serverInfo(Server $server): array
    {
        return [];
    }

    /**
     * Livewire components shown on the server's Simplify page. Each one receives the server as `server`.
     *
     * @return list<class-string>
     */
    public function serverSections(Server $server): array
    {
        return [];
    }
}
