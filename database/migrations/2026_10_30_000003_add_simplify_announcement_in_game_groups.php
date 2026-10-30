<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Also send in game" to the servers in some of the author's server groups: the group ids. Runs again without harm.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_announcements') && ! Schema::hasColumn('simplify_core_announcements', 'in_game_groups')) {
            Schema::table('simplify_core_announcements', function (Blueprint $table) {
                $table->json('in_game_groups')->nullable()->after('in_game_servers');
            });
        }
    }

    public function down(): void {}
};
