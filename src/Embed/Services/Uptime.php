<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * How long the servers on the website were online, like a status page. Each row is a stretch of time a server was
 * online or offline: record() lengthens the open stretch every minute, or starts a new one when the state changed or
 * nothing was seen for a while (the scheduler did not run: that time counts as unknown, not as offline).
 */
final class Uptime
{
    public const TABLE = 'simplify_core_embed_uptime';

    /** Stretches older than this many days are removed. */
    public const KEEP_DAYS = 90;

    /** The card shows this many days. */
    public const DAYS = 30;

    /** A stretch not seen for this many seconds ends where it was last seen. */
    public const GAP = 300;

    /**
     * Notes the state of these servers at $now.
     *
     * @param  array<int, bool>  $online  by server id: true when it runs
     */
    public static function record(array $online, int $now): void
    {
        if ($online === []) {
            return;
        }
        $open = [];
        foreach (DB::table(self::TABLE)->whereIn('server_id', array_keys($online))->where('seen_at', '>=', $now - self::GAP)
            ->orderBy('seen_at')->get() as $row) {
            $open[(int) $row->server_id] = $row;
        }
        foreach ($online as $id => $running) {
            $row = $open[$id] ?? null;
            if ($row !== null && (bool) $row->online === $running) {
                DB::table(self::TABLE)->where('id', $row->id)->update(['seen_at' => $now]);

                continue;
            }
            // A change starts where the last stretch was seen, so the two meet without a gap.
            DB::table(self::TABLE)->insert(['server_id' => $id, 'online' => $running, 'started_at' => $row === null ? $now : (int) $row->seen_at, 'seen_at' => $now]);
        }
        DB::table(self::TABLE)->where('seen_at', '<', $now - self::KEEP_DAYS * 86400)->delete();
    }

    /**
     * Per server: since when it is in its current state (when that is known), its uptime over the last DAYS days and
     * per day (null for a day without data, oldest first). Days end at midnight in the panel's time zone.
     *
     * @param  list<int>  $ids
     * @return array<int, array{since: ?int, online: ?bool, percent: ?float, days: list<?float>}>
     */
    public static function summary(array $ids, int $now, ?string $timezone = null): array
    {
        if ($ids === []) {
            return [];
        }
        $zone = new DateTimeZone($timezone ?? (string) config('app.timezone', 'UTC'));
        $today = (new DateTimeImmutable('@'.$now))->setTimezone($zone)->setTime(0, 0);
        $starts = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $starts[] = $today->modify("-{$i} days")->getTimestamp();
        }
        $from = $starts[0];
        $rows = DB::table(self::TABLE)->whereIn('server_id', $ids)->where('seen_at', '>=', $from)->orderBy('started_at')->get();
        $by = [];
        foreach ($rows as $row) {
            $by[(int) $row->server_id][] = ['online' => (bool) $row->online, 'from' => (int) $row->started_at, 'to' => (int) $row->seen_at];
        }

        return array_map(fn (array $stretches) => self::summarize($stretches, $starts, $now), $by + array_fill_keys($ids, []));
    }

    /**
     * @param  list<array{online: bool, from: int, to: int}>  $stretches  oldest first
     * @param  list<int>  $starts  the start of each day
     * @return array{since: ?int, online: ?bool, percent: ?float, days: list<?float>}
     */
    public static function summarize(array $stretches, array $starts, int $now): array
    {
        $days = [];
        $up = $known = 0;
        foreach ($starts as $index => $start) {
            $end = $starts[$index + 1] ?? $now;
            [$dayUp, $dayKnown] = [0, 0];
            foreach ($stretches as $stretch) {
                $seconds = max(0, min($end, $stretch['to']) - max($start, $stretch['from']));
                $dayKnown += $seconds;
                $dayUp += $stretch['online'] ? $seconds : 0;
            }
            $days[] = $dayKnown > 0 ? round($dayUp / $dayKnown * 100, 1) : null;
            $up += $dayUp;
            $known += $dayKnown;
        }
        $last = $stretches === [] ? null : $stretches[array_key_last($stretches)];
        $current = $last !== null && $now - $last['to'] <= self::GAP;
        // Since when: back over the stretches with the same state that touch each other.
        $since = null;
        if ($current) {
            $since = $last['from'];
            for ($i = count($stretches) - 2; $i >= 0 && $stretches[$i]['online'] === $last['online'] && $stretches[$i]['to'] >= $since; $i--) {
                $since = min($since, $stretches[$i]['from']);
            }
        }

        return [
            'since' => $since,
            'online' => $current ? $last['online'] : null,
            'percent' => $known > 0 ? round($up / $known * 100, 1) : null,
            'days' => $days,
        ];
    }
}
