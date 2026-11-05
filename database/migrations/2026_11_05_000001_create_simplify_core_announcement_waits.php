<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Announcement tasks of a schedule that wait for their server to come online ("When the server is online"). The
// scheduler (simplify:announcements, every minute) looks at them, so waiting needs no queue worker. Deleting a
// server removes its rows.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_announcement_waits')) {
            return;
        }
        Schema::create('simplify_core_announcement_waits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->text('payload');
            $table->string('schedule', 191)->nullable();
            $table->unsignedInteger('started_at');
        });
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
