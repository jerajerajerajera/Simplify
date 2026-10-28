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
| `serverSections(Server $server): list<class-string>` | `[]` | Livewire components on the server page; each gets `server` |

`Button` is `new Button(string $label, string $url, ?string $icon = null, bool $primary = false)`.

Return an empty list when the current user may not use something. Simplify does not check permissions for you.

## Rules

- New methods are only ever added with a default, so an add-on keeps working when Simplify updates.
- If your add-on throws, Simplify reports the error and leaves your add-on out. It never breaks a Simplify page.
- Install other plugins only from the Pelican Hub, and list every outside service you call in your README.

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

Simplify listens only while push is turned on. The listener returns how many devices it sent to.
