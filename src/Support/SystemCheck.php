<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Simplify\Core\Push\WebPush;
use Throwable;

/**
 * What Simplify needs from this server, checked when the setup opens. Nothing blocks the setup: a failed check says
 * what to change, and the setup shows the same list again after a reload.
 */
final class SystemCheck
{
    private const T = 'simplify::setup.checks.';

    private const EXTENSIONS = ['intl', 'mbstring', 'zip', 'curl', 'fileinfo'];

    /** @return list<array{ok: bool, text: string, fix: string, code?: string}> */
    public static function all(): array
    {
        $checks = [
            self::row(str_starts_with(url('/'), 'https://'), __(self::T.'https'), ''),
            self::row(self::keys(), __(self::T.'openssl'), __(self::T.'extension_fix', ['extension' => 'openssl'])),
        ];
        foreach (self::EXTENSIONS as $extension) {
            $replace = ['extension' => $extension];
            $checks[] = self::row(extension_loaded($extension), __(self::T.'extension', $replace), __(self::T.'extension_fix', $replace));
        }
        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $replace = ['directory' => $directory];
            $checks[] = self::row(is_writable(base_path($directory)), __(self::T.'writable', $replace), __(self::T.'writable_fix', $replace));
        }
        $checks[] = self::row(self::cache(), __(self::T.'cache'), __(self::T.'cache_fix'));
        $checks[] = self::row(self::storage(), __(self::T.'storage'), __(self::T.'storage_fix'));
        $checks[] = self::row(! in_array(config('queue.default'), [null, '', 'sync', 'null'], true), __(self::T.'queue'), __(self::T.'queue_fix'));
        $checks[] = [...self::row(! Scheduler::stalled(), __('simplify::push.scheduler'), __('simplify::push.scheduler_admin')), 'code' => Scheduler::cron()];

        return $checks;
    }

    /** @return array{ok: bool, text: string, fix: string} */
    private static function row(bool $ok, string $text, string $fix): array
    {
        return ['ok' => $ok, 'text' => $text, 'fix' => $fix];
    }

    private static function keys(): bool
    {
        try {
            WebPush::createKeys();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function cache(): bool
    {
        try {
            $key = 'simplify.check-'.bin2hex(random_bytes(8));
            Cache::put($key, 'ready', 10);
            $ok = Cache::get($key) === 'ready';
            Cache::forget($key);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    /** Logos and server banners are kept on the local disk. */
    private static function storage(): bool
    {
        try {
            $disk = Storage::disk('local');
            $path = 'simplify/.check-'.bin2hex(random_bytes(8));
            $ok = $disk->put($path, 'ready') && $disk->get($path) === 'ready';
            $disk->delete($path);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}
