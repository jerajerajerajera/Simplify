<?php

declare(strict_types=1);

namespace Simplify\Core\Problems;

use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use Simplify\Core\Server\Installation;
use Throwable;

/**
 * The log behind "Install failed" on a server's card and page, for an add-on such as Explain (ADDONS.md, Errors).
 * The id is simplify:install:<server uuid>. Only someone who may use the server's console gets the lines: that is
 * where Pelican shows the same output.
 */
final class InstallProblems
{
    public const PREFIX = 'simplify:install:';

    /** At most this many of the installation's last lines. */
    public const LINES = 100;

    public function canExplainProblem(string $id, User $user): bool
    {
        return $this->server($id, $user) !== null;
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        $server = $this->server($id, $user);
        if ($server === null) {
            return null;
        }
        try {
            $log = (string) app(DaemonServerRepository::class)->setServer($server)->getInstallLogs();
        } catch (Throwable) {
            $log = '';
        }

        return [
            'title' => __('simplify::dashboard.ui.install_problem_title', ['name' => $server->name]),
            'context' => 'The installation of a game server on Pelican failed. The lines are the output of its install script (egg: '.($server->egg?->name ?: 'unknown').'), read from Wings.',
            'lines' => self::lines($log),
        ];
    }

    /** The uuid in an id of this source, or null when the id is not one. */
    public static function uuid(string $id): ?string
    {
        return preg_match('/^'.preg_quote(self::PREFIX, '/').'([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/', $id, $match) === 1 ? $match[1] : null;
    }

    /**
     * The log as lines without terminal colours, oldest first, at most LINES.
     *
     * @return list<string>
     */
    public static function lines(string $log): array
    {
        $lines = preg_split('/\R/', trim((string) preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $log))) ?: [];

        return array_values(array_slice(array_filter($lines, fn (string $line) => trim($line) !== ''), -self::LINES));
    }

    /** The server behind the id, only while its installation failed and only for someone who may use its console. */
    private function server(string $id, User $user): ?Server
    {
        $uuid = self::uuid($id);
        $server = $uuid === null ? null : Server::query()->where('uuid', $uuid)->first();
        if ($server === null || ! in_array($server->status?->value, Installation::FAILED, true)) {
            return null;
        }

        return $user->isRootAdmin() || $user->can(SubuserPermission::ControlConsole, $server) ? $server : null;
    }
}
