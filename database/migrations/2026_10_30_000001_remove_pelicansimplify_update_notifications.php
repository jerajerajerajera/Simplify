<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PelicanSimplify's own updater left "Plugin updated" and ":name :version is available" notifications in the bell,
 * with a button to its plugin page (simplify-plugins) that no longer exists. Updates go through Pelican now, so
 * these leave the bell. Other notifications stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }
        DB::table('notifications')
            ->where(fn ($query) => $query->where('data', 'like', '%simplify-plugins%')->orWhere('data', 'like', '%simplify_notes%'))
            ->delete();
    }

    public function down(): void {}
};
