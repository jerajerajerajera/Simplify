<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Uptime of the servers on the website: one row per stretch of time a server was online or offline. The scheduler
// (simplify:embed-uptime, every minute) lengthens the open stretch or starts a new one; stretches older than 90 days
// are removed. Deleting a server removes its rows.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_embed_uptime')) {
            return;
        }
        Schema::create('simplify_core_embed_uptime', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->boolean('online');
            $table->unsignedInteger('started_at');
            $table->unsignedInteger('seen_at');
            $table->index(['server_id', 'seen_at']);
        });
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
