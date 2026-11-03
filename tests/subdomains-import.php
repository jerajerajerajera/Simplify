<?php

// The update from PelicanSimplify 0.7.0 keeps a working subdomain setup: 0.7.0 kept the domain and ports in
// storage/app/simplify/subdomains.json, but the Cloudflare token and zone ID in .env (CLOUDFLARE_API_TOKEN,
// CLOUDFLARE_ZONE_ID). Runs every subdomain import migration against such a panel, with Laravel faked in memory.
declare(strict_types=1);

namespace Illuminate\Database\Migrations {
    abstract class Migration {}
}

namespace Illuminate\Support\Facades {
    final class Storage
    {
        /** @var array<string, string> */
        public static array $files = [];

        public static function disk(string $name): self
        {
            return new self;
        }

        public function get(string $path): ?string
        {
            return self::$files[$path] ?? null;
        }

        /** @return list<string> */
        public function files(string $dir): array
        {
            return array_values(array_filter(array_keys(self::$files), fn ($p) => dirname($p) === $dir));
        }
    }

    final class DB
    {
        /** @var array<string, list<array<string, mixed>>> */
        public static array $tables = [];

        public static function table(string $name): \FakeQuery
        {
            return new \FakeQuery($name);
        }
    }
}

namespace {
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Storage;

    final class FakeQuery
    {
        /** @var array<string, mixed> */
        private array $where = [];

        public function __construct(private string $table) {}

        public function where(string $column, mixed $value): self
        {
            $this->where[$column] = $value;

            return $this;
        }

        /** @return list<array<string, mixed>> */
        private function rows(): array
        {
            return array_values(array_filter(
                DB::$tables[$this->table] ?? [],
                fn ($row) => array_intersect_assoc(array_map('strval', $this->where), array_map('strval', $row)) === array_map('strval', $this->where),
            ));
        }

        public function exists(): bool
        {
            return $this->rows() !== [];
        }

        public function doesntExist(): bool
        {
            return ! $this->exists();
        }

        public function value(string $column): mixed
        {
            return $this->rows()[0][$column] ?? null;
        }

        public function insert(array $row): bool
        {
            DB::$tables[$this->table][] = $row;

            return true;
        }

        public function updateOrInsert(array $match, array $values): bool
        {
            $rows = &DB::$tables[$this->table];
            foreach ($rows ?? [] as $i => $row) {
                if (array_intersect_assoc($match, $row) === $match) {
                    $rows[$i] = $values + $row;

                    return true;
                }
            }
            $rows[] = $match + $values;

            return true;
        }
    }

    /** @var array<string, string> what env() returns; empty as on a panel with a cached configuration */
    $env = [];
    function env(string $key, mixed $default = null): mixed
    {
        return $GLOBALS['env'][$key] ?? $default;
    }
    function base_path(string $path = ''): string
    {
        return $GLOBALS['dotenvPath'];
    }
    function encrypt(string $value): string
    {
        return 'enc:'.$value;
    }
    function now(): string
    {
        return '2026-11-03 12:00:00';
    }

    $failed = false;
    function check(bool $ok, string $what): void
    {
        global $failed;
        echo ($ok ? 'PASS ' : 'FAIL ').$what."\n";
        $failed = $failed || ! $ok;
    }

    $migrations = glob(__DIR__.'/../database/migrations/*import_pelicansimplify_subdomains*.php') ?: [];
    sort($migrations);
    $setting = fn (string $key) => DB::table('simplify_core_subdomain_settings')->where('key', $key)->value('value');

    /** A 0.7.0 panel: the settings file, one server's subdomain, and the Cloudflare keys in .env (tests/fixtures/env) or the environment. */
    $panel = function (string $dotenv, array $environment = [], array $existing = []) use ($migrations) {
        Storage::$files = [
            'simplify/subdomains.json' => json_encode(['domain' => 'example.com', 'target' => 'play.example.com', 'port_min' => 25565, 'port_max' => 25600]),
            'simplify/subdomains/0b5a1f0e-8a8e-4f7e-9f5e-1c2d3e4f5a6b.json' => json_encode(['name' => 'oak', 'port' => 25565]),
        ];
        DB::$tables = ['simplify_core_subdomain_settings' => $existing];
        $GLOBALS['env'] = $environment;
        $GLOBALS['dotenvPath'] = __DIR__.'/fixtures/env/'.$dotenv.'.env';
        foreach ($migrations as $file) {
            (require $file)->up();
        }
    };

    $zone = str_repeat('a', 32);
    $panel('070');
    check($setting('domain') === 'example.com', 'the domain from subdomains.json is kept');
    check($setting('token') === 'enc:cf-token-070', 'the token from .env is taken over, encrypted');
    check($setting('zone_id') === $zone, 'the zone ID from .env is taken over');
    check(count(DB::$tables['simplify_core_subdomains'] ?? []) === 1, 'the server\'s subdomain is kept');

    $panel('none', ['CLOUDFLARE_API_TOKEN' => 'from-env', 'CLOUDFLARE_ZONE_ID' => $zone]);
    check($setting('token') === 'enc:from-env' && $setting('zone_id') === $zone, 'the token and zone ID from the environment are taken over');

    $panel('bad-zone', [], [['key' => 'token', 'value' => 'enc:new'], ['key' => 'domain', 'value' => 'example.com']]);
    check($setting('token') === 'enc:new', 'a token saved in Simplify is never replaced');
    check($setting('zone_id') === null, 'a zone ID that is not 32 hex characters is left out');

    $panel('none');
    check($setting('token') === null && $setting('zone_id') === null, 'without Cloudflare keys nothing is added');

    exit($failed ? 1 : 0);
}
