<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_settings')) {
            Schema::create('simplify_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
