<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * PelicanSimplify 0.7.0 styled the panel's name (appearance.json, brand.*). A panel keeps that style; a choice already
 * made in Simplify stays, and a value Simplify does not accept is skipped. The old file is only read.
 */
return new class extends Migration
{
    /**
     * 0.7.0's key => Simplify's setting and the values it takes. The rules live here and not in PanelSettings: while
     * the plugin installs, the class that loads can still be an older version's, without these settings.
     */
    private const KEYS = [
        'font' => ['brand_font', ['required', 'in:inter,system,rounded,serif,mono,monocraft,condensed,display']],
        'weight' => ['brand_weight', ['required', 'integer', 'in:400,500,600,700,800,900']],
        'color_style' => ['brand_colour', ['required', 'in:solid,accent,gradient']],
        'color1' => ['brand_colour1', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
        'color2' => ['brand_colour2', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
        'spacing' => ['brand_spacing', ['required', 'integer', 'min:-5', 'max:30']],
        'uppercase' => ['brand_uppercase', ['required', 'boolean']],
        'logo_text' => ['brand_show', ['required', 'in:logo,text,both']],
    ];

    public function up(): void
    {
        $raw = Storage::disk('local')->get('simplify/appearance.json');
        $brand = is_string($raw) && $raw !== '' ? (json_decode($raw, true)['brand'] ?? null) : null;
        if (! is_array($brand)) {
            return;
        }
        foreach (self::KEYS as $old => [$key, $rules]) {
            $value = $brand[$old] ?? null;
            // 0.7.0's "text beside the logo" switch: on is Simplify's logo and name, off leaves the default.
            if ($old === 'logo_text') {
                $value = $value === true ? 'both' : null;
            }
            if ($value === null || $value === '' || DB::table('simplify_settings')->where('key', $key)->exists()
                || Validator::make(['value' => $value], ['value' => $rules])->fails()) {
                continue;
            }
            DB::table('simplify_settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
