<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Incidents of the servers on the website: one row from when a server became unreachable until it was back (ended_at
// null while it lasts). Planned: someone asked for the stop. The note is the admin's plain text; message is the id of
// the Discord message that is edited when the incident closes. Rows older than 90 days are removed; deleting a server
// removes its rows.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_embed_incidents')) {
            return;
        }
        Schema::create('simplify_core_embed_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unsignedInteger('started_at');
            $table->unsignedInteger('ended_at')->nullable();
            $table->boolean('planned')->default(false);
            $table->string('note', 300)->nullable();
            $table->string('message', 40)->nullable();
            $table->index(['server_id', 'ended_at']);
            $table->index('started_at');
        });
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
