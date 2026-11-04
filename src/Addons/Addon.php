<?php

declare(strict_types=1);

namespace Simplify\Core\Addons;

use App\Models\Server;
use App\Models\User;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

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
    /**
     * The contract version: 1 in Simplify 1.0, 2 since 1.1 (serverInfo, serverLinks, customerLinks, hostModeDefault),
     * 3 since 1.3 (setupFields, setupState, saveSetup), 4 since 1.5.5 (accountPages), 5 since 1.5.6 (startUrl), 6 since
     * 2.0 (connections), 8 since 2.0 (settingsForm), 9 since 2.0 (serverTabs, replacedServerPages). Raised whenever a method is added.
     */
    public const API = 9;

    /** The add-on's plugin id, for example "simplify-servermaker". */
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

    /** The add-on's own settings page, shown in Simplify's add-on list. Not used while settingsForm() gives a form. */
    public function settingsUrl(): ?string
    {
        return null;
    }

    /**
     * The add-on's settings as a Livewire component, shown under its explanation in Simplify settings › Add-ons. The
     * component saves on its own (ADDONS.md, "Settings"). Null: the add-on list links to settingsUrl() instead.
     *
     * @return class-string<\Livewire\Component>|null
     */
    public function settingsForm(): ?string
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
     * Links in the ⋯ menu of the server's page, for example "Upgrade this server" on the hoster's billing page.
     * They open in a new tab.
     *
     * @return list<Button>
     */
    public function serverLinks(Server $server): array
    {
        return [];
    }

    /**
     * Links for customers (users who are not admins) at the top of the overview, beside a greeting, for example the
     * hoster's client area. The greeting shows only when an add-on gives at least one link.
     *
     * @return list<Button>
     */
    public function customerLinks(User $user): array
    {
        return [];
    }

    /**
     * The add-on's own pages for every user, as buttons in Simplify's account menu, for example Server Maker's Steam
     * account. Each opens inside Simplify, in the content area; a URL outside this panel is left out.
     *
     * @return list<Button>
     */
    public function accountPages(User $user): array
    {
        return [];
    }

    /**
     * The add-on's rows on Simplify's Connections page (the account menu), one Livewire component class per row, for
     * example Server Maker's Steam account. Each row is the add-on's own: it checks its user, saves on its own (the page
     * has no Save) and keeps its secrets. The page and its menu item show only while at least one add-on gives a row.
     *
     * @return list<class-string<\Livewire\Component>>
     */
    public function connections(User $user): array
    {
        return [];
    }

    /** Whether host mode is the default for admins on panels with more than one node, until they pick a mode. */
    public function hostModeDefault(): bool
    {
        return false;
    }

    /**
     * Where this user starts instead of the overview, for example a hoster in Admin (Simplify-Hosting). Null keeps
     * the overview. While it is set, Pelican's admin pages also get Simplify's header.
     */
    public function startUrl(User $user): ?string
    {
        return null;
    }

    /**
     * The add-on's server pages that join one of Simplify's rows of tabs instead of getting their own menu item, by row.
     * The only row is 'backups': Backups first, then the add-ons' pages, then Schedules, as one "Backups & tasks" item.
     * Each page is a page or resource class of the server panel; one the user may not open is left out.
     *
     * @return array<string, list<class-string>>
     */
    public function serverTabs(): array
    {
        return [];
    }

    /**
     * Pelican's server pages that a page of the add-on takes over, for example Settings and Startup. Simplify leaves
     * them out of the server menu. Return them only while the current user can open the page that replaces them.
     *
     * @return list<class-string>
     */
    public function replacedServerPages(): array
    {
        return [];
    }

    /**
     * Livewire components that were shown under a server's console. Simplify 1.3.9 and later no longer show them, as
     * the console page fits the window; the method stays so add-ons that still return sections keep working.
     *
     * @return list<class-string>
     */
    public function serverSections(Server $server): array
    {
        return [];
    }

    /**
     * Filament form components for the add-on's most important settings, asked in Simplify's setup so a new panel is
     * set up in one go. Field names are relative to the add-on: they get setupState() and come back in saveSetup().
     * Empty: the setup links to settingsUrl() instead.
     *
     * @return list<Component|Field>
     */
    public function setupFields(): array
    {
        return [];
    }

    /**
     * The current values for setupFields(), by field name.
     *
     * @return array<string, mixed>
     */
    public function setupState(): array
    {
        return [];
    }

    /**
     * Saves what the setup asked, by field name, after the setup's own checks passed.
     *
     * @param  array<string, mixed>  $state
     */
    public function saveSetup(array $state): void {}
}
