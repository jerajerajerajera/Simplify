<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Announcements, formerly the separate plugin Simplify Announcements. Its own table names are kept free, because
// uninstalling that plugin drops them. Like every Simplify migration, it keeps a table that is already there, so an
// install that stopped halfway can run again.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_core_announcements')) {
            Schema::create('simplify_core_announcements', function (Blueprint $table) {
                $table->id();
                $table->string('category', 16)->default('info');
                $table->string('title', 120);
                $table->text('body')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('audience', 16)->default('everyone');
                $table->string('button_label', 40)->nullable();
                $table->string('button_url', 2048)->nullable();
                $table->boolean('in_bell')->default(false);
                // When it was put in everyone's bell; it is put there once.
                $table->timestamp('in_bell_at')->nullable();
                $table->boolean('push')->default(false);
                // How many devices it was sent to as an app notification, added up.
                $table->unsignedInteger('pushed_to')->default(0);
                $table->boolean('in_game')->default(false);
                // Server ids; empty: all servers.
                $table->json('in_game_servers')->nullable();
                // Minutes between two posts while it shows; 0: only once.
                $table->unsignedSmallInteger('in_game_repeat')->default(0);
                $table->timestamp('in_game_at')->nullable();
                // {"sent": n, "offline": n, "unsupported": n, "failed": n} of the last post.
                $table->json('in_game_result')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['starts_at', 'ends_at']);
            });
        }

        // One row per account that unpinned an announcement: its bar is gone for that account.
        if (! Schema::hasTable('simplify_core_announcement_dismissals')) {
            Schema::create('simplify_core_announcement_dismissals', function (Blueprint $table) {
                $table->foreignId('announcement_id')->constrained('simplify_core_announcements')->cascadeOnDelete();
                $table->unsignedInteger('user_id');
                $table->timestamp('created_at')->nullable();

                $table->primary(['announcement_id', 'user_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // A root admin's own chat command per egg. "-" skips that game.
        if (! Schema::hasTable('simplify_core_announcement_commands')) {
            Schema::create('simplify_core_announcement_commands', function (Blueprint $table) {
                $table->unsignedInteger('egg_id')->primary();
                $table->string('command', 200);
                $table->timestamps();

                $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_core_announcement_commands');
        Schema::dropIfExists('simplify_core_announcement_dismissals');
        Schema::dropIfExists('simplify_core_announcements');
    }
};
