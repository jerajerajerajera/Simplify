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

    /** The file that remembers the version the compiled views belong to, among those views. */
    public static function marker(): string
    {
        return rtrim((string) config('view.compiled', storage_path('framework/views')), '/').'/simplify-version';
    }

    /** Runs on boot; never stops the panel from loading. */
    public static function boot(string $version): void
    {
        try {
            $compiler = app('blade.compiler');
            $files = app('files');
            $root = dirname(__DIR__, 2).'/resources';
            $marker = self::marker();
            self::refresh(
                $version,
                $files->exists($marker) ? trim((string) $files->get($marker)) : null,
                array_merge(self::files($root.'/views'), self::files($root.'/overrides')),
                fn (string $path): string => (string) $compiler->getCompiledPath($path),
                fn (string $path) => $files->delete($path),
                fn (string $known) => $files->put($marker, $known),
            );
        } catch (Throwable $error) {
            report($error);
        }
    }
}
