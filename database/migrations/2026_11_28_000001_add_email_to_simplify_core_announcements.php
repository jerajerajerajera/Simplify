<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Announcements by email (VoiceCrack93, Jeroen 28-11): "email" sends one once, when it starts, through the panel's own
// mail settings; email_total and email_sent count how far it got, email_failed says the mail server refused. Each
// account it went to gets a row in simplify_core_announcement_mails, so nobody gets it twice and a repeating one is
// emailed the first time only. Like every Simplify migration, it can run again.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simplify_core_announcements')) {
            foreach (['email', 'email_total', 'email_sent', 'email_failed'] as $column) {
                if (Schema::hasColumn('simplify_core_announcements', $column)) {
                    continue;
                }
                Schema::table('simplify_core_announcements', function (Blueprint $table) use ($column) {
                    match ($column) {
                        'email' => $table->boolean('email')->default(false),
                        'email_total' => $table->unsignedInteger('email_total')->default(0),
                        'email_sent' => $table->unsignedInteger('email_sent')->default(0),
                        'email_failed' => $table->boolean('email_failed')->default(false),
                    };
                });
            }
        }
        if (! Schema::hasTable('simplify_core_announcement_mails')) {
            Schema::create('simplify_core_announcement_mails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('announcement_id');
                $table->unsignedInteger('user_id');
                $table->timestamp('created_at')->nullable();

                $table->unique(['announcement_id', 'user_id']);
            });
        }
    }

    // Uninstalling Simplify keeps its data: Pelican rolls back a plugin's migrations on uninstall, and installing it
    // again finds every table and setting as it was.
    public function down(): void {}
};
