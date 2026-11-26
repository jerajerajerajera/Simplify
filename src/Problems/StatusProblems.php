<?php

declare(strict_types=1);

namespace Simplify\Core\Problems;

use App\Models\User;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\StatusAddress;

/**
 * What the check of the status page's own address found (Settings › Website › Status page), for an add-on such as
 * Explain (ADDONS.md, Errors). The id is simplify:status:<state>. Only root admins get it: they are the ones who set
 * the address up, and the lines name the panel's address.
 */
final class StatusProblems
{
    public const PREFIX = 'simplify:status:';

    /** The states that are a problem. */
    public const STATES = ['dns', 'redirect', 'other', 'ssl', 'offline', 'unreachable', 'permission', 'cloudflare'];

    public function canExplainProblem(string $id, User $user): bool
    {
        return $this->state($id, $user) !== null;
    }

    /** @return array{title: string, context: string, lines: list<string>}|null */
    public function explainProblem(string $id, User $user): ?array
    {
        $state = $this->state($id, $user);
        if ($state === null) {
            return null;
        }
        $host = EmbedOptions::all()['status_host'];

        return [
            'title' => __('simplify::embed.settings.state_'.$state['state']),
            'context' => 'Simplify, a plugin for the Pelican game server panel, shows a public status page on an address of its own. '
                .'With Cloudflare connected it makes a proxied CNAME to the panel and, when Cloudflare reports 525/526, a Configuration Rule with SSL "Full" for that address only. '
                .'Cloudflare cannot rewrite the Host header below Enterprise, so the panel\'s web server must answer for the address. '
                .'The lines are what the check found when it asked https://'.$host.'/ as a visitor does (public DNS, no redirects followed).',
            'lines' => array_values(array_filter([
                'address: '.$host,
                'panel: '.StatusAddress::panelHost(),
                'cloudflare connected: '.(StatusAddress::cloudflare() ? 'yes' : 'no'),
                'state: '.$state['state'],
                $state['code'] > 0 ? 'http status: '.$state['code'] : '',
                $state['to'] !== '' ? 'redirects to: '.$state['to'] : '',
                $state['error'] !== '' ? 'detail: '.$state['error'] : '',
            ])),
        ];
    }

    /**
     * The saved check, only while it still shows the problem of the id, and only for root admins.
     *
     * @return array{state: string, code: int, to: string, error: string}|null
     */
    private function state(string $id, User $user): ?array
    {
        if (! $user->isRootAdmin() || preg_match('/^'.preg_quote(self::PREFIX, '/').'([a-z]+)$/', $id, $match) !== 1 || ! in_array($match[1], self::STATES, true)) {
            return null;
        }
        $host = EmbedOptions::all()['status_host'];
        if ($host === '') {
            return null;
        }
        $state = StatusAddress::state($host);

        return $state['state'] === $match[1] ? $state : null;
    }
}
