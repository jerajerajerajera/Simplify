<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * PelicanSimplify 0.7.0 had friends mode on unless it was switched off (features.json, friends_mode). A panel that ran
 * it with friends mode on keeps it on; a choice already made in Simplify stays. The old file is only read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $raw = Storage::disk('local')->get('simplify/features.json');
        $saved = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (! is_array($saved) || ($saved['friends_mode'] ?? true) !== true || DB::table('simplify_settings')->where('key', 'friends_mode')->exists()) {
            return;
        }
        DB::table('simplify_settings')->insert(['key' => 'friends_mode', 'value' => json_encode(true), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void {}
};
