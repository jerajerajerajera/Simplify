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
    public function id(): string { return 'simplify-servermaker'; }
    public function name(): string { return 'Server Maker'; }
    public function description(): string { return __('simplify-servermaker::addon.description'); }
    public function icon(): string { return 'tabler-server'; }

    public function dashboardActions(): array
    {
        if (! user()?->can('create server')) {
            return [];
        }

        return [new Button(__('simplify-servermaker::addon.create'), CreateServer::getUrl(panel: 'admin'), 'tabler-plus', primary: true)];
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
| `settingsUrl(): ?string` | `null` | "Settings" button in the add-on list, and a leaf under Simplify add-ons in the Admin menu, while `settingsForm()` gives no form |
| `settingsForm(): ?class-string<Livewire\Component>` | `null` | your settings in your section of Simplify settings › Add-ons, under your `description()`. See "Settings" below (since 2.0, API 8) |
| `hiddenServerPrefixes(): list<string>` | `[]` | servers whose external id starts with one of these are left out of every Simplify list and count |
| `tileBadges(Server $server): list<string>` | `[]` | short texts on a server tile, such as its address |
| `serverInfo(Server $server): array` | `[]` | players, max_players, motd, version and icon of a running game, on the server's card and page (since 1.1, API 2) |
| `serverLinks(Server $server): list<Button>` | `[]` | links in the ⋯ menu of the server page, opened in a new tab (since 1.1, API 2); a `primary` link also shows on a suspended server's card for its owner, under a line that the hoster paused it (since 1.5.5) |
| `customerLinks(User $user): list<Button>` | `[]` | links for customers (not admins) beside a greeting at the top of the overview; the panel's support link follows (since 1.1, API 2). A link with an `icon` (a Tabler name such as `tabler-receipt`) is also an icon button in the account menu, before Support, for everyone it is given to |
| `hostModeDefault(): bool` | `false` | No effect since the Admin page (every admin has it); kept so add-ons that override it still load (since 1.1, API 2) |
| `setupFields(): list<Component>` | `[]` | your most important settings as Filament fields in the last step of Simplify's setup, so a new panel is set up in one go (since 1.3, API 3) |
| `setupState(): array` | `[]` | the current values for those fields, by field name (since 1.3, API 3) |
| `saveSetup(array $state): void` | nothing | saves what the setup asked, by field name (since 1.3, API 3) |
| `accountPages(User $user): list<Button>` | `[]` | your own pages for every user, as icon buttons in Simplify's account menu (for example a Steam account). They open inside Simplify, in the content area, as Admin tools do; a URL that is not on this panel is left out. Without an `icon` the button shows `tabler-puzzle` (since 1.5.5, API 4) |
| `connections(User $user): list<class-string<Livewire\Component>>` | `[]` | your rows on Simplify's Connections page (account menu, right after Profile), one Livewire component per outside account, for example a Steam account or a CurseForge key. Each row is yours: it checks its user, saves on its own (the page has no Save and nothing saves by itself) and keeps its secrets. The page and its menu item show only while an add-on gives at least one row; without one the page answers 404. A class that is not a Livewire component is left out (since 2.0, API 6) |
| `startUrl(User $user): ?string` | `null` | a URL on this panel where this user starts: the server list and Simplify's overview redirect there, before the panel's own start page. Return `null` when the user should start in Simplify; while it returns a URL, Simplify puts its own header (panel name, bell, account menu) over Pelican's admin pages for admins, as the first child of `.fi-layout` (since 1.5.6, API 5) |
| `serverTabs(): array<string, list<class-string>>` | `[]` | your server pages as tabs in one of Simplify's rows instead of their own menu item, by row. The only row is `'backups'`: Backups, then the add-ons' pages, then Schedules, as one "Backups & tasks" item, for example `['backups' => [AutomaticBackups::class]]`. Label and address come from the class; a page the user may not open is left out (since 2.0, API 9) |
| `replacedServerPages(): list<class-string>` | `[]` | Pelican's server pages that a page of yours takes over, for example `Settings::class` and `Startup::class`: Simplify leaves them out of the server menu. Return them only while the current user can open your page (since 2.0, API 9) |

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
- `Addon::API` is the contract version: 1 in Simplify 1.0, 2 since 1.1 (`serverInfo()`, `serverLinks()`, `customerLinks()`, `hostModeDefault()`), 3 since 1.3 (`setupFields()`, `setupState()`, `saveSetup()`), 4 since 1.5.5 (`accountPages()`), 5 since 1.5.6 (`startUrl()`), 6 since 2.0 (`connections()`), 7 since 2.0 (`serverSections()` gone), 8 since 2.0 (`settingsForm()`), 9 since 2.0 (`serverTabs()`, `replacedServerPages()`). It goes up by one whenever a method is added or removed.
- A newer add-on on an older Simplify just works: Simplify never calls the methods it does not know yet. Check `defined(Addon::class.'::API') ? Addon::API : 1` only when your add-on wants to show something different on an older core.
- `\Simplify\Core\Health\HostHealth::cached()` is stable for add-ons: it returns the last shared host-health snapshot, or `[]` when none is kept, without running the checks (since 1.5.6).
- If your add-on throws, Simplify reports the error and leaves your add-on out. It never breaks a Simplify page.
- Install other plugins only from the Pelican Hub, and list every outside service you call in your README.

## Settings

Simplify settings › Add-ons has a section for every installed add-on, closed until it is opened: first your
`description()`, then your settings. Give your settings as a Livewire component in `settingsForm()` and the core shows
it there; while it does, your add-on has no leaf of its own in the Admin menu, and **Simplify add-ons** holds only
add-ons with a page of their own. With the core (`Addon::API >= 8`), hide your settings page from the menu and take
the settings button off your other pages; without the core they stay where they are.

- The component checks its user (root admin) and saves on its own. Its root element carries
  `data-simplify-unsaved` and `data-simplify-save="save"` ("Unsaved changes" below), so Save in the page's title bar
  saves it with the rest. Do not put a `<form>` in it: the section already sits inside Simplify's own form.
- Use the same component on your own settings page, so both stay the same.
- `Simplify\Core\Filament\Admin\Pages\Settings::addonUrl($id)` links to your section, opened (check
  `method_exists` first).

```php
public function settingsForm(): ?string { return ExplainSettingsForm::class; }
```

## Admin menu

Every admin page of Simplify and its add-ons sits in one group, **Simplify**, in Pelican's own menu. That works without the core too: the group is just a name.

```php
public static function getNavigationGroup(): ?string
{
    return 'Simplify';
}
```

An admin page never scrolls with the core installed: give the element that holds the page's form (or long content) the class `s-fit`. The core then makes it as tall as the window leaves and lets only that element scroll, as on Simplify's own form pages. Without the core the class does nothing.

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
set subdomains up. `gameAddress($server)` gives where players join: the working subdomain, otherwise the shared
game address with the port (`play.example.com:25566`) when the port is in the forwarded range, otherwise null.

`Simplify\Core\Server\Looks` sets a server's colour and banner, for example right after a server is made:
`Looks::for($server)` gives `color` and `banner`; `Looks::save($server, $color, $banner)` sets both, where `$banner`
is `false` to keep the current one, `null` for none, or a file the add-on stored on the local disk as
`Looks::DIRECTORY.'/<name>.(png|jpg|jpeg|webp)'` (name `[A-Za-z0-9_-]`, at most 80). Uploads no server shows any more are
deleted by Simplify. `Looks::COLORS` lists the colours a server's ⋯ menu offers (hex => name, in that order);
it is newer than `Looks::save()`, so check `defined(Looks::class.'::COLORS')` first.

`Simplify\Core\Support\PanelSettings::friendsMode()` is true when the panel runs in friends mode (everyone signed in
sees every server's card), false otherwise. It is newer than 2.0.0-beta.1, so check
`method_exists(PanelSettings::class, 'friendsMode')` first and treat a missing method as off.

`Simplify\Core\Embed\Services\EmbedServers::visible($server)` and `setVisible($server, $on)` read and set a server's
"Show on the website" choice (the same as its Website page). They check no permissions: the caller does that.

## Page head

Every page of Simplify and its add-ons has the same head, so they read as one product. The content is one card, its
buttons sit in its title bar, and there is no box inside a box. The head has three parts:

1. **Title**: what the page is, usually its name in the menu.
2. **Subtitle**: one short line saying what you do here or what it applies to (for example "For Paper 1.21.1"). Do not
   put a warning paragraph above the content; put the warning in the subtitle, or send it as a notification.
3. **Actions on the right**: Save comes last, as a text button "Save" with the `tabler-device-floppy` icon. Any other
   buttons go before it. Dangerous actions go in a ⋯ group, never as a large red button.

On a server page, the first `Section` of your form holds the title (`heading`) and the subtitle (`description`), and
`getHeaderActions()` gives the actions. With the core, the page's own Filament heading is hidden and the actions sit
at the right of that section's head. On an admin page, the breadcrumb in the Admin page's title bar is the title, and
the actions and `getSubheading()` stay in the page's header. Without the core, Filament shows its own heading and
actions as usual.

```php
public function form(Schema $schema): Schema
{
    return $schema->components([
        Section::make(__('my-addon::page.title'))
            ->description(__('my-addon::page.subtitle'))
            ->schema([/* fields */]),
    ]);
}

protected function getHeaderActions(): array
{
    return [
        Action::make('save')->label(__('my-addon::page.save'))->icon('tabler-device-floppy')
            ->action('save')->keyBindings(['mod+s']),
    ];
}
```

- Save works with "Unsaved changes" below: the form carries `data-simplify-unsaved`, and the `save` action counts as
  saved when it ends without validation errors.
- An empty page shows one grey line in the middle saying what is missing, with the action that fixes it in the head.
  Do not use an illustration or an empty-state icon.
- A page without a Filament form (your own Blade view) still follows the same model. With the core, draw its head with
  the partial `simplify::partials.page-head` (since 2.0.0-beta.6); without the core, use a Filament section with a
  heading and a description:

```blade
@if (view()->exists('simplify::partials.page-head'))
    @include('simplify::partials.page-head', [
        'title' => __('my-addon::page.title'),
        'subtitle' => __('my-addon::page.subtitle'),
        'actions' => [$this->refreshAction, $this->saveAction],
    ])
@endif
```

  `subtitle` and `actions` are optional; `actions` is one Filament action (or any `Htmlable`) or a list of them, Save
  last.

## Unsaved changes

Nothing in Simplify saves itself. Give your settings form the attribute `data-simplify-unsaved` and Simplify does the
rest: as soon as what the form holds differs from what was loaded or last saved, a bar says "Changes made" with Discard
and Save, and leaving the page (a link, a menu click with `wire:navigate`, going back, closing the tab) asks Save,
Discard or Cancel first.

```blade
<form wire:submit="save" data-simplify-unsaved="data">
    {{ $this->form }}
</form>
```

- The value names the Livewire properties to compare, comma separated. Left empty, a page with a `data` property (any
  Filament page) compares `data`, any other component all its properties, and a form outside Livewire its fields.
- Save in the bar submits the form. When the save is not the form's own submit, name the Livewire method with
  `data-simplify-save="method"`. A call of that method (or a Filament action of that name, like a Save button in the
  page's header) with no validation errors counts as saved; with errors the bar says the changes were not saved.
- Discard reloads the page. Say that it was saved in your own way (a Filament notification); the bar only goes away.
- From a script: `window.SimplifyUnsaved.guard(go)` asks first when there are changes and then runs `go` (use it when
  you close a sheet that holds a form); `window.SimplifyUnsaved.dirty()` tells whether there are any.
- On an older Simplify the attributes do nothing, so the form still works as before.

## Errors

Wherever Simplify or an add-on shows that something failed, an add-on such as Explain can put a small button right
after the message. The full contract is in Explain's README, ["Explain your plugin's errors"](https://github.com/jerajerajerajera/Simplify-Explain#explain-your-plugins-errors).
Put this spot in your view while the error is shown:

```blade
{{ \Filament\Support\Facades\FilamentView::renderHook('simplify::problem.actions', scopes: ['simplify-problem', 'simplify-problem:'.$problemId]) }}
```

- `$problemId` is `<plugin-id>:<kind>:<ref>`: 2 to 5 parts of only `A-Z a-z 0-9 _ . -`, at most 128 characters. For
  example `simplify-eggupdates:test:42` or `simplify:health:queue`.
- Without an add-on that registers the hook nothing renders, and no add-on needs Simplify or Explain for it.
- Which log belongs to the error comes from a source class tagged `simplify.problems` in your service provider, with
  `canExplainProblem(string $id, User $user): bool` and `explainProblem(string $id, User $user): ?array`. Check the
  user in both, and never give panel or Wings log lines to someone who is not a root admin.

Simplify puts the spot on each Health check that is Failed, Crashed or a Warning, with the id
`simplify:health:<check>` (the check's name in lower case without spaces, for example `simplify:health:queue`).
Checks that are fine get no spot.

A notification in Simplify's bell gets the spot when its data carries an error id under `simplify_problem`: in a
Filament notification `->viewData(['simplify_problem' => 'my-addon:test:42'])->sendToDatabase($user)`, in a Laravel
notification of your own at the top level of `toDatabase()`. The sender gives the id and is its source. The bell renders the spot once with the id `__ID__`
and fills in each notification's own id, so an add-on that renders a button in the spot must keep the id in its HTML
and check it again on the server.

On a server's page, a failed installation has the spot next to Try again, with the id `simplify:install:<server uuid>`.
Simplify is the source for it (`InstallProblems`): the last 100 lines of the install script's output from Wings, only
for root admins and people who may use the server's console.

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
