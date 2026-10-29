<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simplify_push_devices')) {
            Schema::create('simplify_push_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->char('device', 16)->unique();
                $table->text('endpoint');
                $table->string('p256dh', 128);
                $table->string('auth', 64);
                $table->string('label', 60);
                $table->json('received')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_push_devices');
    }
};
