<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('simplify_preferences');
    }
};
