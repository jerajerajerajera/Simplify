<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Illuminate\Support\Facades\Cache;
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

    /** key => [default, validation rules] */
    public const FIELDS = [
        'accent' => [null, ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']],
        'push' => [true, ['required', 'boolean']],
        'overview_seconds' => [15, ['required', 'integer', 'min:5', 'max:300']],
        'brand_name' => [null, ['nullable', 'string', 'max:40']],
        'brand_logo' => [null, ['nullable', 'string', 'regex:#^'.Brand::DIRECTORY.'/'.Brand::FILE_PATTERN.'$#']],
        'app_icon' => [null, ['nullable', 'string', 'regex:#^'.Brand::DIRECTORY.'/'.Brand::FILE_PATTERN.'$#']],
        'logo_height' => [32, ['required', 'integer', 'min:16', 'max:64']],
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        try {
            $saved = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            // Before the migration has run, for example while the plugin installs.
            $saved = [];
        }
        $settings = [];
        foreach (self::FIELDS as $key => [$default, $rules]) {
            $value = $saved[$key] ?? $default;
            $settings[$key] = Validator::make(['value' => $value], ['value' => $rules])->passes() ? $value : $default;
        }

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
        Cache::forget(self::CACHE_KEY);
    }
}
