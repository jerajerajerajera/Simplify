<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simplify\Core\Parts\Parts;

// Subdomains, formerly the separate plugin Simplify Subdomains. Its own table names are kept free, because
// uninstalling that plugin drops them.
return new class extends Migration
{
    public function up(): void
    {
        // Settings of the Automatic subdomains page, one row per key. The Cloudflare token is stored encrypted.
        Schema::create('simplify_core_subdomain_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value');
        });

        // One row per server that has (or had) a subdomain. A row without a name is a deleted server whose
        // records still have to be removed from Cloudflare.
        Schema::create('simplify_core_subdomains', function (Blueprint $table) {
            $table->id();
            $table->uuid('server_uuid')->unique();
            $table->string('name', 63)->nullable()->unique();
            $table->string('status', 16)->default('pending');
            $table->string('message', 500)->default('');
            $table->unsignedInteger('port')->nullable();
            $table->timestamps();
        });

        Parts::import('subdomains');
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_core_subdomains');
        Schema::dropIfExists('simplify_core_subdomain_settings');
    }
};
