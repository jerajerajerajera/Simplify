<?php

declare(strict_types=1);

namespace Simplify\Core\Subdomains\Console;

use Illuminate\Console\Command;
use Simplify\Core\Subdomains\Subdomains;

final class SyncSubdomains extends Command
{
    protected $signature = 'simplify:subdomains';

    protected $description = 'Retry subdomains that are not connected, follow port changes and remove records of deleted servers.';

    public function handle(): int
    {
        Subdomains::tick();

        return self::SUCCESS;
    }
}
