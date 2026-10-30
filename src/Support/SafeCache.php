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
 * files under storage/ belong to root and the web server may no longer change them.
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

    /** The file cache's folders belong to the user the panel runs as (only checked for the file store). */
    private static function writable(): bool
    {
        if (config('cache.default') !== 'file' || ! function_exists('posix_geteuid') || posix_geteuid() === 0) {
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
