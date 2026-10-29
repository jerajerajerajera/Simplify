# Building an add-on for Simplify

An add-on is a normal Pelican plugin that works on its own. When Simplify is installed too, it can show up inside
Simplify: on the dashboard, in Simplify's add-on list and on the server page. There is no hard dependency.

## 1. Tag your add-on class

In a service provider of your plugin (`src/Providers/`), always:

```php
$this->app->tag([ServerMakerAddon::class], 'simplify.addons');
```

No `class_exists`, `interface_exists` or load order is needed. Tagging stores only the class name. Simplify builds
the class when it draws a page, so without Simplify the class is never loaded.

## 2. Extend `Simplify\Core\Addons\Addon`

```php
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\Button;

final class ServerMakerAddon extends Addon
{
    public function id(): string { return 'simplify-server-maker'; }
    public function name(): string { return 'Server Maker'; }
    public function description(): string { return __('simplify-server-maker::addon.description'); }
    public function icon(): string { return 'tabler-server'; }

    public function dashboardActions(): array
    {
        if (! user()?->can('create server')) {
            return [];
        }

        return [new Button(__('simplify-server-maker::addon.create'), CreateServer::getUrl(panel: 'admin'), 'tabler-plus', primary: true)];
    }

    public function settingsUrl(): ?string { return MakerSettings::getUrl(panel: 'admin'); }
}
```

Only `id()` and `name()` are required. Everything else has a default.

| Method | Default | Where Simplify shows it |
| --- | --- | --- |
| `id(): string` | required | your plugin id |
| `name(): string` | required | add-on list |
| `description(): string` | `''` | add-on list |
| `icon(): string` | `'tabler-puzzle'` | add-on list (a Tabler icon name) |
| `dashboardActions(): list<Button>` | `[]` | buttons in the dashboard toolbar |
| `settingsUrl(): ?string` | `null` | "Settings" button in the add-on list |
| `hiddenServerPrefixes(): list<string>` | `[]` | servers whose external id starts with one of these are left out of every Simplify list and count |
| `tileBadges(Server $server): list<string>` | `[]` | short texts on a server tile, such as its address |
| `serverInfo(Server $server): array` | `[]` | players, max_players, motd, version and icon of a running game, on the server's card and page (since 1.1, API 2) |
| `serverSections(Server $server): list<class-string>` | `[]` | Livewire components on the server page; each gets `server` |
| `serverLinks(Server $server): list<Button>` | `[]` | links in the ⋯ menu of the server page, opened in a new tab (since 1.1, API 2) |
| `customerLinks(User $user): list<Button>` | `[]` | links for customers (not admins) beside a greeting at the top of the overview; the panel's support link follows (since 1.1, API 2) |
| `hostModeDefault(): bool` | `false` | `true` opens host mode for admins of panels with more than one node, until they pick a mode (since 1.1, API 2) |
| `setupFields(): list<Component>` | `[]` | your most important settings as Filament fields in the last step of Simplify's setup, so a new panel is set up in one go (since 1.3, API 3) |
| `setupState(): array` | `[]` | the current values for those fields, by field name (since 1.3, API 3) |
| `saveSetup(array $state): void` | nothing | saves what the setup asked, by field name (since 1.3, API 3) |

`Button` is `new Button(string $label, string $url, ?string $icon = null, bool $primary = false)`.

Return an empty list when the current user may not use something. Simplify does not check permissions for you.

The setup is for root admins only. Its first step recommends the add-ons in Simplify's catalog, with a link to each one's Hub page, so people install them before they set up. An add-on in the setup:

```php
public function setupFields(): array
{
    return [Toggle::make('enabled')->label(__('my-addon::setup.enabled'))];
}

public function setupState(): array
{
    return ['enabled' => MySettings::enabled()];
}

public function saveSetup(array $state): void
{
    MySettings::save(['enabled' => (bool) ($state['enabled'] ?? false)]);
}
```

## Rules

- New methods are only ever added with a default, so an add-on keeps working when Simplify updates. A test in Simplify's CI fails when a method without a default is added.
- `Addon::API` is the contract version: 1 in Simplify 1.0, 2 since 1.1 (`serverInfo()`, `serverLinks()`, `customerLinks()`, `hostModeDefault()`), 3 since 1.3 (`setupFields()`, `setupState()`, `saveSetup()`). It goes up by one whenever a method is added.
- A newer add-on on an older Simplify just works: Simplify never calls the methods it does not know yet. Check `defined(Addon::class.'::API') ? Addon::API : 1` only when your add-on wants to show something different on an older core.
- If your add-on throws, Simplify reports the error and leaves your add-on out. It never breaks a Simplify page.
- Install other plugins only from the Pelican Hub, and list every outside service you call in your README.

## Admin menu

Every admin page of Simplify and its add-ons sits in one group, **Simplify**, in Pelican's own menu. That works without the core too: the group is just a name.

```php
public static function getNavigationGroup(): ?string
{
    return 'Simplify';
}
```

Use the number below as `$navigationSort` for your add-on's main admin page (its other admin pages get the next numbers), so the core comes first and the add-ons follow from A to Z. A new add-on takes the free number in its place.

| Page | Sort |
| --- | --- |
| Simplify settings, People (core) | 1, 2 |
| Announcements (core) | 10 |
| Egg Updates | 20 |
| Embed (core) | 30 |
| Explain | 40 |
| Hosting | 50 |
| Server Maker | 80 |
| Subdomains (core) | 90 |
| Toolkit | 95 |

Pages on a server (such as Subdomain or Website) stay in the server's own menu.

Announcements, embed and subdomains are core features of Simplify since 1.2. Plugins that used Simplify Subdomains
can call `Simplify\Core\Subdomains\Subdomains` with the same methods; its `ready()` is false until a root admin has
set subdomains up.

## Events

Some things go through Laravel events with a plain string name, so an add-on needs no Simplify class for them.

### `simplify.push`: send an app notification

```php
use Illuminate\Support\Facades\Event;

if (Event::hasListeners('simplify.push')) {
    $devices = array_sum(event('simplify.push', [[
        'users' => [1, 2],           // user ids
        'title' => 'Maintenance tonight',
        'body' => 'The servers restart at 23:00.',
        'url' => null,               // opened on tap, or null
        'tag' => 'announcement-12',  // the same tag reaches each device once
    ]]));
}
```

Simplify listens only while push is turned on. The listener returns how many devices it sent to. People choose per
account whether they get these messages; a `url` outside the panel is dropped.

## Testing your add-on in a real panel

Copy `tests/panel/smoke.php` and the `panel-smoke` job from `.github/workflows/checks.yml`. Three traps they avoid:

- Pelican 1.0 has no `users.root_admin` column. Create the user, then `$admin->syncRoles(Role::getRootAdmin())`.
- Laravel's handler prints an error outside a step and exits with 0. Set your own `set_exception_handler` that exits
  with 1, end with the line `All steps passed`, and let CI check that line.
- Do not name a helper `expect()`: Pest in Pelican's vendor folder already has one.
