<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Console;

use App\Models\Server;
use Illuminate\Console\Command;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Embed\Services\ServerList;
use Simplify\Core\Embed\Services\Uptime;
use Simplify\Core\Overview\NodeUsage;

/** Runs every minute from Pelican's scheduler: notes which servers on the website run, for their uptime. */
final class RecordUptime extends Command
{
    protected $signature = 'simplify:embed-uptime';

    protected $description = 'Notes whether each server on the website is online, for the uptime on the website.';

    public function handle(): int
    {
        $ids = EmbedServers::ids();
        if ($ids === []) {
            return self::SUCCESS;
        }
        $servers = Server::query()->whereIn('id', $ids)->with('node')->limit(ServerList::LIMIT)->get();
        // One request per node for all its servers; a node that does not answer leaves its servers unknown.
        $usage = NodeUsage::read($servers);
        $online = [];
        foreach ($servers as $server) {
            $state = $usage[$server->uuid]['state'] ?? null;
            if ($server->status !== null) {
                $online[(int) $server->id] = false;
            } elseif (is_string($state)) {
                $online[(int) $server->id] = $state === 'running';
            }
        }
        Uptime::record($online, time());

        return self::SUCCESS;
    }
}
