<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PelicanSimplify 0.6 and 0.7 read the Cloudflare token and zone ID from .env (CLOUDFLARE_API_TOKEN and
 * CLOUDFLARE_ZONE_ID), not from subdomains.json, so the first import left them behind and subdomains stopped.
 * Takes them over once, encrypted, unless Simplify already has its own. .env is only read, never changed.
 */
return new class extends Migration
{
    private const TABLE = 'simplify_core_subdomain_settings';

    public function up(): void
    {
        $token = $this->key('CLOUDFLARE_API_TOKEN');
        $zone = strtolower($this->key('CLOUDFLARE_ZONE_ID'));
        if ($token !== '' && $this->missing('token')) {
            DB::table(self::TABLE)->updateOrInsert(['key' => 'token'], ['value' => encrypt($token)]);
        }
        if (preg_match('/^[a-f0-9]{32}$/', $zone) && $this->missing('zone_id')) {
            DB::table(self::TABLE)->updateOrInsert(['key' => 'zone_id'], ['value' => $zone]);
        }
    }

    public function down(): void {}

    private function missing(string $key): bool
    {
        return (string) DB::table(self::TABLE)->where('key', $key)->value('value') === '';
    }

    /** From the environment, or from .env itself when the configuration is cached (as PelicanSimplify did). */
    private function key(string $name): string
    {
        $value = trim((string) env($name, ''));
        $path = base_path('.env');
        if ($value === '' && is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (preg_match('/^\s*'.$name.'\s*=\s*"?([^"\s#]*)"?/', $line, $match)) {
                    $value = $match[1];
                }
            }
        }

        return $value;
    }
};
