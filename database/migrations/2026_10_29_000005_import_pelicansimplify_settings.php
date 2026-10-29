<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Takes over what PelicanSimplify 0.6 and 0.7 kept in storage/app/simplify: the announcements, the panel's accent colour,
 * name and logo, and the website list (its look and the servers on it). Each part is only filled while it is still
 * empty, so running it again, or on a panel that set Simplify up already, changes nothing. The old files are only read.
 */
return new class extends Migration
{
    /** The neutral colour PelicanSimplify started with: not a choice, so Pelican's own colour stays. */
    private const OLD_ACCENT = '#878787';

    public function up(): void
    {
        $this->announcements();
        $this->looks();
        $this->website();
    }

    public function down(): void {}

    private function announcements(): void
    {
        $rows = $this->json('simplify/announcements.json');
        if ($rows === [] || DB::table('simplify_core_announcements')->exists()) {
            return;
        }
        $sent = $this->json('simplify/announcements-ingame.json');
        foreach (array_reverse($rows) as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null) || ! is_string($row['title'] ?? null) || trim($row['title']) === '') {
                continue;
            }
            $button = is_array($row['button'] ?? null) ? $row['button'] : [];
            $url = match (true) {
                is_string($button['url'] ?? null) => $button['url'],
                is_string($button['server'] ?? null) => '/server/'.$button['server'],
                default => null,
            };
            $game = is_array($row['ingame'] ?? null) ? $row['ingame'] : [];
            $servers = ($game['targets'] ?? 'all') === 'pick' ? $this->serverIds($game['servers'] ?? []) : [];
            $lastPost = $sent[$row['id']]['at'] ?? null;
            DB::table('simplify_core_announcements')->insert([
                'category' => in_array($row['category'] ?? null, ['info', 'warning', 'success'], true) ? $row['category'] : 'info',
                'title' => mb_substr(trim($row['title']), 0, 120),
                'body' => is_string($row['text'] ?? null) && $row['text'] !== '' ? $row['text'] : null,
                'starts_at' => $this->time($row['starts_at'] ?? null),
                'ends_at' => $this->time($row['ends_at'] ?? null),
                'audience' => ($row['audience'] ?? null) === 'admins' ? 'admins' : 'everyone',
                'button_label' => $url === null ? null : (mb_substr((string) ($button['label'] ?? ''), 0, 40) ?: null),
                'button_url' => $url === null ? null : mb_substr($url, 0, 2048),
                'in_bell' => ($row['pinned'] ?? false) === true,
                // Already sent as an app notification: it is not sent again.
                'push' => ($row['push'] ?? false) === true && ! is_int($row['pushed_at'] ?? null),
                'pushed_to' => is_int($row['pushed_to'] ?? null) ? max(0, $row['pushed_to']) : 0,
                'in_game' => ($game['enabled'] ?? false) === true,
                'in_game_servers' => $servers === [] ? null : json_encode($servers),
                'in_game_repeat' => in_array((int) ($game['repeat'] ?? 0), [0, 5, 10, 15, 30, 60, 180], true) ? (int) ($game['repeat'] ?? 0) : 0,
                // Posted in game already: the next post waits for its repeat.
                'in_game_at' => $this->time(is_int($lastPost) ? $lastPost : null),
                'created_at' => $this->time($row['created_at'] ?? null) ?? now(),
                'updated_at' => $this->time($row['updated_at'] ?? null) ?? now(),
            ]);
        }
    }

    private function looks(): void
    {
        $saved = $this->json('simplify/appearance.json');
        $brand = is_array($saved['brand'] ?? null) ? $saved['brand'] : [];
        $accent = is_string($saved['accent_color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $saved['accent_color']) ? strtolower($saved['accent_color']) : null;
        $name = is_string($brand['text'] ?? null) ? mb_substr(trim($brand['text']), 0, 40) : '';
        $logo = is_string($brand['logo'] ?? null) && preg_match('/^logo-[a-f0-9]{16}\.(png|jpg|webp)$/', $brand['logo'])
            && Storage::disk('local')->exists('simplify/branding/'.$brand['logo']) ? 'simplify/branding/'.$brand['logo'] : null;
        $height = is_numeric($brand['logo_height'] ?? null) ? min(64, max(16, (int) $brand['logo_height'])) : null;

        $this->setting('accent', $accent !== null && $accent !== self::OLD_ACCENT ? $accent : null);
        $this->setting('brand_name', $name !== '' ? $name : null);
        $this->setting('brand_logo', ($brand['mode'] ?? 'text') === 'logo' ? $logo : null);
        $this->setting('logo_height', $logo !== null ? $height : null);
        $this->setting('server_colours', ($saved['individual_colors'] ?? true) === false ? false : null);
    }

    private function website(): void
    {
        $options = $this->json('simplify/website-embed.json');
        if ($options !== [] && DB::table('simplify_core_embed_settings')->doesntExist()) {
            foreach (['style' => ['auto', 'light', 'dark'], 'corners' => ['round', 'square']] as $key => $allowed) {
                if (in_array($options[$key] ?? null, $allowed, true)) {
                    DB::table('simplify_core_embed_settings')->insert(['key' => $key, 'value' => json_encode($options[$key])]);
                }
            }
            if (is_string($options['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $options['accent'])) {
                DB::table('simplify_core_embed_settings')->insert(['key' => 'accent', 'value' => json_encode(strtolower($options['accent']))]);
            }
            foreach (['players', 'modpack', 'join'] as $key) {
                if (is_bool($options[$key] ?? null)) {
                    DB::table('simplify_core_embed_settings')->insert(['key' => $key, 'value' => json_encode($options[$key])]);
                }
            }
        }

        if (DB::table('simplify_core_embed_servers')->exists()) {
            return;
        }
        $shown = [];
        $disk = Storage::disk('local');
        // The older folder first, so the newer file of the same server wins.
        foreach (['ssc/website-visibility', 'simplify/server-metadata'] as $folder) {
            foreach ($disk->files($folder) as $path) {
                $uuid = pathinfo($path, PATHINFO_FILENAME);
                $data = $this->json($path);
                if (preg_match('/^[a-f0-9-]{36}$/i', $uuid) && array_key_exists('show_on_website', $data)) {
                    $shown[strtolower($uuid)] = $data['show_on_website'] === true;
                }
            }
        }
        $uuids = array_keys(array_filter($shown));
        foreach (array_chunk($uuids, 200) as $chunk) {
            foreach (DB::table('servers')->whereIn('uuid', $chunk)->pluck('id') as $id) {
                DB::table('simplify_core_embed_servers')->insert(['server_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** Only a setting that was never saved, so a choice made in Simplify itself always stays. */
    private function setting(string $key, mixed $value): void
    {
        if ($value !== null && DB::table('simplify_settings')->where('key', $key)->doesntExist()) {
            DB::table('simplify_settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * PelicanSimplify named servers by their short or full uuid.
     *
     * @return list<int>
     */
    private function serverIds(mixed $names): array
    {
        $names = array_values(array_filter(is_array($names) ? $names : [], fn ($name) => is_string($name) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $name)));
        if ($names === []) {
            return [];
        }

        return DB::table('servers')->whereIn('uuid', $names)->orWhereIn('uuid_short', $names)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function time(mixed $value): ?string
    {
        return is_int($value) && $value > 0 ? date('Y-m-d H:i:s', $value) : null;
    }

    /** @return array<mixed> */
    private function json(string $path): array
    {
        $raw = Storage::disk('local')->get($path);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }
};
