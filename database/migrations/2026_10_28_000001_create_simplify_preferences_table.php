<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_preferences')) {
            Schema::create('simplify_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('key', 64);
                $table->json('value');
                $table->timestamps();

                $table->unique(['user_id', 'key']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
