<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Simplify\Core\Support\PanelSettings;

/**
 * PelicanSimplify 0.7.0 styled the panel's name (appearance.json, brand.*). A panel keeps that style; a choice already
 * made in Simplify stays, and a value Simplify does not accept is skipped. The old file is only read.
 */
return new class extends Migration
{
    private const KEYS = [
        'font' => 'brand_font', 'weight' => 'brand_weight', 'color_style' => 'brand_colour', 'color1' => 'brand_colour1',
        'color2' => 'brand_colour2', 'spacing' => 'brand_spacing', 'uppercase' => 'brand_uppercase', 'logo_text' => 'brand_show',
    ];

    public function up(): void
    {
        $raw = Storage::disk('local')->get('simplify/appearance.json');
        $brand = is_string($raw) && $raw !== '' ? (json_decode($raw, true)['brand'] ?? null) : null;
        if (! is_array($brand)) {
            return;
        }
        foreach (self::KEYS as $old => $key) {
            $value = $brand[$old] ?? null;
            // 0.7.0's "text beside the logo" switch: on is Simplify's logo and name, off leaves the default.
            if ($old === 'logo_text') {
                $value = $value === true ? 'both' : null;
            }
            if ($value === null || $value === '' || ! isset(PanelSettings::FIELDS[$key]) || DB::table('simplify_settings')->where('key', $key)->exists()
                || Validator::make(['value' => $value], ['value' => PanelSettings::FIELDS[$key][1]])->fails()) {
                continue;
            }
            DB::table('simplify_settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
