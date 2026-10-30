<?php

declare(strict_types=1);

namespace Simplify\Core\Push;

use App\Models\Plugin;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\SoftwareVersionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Simplify\Core\Support\SafeCache;
use Throwable;

/**
 * Tells root admins once per version that a plugin has an update, in the bell (and as an app notification), with a
 * button to Admin → Plugins. Pelican's own update check finds it and Pelican installs it: Simplify downloads nothing.
 * Without this Pelican only shows an update when someone opens Admin → Plugins.
 */
final class PluginUpdates
{
    /** Remembers a told version this long, so the bell gets each version once. */
    private const DAYS = 90;

    /** @return int plugins with a new update that admins were told about */
    public static function send(): int
    {
        $admins = User::role(Role::ROOT_ADMIN)->get();
        if ($admins->isEmpty() || ! class_exists(Plugin::class)) {
            return 0;
        }
        $told = 0;
        foreach (Plugin::query()->get() as $plugin) {
            try {
                if (! method_exists($plugin, 'isUpdateAvailable') || ! $plugin->isUpdateAvailable()) {
                    continue;
                }
                $version = self::latest($plugin);
                if (! SafeCache::add('simplify.plugin-update.'.$plugin->id.'.'.($version ?? 'new'), true, self::DAYS * 86400)) {
                    continue;
                }
                self::tell($admins, (string) $plugin->name, $version);
                $told++;
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $told;
    }

    /** The version Pelican's update check offers, read from the answer Pelican just cached (null when unknown). */
    private static function latest(Plugin $plugin): ?string
    {
        $data = cache()->get('plugins.'.$plugin->id.'.update');
        if (! is_array($data)) {
            return null;
        }
        $data = is_array($data[$plugin->id] ?? null) ? $data[$plugin->id] : $data;
        try {
            $panel = App::call(fn (SoftwareVersionService $service) => method_exists($service, 'currentComparableVersion') ? $service->currentComparableVersion() : null);
        } catch (Throwable) {
            $panel = null;
        }
        $entry = ($panel !== null ? $data[$panel] ?? null : null) ?? $data['*'] ?? null;
        $version = is_array($entry) ? ($entry['version'] ?? null) : null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    /** @param iterable<User> $admins */
    private static function tell(iterable $admins, string $name, ?string $version): void
    {
        $url = self::pluginsUrl();
        foreach ($admins as $admin) {
            $locale = $admin->language ?: config('app.locale');
            $title = $version !== null
                ? __('simplify::push.plugin_update', ['name' => $name, 'version' => $version], $locale)
                : __('simplify::push.plugin_update_unknown', ['name' => $name], $locale);
            $body = __('simplify::push.plugin_update_body', [], $locale);
            $admin->notifyNow(Notification::make()
                ->title($title)
                ->body($body)
                ->info()
                ->actions($url ? [Action::make('open')->label(__('simplify::push.open_plugins', [], $locale))->url($url)] : [])
                ->toDatabase());
            Push::send([$admin], 'updates', ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => 'plugin-'.$name.'-'.$version]);
        }
    }

    private static function pluginsUrl(): ?string
    {
        try {
            foreach (Filament::getPanel('admin')->getResources() as $class) {
                if (class_basename($class) === 'PluginResource') {
                    return $class::getUrl('index', panel: 'admin');
                }
            }
        } catch (Throwable) {
        }

        return null;
    }
}
