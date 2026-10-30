<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Console;

use Illuminate\Console\Command;
use Simplify\Core\Announcements\Services\Bell;
use Simplify\Core\Announcements\Services\InGame;
use Simplify\Core\Announcements\Services\Push;
use Simplify\Core\Support\Scheduler;

/** Runs every minute from Pelican's scheduler, so an announcement with a later start reaches the bell, devices and game chats when it starts. */
final class DeliverAnnouncements extends Command
{
    protected $signature = 'simplify:announcements';

    protected $description = 'Puts announcements that just started in the bell, sends them as app notifications and posts them in game.';

    public function handle(): int
    {
        Scheduler::beat();
        $reached = Bell::deliverDue();
        if ($reached > 0) {
            $this->info("Put announcements in the notifications of $reached accounts.");
        }
        $devices = Push::sendDue();
        if ($devices > 0) {
            $this->info("Sent announcements as app notifications to $devices devices.");
        }
        $servers = InGame::sendDue();
        if ($servers > 0) {
            $this->info("Posted announcements in game on $servers servers.");
        }

        return self::SUCCESS;
    }
}
