<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use Illuminate\Support\Facades\DB;
use Simplify\Core\Support\SafeCache;

/**
 * How the list looks on other websites. Websites can still change each option with data-* attributes or the query.
 */
final class EmbedOptions
{
    public const TABLE = 'simplify_core_embed_settings';

    /** The Simplify blue, used when no colour is chosen. */
    public const ACCENT = '#5b8fd0';

    public const DEFAULTS = [
        'style' => 'auto',
        'corners' => 'round',
        'accent' => '',
        'players' => true,
        'modpack' => true,
        'join' => true,
        // How long each server is online or offline, and the last 30 days (Uptime).
        'uptime' => true,
        // Online servers before offline ones; off keeps the order of the list.
        'online_first' => false,
        // The status page on an address of its own, such as status.example.com (StatusAddress); empty: none.
        'status_host' => '',
        // An uploaded logo per egg id (EggLogo): a file in EggLogo::DIRECTORY.
        'logos' => [],
        // The status page's own look (/status and its own address); empty follows the list's Look or the default.
        'page_title' => '',
        // panel (the logo under Settings › Branding), upload (page_logo_file) or none.
        'page_logo' => 'panel',
        'page_logo_file' => '',
        // Plain text under the title.
        'page_intro' => '',
        // '' = the same as style above, or auto, light, dark.
        'page_style' => '',
        'page_accent' => '',
        // The admin's own CSS, cleaned on save (StatusCss).
        'page_css' => '',
    ];

    public const PAGE_TITLE = 80;

    public const PAGE_INTRO = 300;

    /** Options only the panel needs; they never reach the public list. */
    public const PRIVATE = ['logos', 'status_host'];

    /** @return array{style: string, corners: string, accent: string, players: bool, modpack: bool, join: bool, uptime: bool, online_first: bool, status_host: string, logos: array<int, string>} */
    public static function all(): array
    {
        $saved = [];
        foreach (DB::table(self::TABLE)->get() as $row) {
            $saved[$row->key] = json_decode((string) $row->value, true);
        }

        return self::normalize($saved);
    }

    /** @param  array<string, mixed>  $input */
    public static function save(array $input): void
    {
        foreach (self::normalize($input) as $key => $value) {
            DB::table(self::TABLE)->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        }
        SafeCache::forget(ServerList::CACHE);
    }

    /**
     * Only known options with valid values, anything else falls back to its default.
     *
     * @param  array<string, mixed>  $input
     * @return array{style: string, corners: string, accent: string, players: bool, modpack: bool, join: bool, uptime: bool, online_first: bool, status_host: string, logos: array<int, string>, page_title: string, page_logo: string, page_logo_file: string, page_intro: string, page_style: string, page_accent: string, page_css: string}
     */
    public static function normalize(array $input): array
    {
        $out = self::DEFAULTS;
        if (in_array($input['style'] ?? null, ['auto', 'light', 'dark'], true)) {
            $out['style'] = $input['style'];
        }
        if (in_array($input['corners'] ?? null, ['round', 'square'], true)) {
            $out['corners'] = $input['corners'];
        }
        if (is_string($input['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['accent'])) {
            $out['accent'] = strtolower($input['accent']);
        }
        foreach (['players', 'modpack', 'join', 'uptime', 'online_first'] as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = (bool) $input[$key];
            }
        }
        $host = is_string($input['status_host'] ?? null) ? strtolower(trim($input['status_host'])) : '';
        if ($host !== '' && preg_match(StatusAddress::HOST, $host) === 1) {
            $out['status_host'] = $host;
        }
        foreach (is_array($input['logos'] ?? null) ? $input['logos'] : [] as $egg => $file) {
            if (is_numeric($egg) && (int) $egg > 0 && is_string($file) && EggLogo::validUpload($file)) {
                $out['logos'][(int) $egg] = $file;
            }
        }
        // Plain text: tags and control characters go, the intro keeps its line breaks.
        $text = fn (mixed $value, int $max, bool $lines) => mb_substr(trim((string) preg_replace($lines ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', strip_tags(is_string($value) ? str_replace("\r\n", "\n", $value) : ''))), 0, $max);
        $out['page_title'] = $text($input['page_title'] ?? '', self::PAGE_TITLE, false);
        $out['page_intro'] = $text($input['page_intro'] ?? '', self::PAGE_INTRO, true);
        if (in_array($input['page_logo'] ?? null, ['panel', 'upload', 'none'], true)) {
            $out['page_logo'] = $input['page_logo'];
        }
        if (is_string($input['page_logo_file'] ?? null) && EggLogo::validUpload($input['page_logo_file'])) {
            $out['page_logo_file'] = $input['page_logo_file'];
        }
        if (in_array($input['page_style'] ?? null, ['auto', 'light', 'dark'], true)) {
            $out['page_style'] = $input['page_style'];
        }
        if (is_string($input['page_accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['page_accent'])) {
            $out['page_accent'] = strtolower($input['page_accent']);
        }
        $out['page_css'] = is_string($input['page_css'] ?? null) ? StatusCss::clean($input['page_css'])['css'] : '';

        return $out;
    }
}
