<?php

declare(strict_types=1);

namespace Simplify\Core\Console;

use Illuminate\Console\Command;
use Simplify\Core\Push\PluginUpdates;
use Simplify\Core\Push\UpdateNotices;

/** Runs hourly through Pelican's scheduler. */
final class UpdateNoticesCommand extends Command
{
    protected $signature = 'simplify:update-notices';

    protected $description = 'Tell root admins about new Pelican, Wings and plugin versions.';

    public function handle(): int
    {
        $this->info('Notified '.UpdateNotices::send().' devices.');
        $this->info('Plugin updates found: '.PluginUpdates::send().'.');

        return self::SUCCESS;
    }
}
