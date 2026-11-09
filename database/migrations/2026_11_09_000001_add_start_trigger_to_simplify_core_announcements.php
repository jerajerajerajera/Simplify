<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Send: when a server starts" (Khion 6-11): an announcement that goes out each time one of its servers comes online,
// instead of at a time. start_seen keeps whether each server was running at the last look. Like every Simplify
// migration, it can run again.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_core_announcements')) {
            return;
        }
        foreach (['send_on', 'start_servers', 'start_once', 'start_seen'] as $column) {
            if (Schema::hasColumn('simplify_core_announcements', $column)) {
                continue;
            }
            Schema::table('simplify_core_announcements', function (Blueprint $table) use ($column) {
                match ($column) {
                    'send_on' => $table->string('send_on', 16)->default('time'),
                    'start_servers' => $table->json('start_servers')->nullable(),
                    'start_once' => $table->boolean('start_once')->default(false),
                    'start_seen' => $table->json('start_seen')->nullable(),
                };
            });
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
