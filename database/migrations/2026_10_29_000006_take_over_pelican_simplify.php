<?php

use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Queue;

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

        // Its code may still be needed now, so the folder goes once the install is done: at the end of the command or
        // request, or, when the panel installs it from Admin → Plugins or the Hub, after that queue job (a queue worker
        // never terminates). deletePlugin only removes the files: an uninstall would roll back the migrations and drop
        // the tables with the settings.
        $done = false;
        $delete = function () use ($old, &$done): void {
            if (! $done) {
                $done = true;
                app(PluginService::class)->deletePlugin($old);
            }
        };
        app()->terminating($delete);
        Queue::after($delete);
    }

    public function down(): void {}
};
