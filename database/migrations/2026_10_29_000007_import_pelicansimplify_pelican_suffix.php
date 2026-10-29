<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * PelicanSimplify 0.7.0 could hide "× Pelican" after the panel's name (appearance.json, brand.suffix). A panel that had
 * it off keeps it off; a choice already made in Simplify stays. The old file is only read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $raw = Storage::disk('local')->get('simplify/appearance.json');
        $saved = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (! is_array($saved) || ($saved['brand']['suffix'] ?? true) !== false || DB::table('simplify_settings')->where('key', 'pelican_suffix')->exists()) {
            return;
        }
        DB::table('simplify_settings')->insert(['key' => 'pelican_suffix', 'value' => json_encode(false), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void {}
};
