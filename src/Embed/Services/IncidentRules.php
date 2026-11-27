<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

/**
 * The status page's incidents as plain functions, so they are tested without a panel: when one opens, closes or
 * opens again, the Discord webhook it may use, and the RSS feed. Incidents (the service) keeps them in the database.
 */
final class IncidentRules
{
    /** A server down again this many seconds after it came back reopens the same incident: a crash loop is one. */
    public const REOPEN = 300;

    /** The status page and the feed show this many days. */
    public const DAYS = 14;

    /** Incidents older than this many days are removed. */
    public const KEEP_DAYS = 90;

    /** At most this many incidents on the page and in the feed. */
    public const SHOWN = 50;

    public const NOTE = 300;

    /** A Discord webhook: only Discord's own addresses, so the panel never posts to anything else. */
    public const WEBHOOK = '#^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api/webhooks/[0-9]{5,30}/[A-Za-z0-9_-]{20,100}$#';

    /**
     * What changes this minute. A server that runs closes its open incident where its online stretch began; one that
     * is unreachable for $after seconds opens one where its offline stretch began, or reopens the last one when that
     * closed less than REOPEN seconds before. A server without a stretch now (Wings did not answer) changes nothing.
     *
     * @param  array<int, bool>  $online  server id => runs now (only the servers Wings answered for)
     * @param  array<int, array{online: bool, since: int}>  $stretches  server id => its current stretch (Uptime)
     * @param  array<int, int>  $open  server id => its open incident
     * @param  array<int, array{id: int, ended: int}>  $closed  server id => its last closed incident
     * @return list<array{do: 'open'|'close'|'reopen', server: int, incident?: int, at?: int}>
     */
    public static function decide(array $online, array $stretches, array $open, array $closed, int $now, int $after): array
    {
        $actions = [];
        foreach ($online as $id => $running) {
            $stretch = $stretches[$id] ?? null;
            if ($stretch === null || $stretch['online'] !== $running) {
                continue;
            }
            $incident = $open[$id] ?? null;
            if ($running) {
                if ($incident !== null) {
                    $actions[] = ['do' => 'close', 'server' => $id, 'incident' => $incident, 'at' => min($now, $stretch['since'])];
                }

                continue;
            }
            if ($incident !== null || $now - $stretch['since'] < $after) {
                continue;
            }
            $last = $closed[$id] ?? null;
            if ($last !== null && $stretch['since'] - $last['ended'] <= self::REOPEN) {
                $actions[] = ['do' => 'reopen', 'server' => $id, 'incident' => $last['id']];
            } else {
                $actions[] = ['do' => 'open', 'server' => $id, 'at' => $stretch['since']];
            }
        }

        return $actions;
    }

    /** Whether a stop of the server was asked for: a power task of a schedule that ran around when it went down. */
    public static function scheduled(iterable $runs, int $since): bool
    {
        foreach ($runs as $ran) {
            if (is_int($ran) && $ran >= $since - 600 && $ran <= $since + 60) {
                return true;
            }
        }

        return false;
    }

    public static function validWebhook(string $url): bool
    {
        return preg_match(self::WEBHOOK, $url) === 1;
    }

    /** A webhook hidden as the settings show it once saved: the address up to the id, the token never. */
    public static function hidden(string $url): string
    {
        return preg_match('#^(https://[^/]+/api/webhooks/)#', $url, $match) === 1 ? $match[1].'•••••••' : '';
    }

    /**
     * The message Discord shows: one embed, red while the server is down and green once it is back; nobody is
     * mentioned, whatever a server's name holds.
     *
     * @return array<string, mixed>
     */
    public static function discord(string $sender, string $title, string $body, string $link, bool $down): array
    {
        return [
            'username' => mb_substr($sender, 0, 80),
            'allowed_mentions' => ['parse' => []],
            'embeds' => [[
                'title' => mb_substr(($down ? '🔴 ' : '🟢 ').$title, 0, 256),
                'description' => mb_substr($body, 0, 2000),
                'url' => $link,
                'color' => $down ? 0xDA373C : 0x23A55A,
            ]],
        ];
    }

    /**
     * The incidents as an RSS 2.0 feed: an item when a server goes down and one when it is back, newest first.
     *
     * @param  list<array{guid: string, title: string, body: string, at: int}>  $items
     */
    public static function feed(string $title, string $link, string $self, string $description, array $items): string
    {
        usort($items, fn (array $a, array $b) => $b['at'] <=> $a['at']);
        $x = fn (string $text) => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            .'<title>'.$x($title).'</title><link>'.$x($link).'</link><description>'.$x($description).'</description>'
            .'<atom:link href="'.$x($self).'" rel="self" type="application/rss+xml"/>'
            .'<ttl>5</ttl>';
        foreach (array_slice($items, 0, self::SHOWN * 2) as $item) {
            $xml .= '<item><title>'.$x($item['title']).'</title><link>'.$x($link).'</link>'
                .'<description>'.$x($item['body']).'</description>'
                .'<guid isPermaLink="false">'.$x($item['guid']).'</guid>'
                .'<pubDate>'.gmdate('D, d M Y H:i:s', $item['at']).' +0000</pubDate></item>';
        }

        return $xml.'</channel></rss>';
    }

    /** A note as the page shows it: plain text on one line. */
    public static function note(mixed $note): ?string
    {
        if (! is_string($note)) {
            return null;
        }
        $note = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/\p{C}/u', ' ', strip_tags($note)))), 0, self::NOTE);

        return $note === '' ? null : $note;
    }
}
