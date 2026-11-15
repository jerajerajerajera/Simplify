<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Closure;
use Illuminate\Cache\FileStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laravel's cache, but a cache that cannot be written never breaks a page: the value is worked out again instead,
 * and root admins get a notice with the command that fixes it. This happens when artisan ran as root, so cache
 * files under storage/ belong to root and the web server may no longer change them. The notice also comes when the
 * compiled views or bootstrap/cache belong to someone else, before a page answers 500 for it.
 */
final class SafeCache
{
    /** Keys written on almost every visit, checked by problem(). */
    private const WATCHED = ['simplify.settings', 'simplify.host-health'];

    private static bool $failed = false;

    public static function remember(string $key, int $seconds, Closure $value): mixed
    {
        try {
            return Cache::remember($key, $seconds, $value);
        } catch (Throwable $e) {
            self::failed($e);

            return $value();
        }
    }

    public static function rememberForever(string $key, Closure $value): mixed
    {
        try {
            return Cache::rememberForever($key, $value);
        } catch (Throwable $e) {
            self::failed($e);

            return $value();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable $e) {
            self::failed($e);

            return $default;
        }
    }

    public static function put(string $key, mixed $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (Throwable $e) {
            self::failed($e);
        }
    }

    /** False when the key was already there, or when nothing could be stored (so the caller does the work at most once). */
    public static function add(string $key, mixed $value, int $seconds): bool
    {
        try {
            return Cache::add($key, $value, $seconds);
        } catch (Throwable $e) {
            self::failed($e);

            return false;
        }
    }

    public static function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            self::failed($e);
        }
    }

    /**
     * For root admins: the command that gives the panel's files back to the web server, when the cache could not
     * be written in this request or the cache folder belongs to someone else.
     *
     * @return array{command: string}|null
     */
    public static function problem(): ?array
    {
        if (! self::$failed && self::writable()) {
            return null;
        }
        $user = function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data') : 'www-data';

        return ['command' => 'cd '.base_path().' && sudo chown -R '.$user.':'.$user.' storage bootstrap/cache && sudo -u '.$user.' php artisan optimize:clear'];
    }

    /**
     * Whether one of these paths belongs to someone else, or cannot be written. Paths that are not there are fine.
     *
     * @param  list<string>  $paths
     * @param  callable(string): (int|false)  $owner
     * @param  callable(string): bool  $writable
     */
    public static function foreign(array $paths, int $me, callable $owner, callable $writable): bool
    {
        foreach ($paths as $path) {
            if (file_exists($path) && ($owner($path) !== $me || ! $writable($path))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The folders the panel writes on its own: compiled views, Laravel's bootstrap cache and Simplify's markers among
     * the views. After an update or a plugin install as root they belong to root, and the next page that needs a new
     * compiled view answers 500 (Khion 15-11).
     *
     * @return list<string>
     */
    private static function panelPaths(): array
    {
        $views = rtrim((string) config('view.compiled', storage_path('framework/views')), '/');
        $bootstrap = base_path('bootstrap/cache');

        return [$views, $views.'/simplify-version', $views.'/simplify-opcache', $bootstrap, ...(glob($bootstrap.'/*.php') ?: [])];
    }

    /** The panel's own folders and the file cache's folders belong to the user the panel runs as. */
    private static function writable(): bool
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            return true;
        }
        if (self::foreign(self::panelPaths(), posix_geteuid(), fn (string $path) => @fileowner($path), is_writable(...))) {
            return false;
        }
        if (config('cache.default') !== 'file') {
            return true;
        }
        $root = (string) config('cache.stores.file.path', storage_path('framework/cache/data'));
        if (! is_dir($root)) {
            return true;
        }
        $me = posix_geteuid();
        foreach ([$root, ...(glob($root.'/*', GLOB_ONLYDIR) ?: [])] as $folder) {
            if (@fileowner($folder) !== $me) {
                return false;
            }
        }
        // The entries the panel changes on every visit, one folder deeper.
        $store = Cache::getStore();
        foreach (self::WATCHED as $key) {
            $file = $store instanceof FileStore ? $store->path($key) : null;
            if ($file !== null && file_exists($file) && (@fileowner($file) !== $me || @fileowner(dirname($file)) !== $me)) {
                return false;
            }
        }

        return true;
    }

    private static function failed(Throwable $e): void
    {
        if (! self::$failed) {
            Log::warning('Simplify could not use the cache: '.$e->getMessage());
        }
        self::$failed = true;
    }
}
