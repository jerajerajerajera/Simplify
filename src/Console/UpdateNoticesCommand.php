<?php

declare(strict_types=1);

namespace Simplify\Core\Console;

use Illuminate\Console\Command;
use Simplify\Core\Push\UpdateNotices;

/** Runs hourly through Pelican's scheduler. */
final class UpdateNoticesCommand extends Command
{
    protected $signature = 'simplify:update-notices';

    protected $description = 'Send root admins an app notification about new Pelican and Wings versions.';

    public function handle(): int
    {
        $this->info('Notified '.UpdateNotices::send().' devices.');

        return self::SUCCESS;
    }
}
