<?php

use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use Illuminate\Database\Migrations\Migration;

/*
 * Until version 1.4.0 Simplify had the plugin id "pelican-simplify", the id of the old PelicanSimplify (0.7.0 and
 * before) too. Both answer the same addresses (/simplify and the embed), and Simplify 1.x shares the namespace
 * Simplify\Core, so the old folder has to go. Its tables, settings and files stay: Simplify 1.x used the same table
 * names, and PelicanSimplify's files in storage/app/simplify are read by the import migrations.
 */
return new class extends Migration
{
    private const OLD = 'pelican-simplify';

    public function up(): void
    {
        $old = Plugin::query()->find(self::OLD);
        if ($old === null || ! in_array($old->namespace, ['Simplify\\Core', 'Simplify\\Panel'], true)) {
            return;
        }

        // Its code may still be needed in this request, so the folder goes once the request is done. deletePlugin only
        // removes the files: an uninstall would roll back the migrations and drop the tables with the settings.
        app()->terminating(fn () => app(PluginService::class)->deletePlugin($old));
    }

    public function down(): void {}
};
