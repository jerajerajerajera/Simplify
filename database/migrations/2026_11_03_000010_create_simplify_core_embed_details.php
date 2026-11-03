<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What a server shows on the website besides Pelican's own data: one row per server, only once someone filled in
// one of its fields. Deleting a server removes its row.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_embed_details')) {
            return;
        }
        Schema::create('simplify_core_embed_details', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->primary();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('banner', 200)->nullable();
            $table->string('download', 500)->nullable();
            $table->string('address2', 120)->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_core_embed_details');
    }
};
