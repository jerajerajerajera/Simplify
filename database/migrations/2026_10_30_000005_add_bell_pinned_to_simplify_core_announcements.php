<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Pin to everyone's notifications" (as in PelicanSimplify 0.7.0): the bell item stays on top until the announcement
// ends. Like every Simplify migration, it can run again.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_announcements') && ! Schema::hasColumn('simplify_core_announcements', 'bell_pinned')) {
            Schema::table('simplify_core_announcements', function (Blueprint $table) {
                $table->boolean('bell_pinned')->default(false)->after('in_bell_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('simplify_core_announcements', 'bell_pinned')) {
            Schema::table('simplify_core_announcements', fn (Blueprint $table) => $table->dropColumn('bell_pinned'));
        }
    }
};
