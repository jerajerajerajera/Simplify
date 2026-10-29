<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_server_looks')) {
            Schema::create('simplify_server_looks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('server_id')->unique();
                $table->char('color', 7)->nullable();
                $table->string('banner', 160)->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_server_looks');
    }
};
