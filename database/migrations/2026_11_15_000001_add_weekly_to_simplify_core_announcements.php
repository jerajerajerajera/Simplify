<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Send: on days of the week" (Khion 15-11): the days, the time and its time zone, how long the bar shows, and the turn
// that went out last (weekly_from until weekly_until). Like every Simplify migration, it can run again.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_core_announcements')) {
            return;
        }
        foreach (['weekly_days', 'weekly_at', 'weekly_zone', 'weekly_hours', 'weekly_from', 'weekly_until'] as $column) {
            if (Schema::hasColumn('simplify_core_announcements', $column)) {
                continue;
            }
            Schema::table('simplify_core_announcements', function (Blueprint $table) use ($column) {
                match ($column) {
                    'weekly_days' => $table->json('weekly_days')->nullable(),
                    'weekly_at' => $table->string('weekly_at', 5)->nullable(),
                    'weekly_zone' => $table->string('weekly_zone', 64)->nullable(),
                    'weekly_hours' => $table->unsignedSmallInteger('weekly_hours')->default(24),
                    'weekly_from' => $table->timestamp('weekly_from')->nullable(),
                    'weekly_until' => $table->timestamp('weekly_until')->nullable(),
                };
            });
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
