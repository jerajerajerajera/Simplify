<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Throwable;

/**
 * Throws away Laravel's compiled copies of Simplify's views once after Simplify's version changes.
 *
 * Laravel compiles a view again only when its file is newer than the compiled copy. An update from a zip keeps the
 * time each file was built, which can be older than a copy compiled before the update. The panel then kept running
 * the old page, for example a 1.5.x overview that includes a view 2.0 no longer has (K-B57).
 */
final class FreshViews
{
    /**
     * @param  string  $version  The version in plugin.json.
     * @param  string|null  $known  The version the compiled views belong to (null: not known).
     * @param  iterable<string>  $views  Paths of Simplify's view files.
     * @param  callable(string): string  $compiledPath  Where Laravel keeps the compiled copy of a view file.
     * @param  callable(string): mixed  $remove  Removes a compiled copy, if it is there.
     * @param  callable(string): mixed  $remember  Remembers the version the compiled views now belong to.
     * @return bool True when this call removed the old copies.
     */
    public static function refresh(string $version, ?string $known, iterable $views, callable $compiledPath, callable $remove, callable $remember): bool
    {
        if ($version === '' || $known === $version) {
            return false;
        }
        foreach ($views as $view) {
            $remove($compiledPath($view));
        }
        $remember($version);

        return true;
    }

    /** Every .blade.php file under a folder. @return list<string> */
    public static function files(string $folder): array
    {
        if (! is_dir($folder)) {
            return [];
        }
        $found = [];
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
        foreach ($walk as $file) {
            if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /** The file that remembers the versions the compiled views belong to, among those views. */
    public static function marker(): string
    {
        return rtrim((string) config('view.compiled', storage_path('framework/views')), '/').'/simplify-version';
    }

    /**
     * Whether this request starts PHP's opcache over: only in the web server's PHP (an artisan command has an opcache
     * of its own, if any) and once per new version there. The first request after an update can be an artisan command
     * (the Hub, an update script, optimize:clear): it throws the views away, but resetting its own opcache left the web
     * server running the previous version's classes next to the new views (K-B62).
     *
     * @param  string|null  $known  The version the web server's opcache was last started over for (null: not known).
     */
    public static function resetsOpcache(string $fingerprint, ?string $known, string $sapi): bool
    {
        return $fingerprint !== '' && ! in_array($sapi, ['cli', 'phpdbg'], true) && $known !== $fingerprint;
    }

    /**
     * Simplify's version plus every installed plugin's id and version (its add-ons among them), so an update of any of
     * them counts as a new version (Jeroen 5-11: never again after an update, also for add-ons).
     *
     * @param  list<string>  $manifests  The contents of the plugins' plugin.json files.
     */
    public static function fingerprint(string $version, array $manifests): string
    {
        if ($version === '') {
            return '';
        }
        $plugins = [];
        foreach ($manifests as $manifest) {
            $data = json_decode($manifest, true);
            if (is_array($data) && isset($data['id'])) {
                $plugins[] = $data['id'].'@'.($data['version'] ?? '');
            }
        }
        sort($plugins);

        return $version.'+'.substr(sha1(implode(',', $plugins)), 0, 12);
    }

    /** Runs on boot; never stops the panel from loading. */
    public static function boot(string $version): void
    {
        try {
            $files = app('files');
            $marker = self::marker();
            $compiled = dirname($marker);
            // Every compiled view goes, not only Simplify's: an add-on's pages could be just as old.
            $fingerprint = self::fingerprint($version, array_map(fn (string $path): string => (string) $files->get($path), glob(base_path('plugins/*/plugin.json')) ?: []));
            self::refresh(
                $fingerprint,
                $files->exists($marker) ? trim((string) $files->get($marker)) : null,
                $files->isDirectory($compiled) ? (glob($compiled.'/*.php') ?: []) : [],
                fn (string $path): string => $path,
                fn (string $path) => $files->delete($path),
                fn (string $known) => $files->put($marker, $known),
            );
            // PHP's opcache can keep running the previous version's classes next to the new views (K-B60): the web
            // server's PHP starts it over once per new version, so every class is read again from the new files (K-B62).
            $opcache = $compiled.'/simplify-opcache';
            if (function_exists('opcache_reset') && $files->isDirectory($compiled)
                && self::resetsOpcache($fingerprint, $files->exists($opcache) ? trim((string) $files->get($opcache)) : null, PHP_SAPI)) {
                opcache_reset();
                $files->put($opcache, $fingerprint);
            }
        } catch (Throwable $error) {
            report($error);
        }
    }
}
