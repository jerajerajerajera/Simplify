<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Simplify\Core\Models\Setting;
use Throwable;

/**
 * Panel-wide settings. Every key has a default and a validation rule here, so a missing or broken value never
 * reaches a page. Read on every request, so the whole set is cached until the next save.
 */
final class PanelSettings
{
    private const CACHE_KEY = 'simplify.settings';

    /** The checked settings for this request, kept in the container so a new request (or test app) starts fresh. */
    private const MEMO = 'simplify.settings.checked';

    /** An https address or a mailto: address; Laravel's url rule does not accept mailto:. */
    public const SUPPORT_URL = '#^(https://[^\s<>"]+|mailto:[^\s@<>"]+@[^\s@<>"]+)$#';

    /** key => [default, validation rules] */
    public const FIELDS = [
        'accent' => [null, ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
        'server_colours' => [true, ['required', 'boolean']],
        'setup_done' => [false, ['required', 'boolean']],
        'start_page' => ['overview', ['required', 'in:overview,pelican']],
        'support_url' => [null, ['nullable', 'max:255', 'regex:'.self::SUPPORT_URL]],
        'push' => [true, ['required', 'boolean']],
        'overview_seconds' => [15, ['required', 'integer', 'min:5', 'max:300']],
        'brand_name' => [null, ['nullable', 'string', 'max:40']],
        'pelican_suffix' => [true, ['required', 'boolean']],
        'brand_logo' => [null, ['nullable', 'string', 'regex:#^'.Brand::DIRECTORY.'/'.Brand::FILE_PATTERN.'$#']],
        'app_icon' => [null, ['nullable', 'string', 'regex:#^'.Brand::DIRECTORY.'/'.Brand::FILE_PATTERN.'$#']],
        'app_badge' => [null, ['nullable', 'string', 'regex:#^'.Brand::DIRECTORY.'/'.Brand::FILE_PATTERN.'$#']],
        'logo_height' => [32, ['required', 'integer', 'min:16', 'max:64']],
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (app()->bound(self::MEMO)) {
            return app(self::MEMO);
        }
        try {
            $saved = SafeCache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            // Before the migration has run, for example while the plugin installs.
            $saved = [];
        }
        $settings = [];
        foreach (self::FIELDS as $key => [$default, $rules]) {
            $value = $saved[$key] ?? $default;
            $settings[$key] = Validator::make(['value' => $value], ['value' => $rules])->passes() ? $value : $default;
        }
        app()->instance(self::MEMO, $settings);

        return $settings;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** @param array<string, mixed> $values only known keys are saved; throws a ValidationException for bad values */
    public static function save(array $values): void
    {
        $values = array_intersect_key($values, self::FIELDS);
        $rules = array_map(fn (array $field) => $field[1], array_intersect_key(self::FIELDS, $values));
        Validator::make($values, $rules)->validate();
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        SafeCache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::MEMO);
    }

    /**
     * Saves what a settings form or the setup wizard sent: empty text becomes null, and an uploaded image that was
     * replaced or removed is deleted, since nothing else uses it.
     *
     * @param  array<string, mixed>  $state
     */
    public static function saveForm(array $state): void
    {
        foreach (['accent', 'brand_name', 'support_url'] as $text) {
            if (array_key_exists($text, $state)) {
                $state[$text] = trim((string) $state[$text]) ?: null;
            }
        }
        if (isset($state['accent'])) {
            $state['accent'] = strtolower($state['accent']);
        }
        $previous = [];
        foreach (['brand_logo', 'app_icon', 'app_badge'] as $file) {
            if (array_key_exists($file, $state)) {
                $state[$file] = $state[$file] ?: null;
                $previous[$file] = self::get($file);
            }
        }
        self::save($state);
        foreach ($previous as $file => $old) {
            if ($old !== null && $old !== $state[$file]) {
                Storage::disk('local')->delete($old);
            }
        }
    }
}
