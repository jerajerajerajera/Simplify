<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "When a server starts" (Khion 22-11): "Once per day" sends only the first start of each chosen day per server
// (start_sent keeps that day per server), and the optional hours limit the starts that count. Like every Simplify
// migration, it can run again.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_core_announcements')) {
            return;
        }
        foreach (['start_daily', 'start_sent', 'start_from', 'start_until'] as $column) {
            if (Schema::hasColumn('simplify_core_announcements', $column)) {
                continue;
            }
            Schema::table('simplify_core_announcements', function (Blueprint $table) use ($column) {
                match ($column) {
                    'start_daily' => $table->boolean('start_daily')->default(false),
                    'start_sent' => $table->json('start_sent')->nullable(),
                    'start_from' => $table->string('start_from', 5)->nullable(),
                    'start_until' => $table->string('start_until', 5)->nullable(),
                };
            });
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
