<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Online servers first" is new and off by default. A panel that already shows servers on a website keeps the order it
// had (online servers on top) until the host turns it off. Runs once: a saved choice is never overwritten.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_core_embed_servers') || ! Schema::hasTable('simplify_core_embed_settings')) {
            return;
        }
        if (! DB::table('simplify_core_embed_servers')->exists()
            || DB::table('simplify_core_embed_settings')->where('key', 'online_first')->exists()) {
            return;
        }
        DB::table('simplify_core_embed_settings')->insert(['key' => 'online_first', 'value' => json_encode(true)]);
    }

    public function down(): void {}
};
