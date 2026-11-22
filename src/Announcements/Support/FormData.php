<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * The announcement form asks four things (what, when, where, who) in its own words; the table stores them in the
 * columns the scheduler reads. These plain functions go from one to the other, so they are tested without a panel.
 */
final class FormData
{
    public const NOW = 'now';

    public const DATE = 'date';

    public const WEEKLY = 'weekly';

    public const SERVER = 'server';

    public const WHEN = [self::NOW, self::DATE, self::WEEKLY, self::SERVER];

    public const EVERY = 'every';

    public const DAY = 'day';

    public const ONCE = 'once';

    /** In the chat of the server the moment is about, or of the chosen servers. */
    public const TARGETS = ['started', 'servers'];

    /** The fields that only exist in the form. */
    private const VIRTUAL = ['example', 'when', 'often', 'more', 'stop_after', 'stay_n', 'stay_unit'];

    /**
     * What the form shows for a stored announcement.
     *
     * @param  array<string, mixed>  $row
     * @return array{when: string, often: string, more: bool, stop_after: mixed, stay_n: int, stay_unit: string}
     */
    public static function fill(array $row): array
    {
        $sendOn = $row['send_on'] ?? StartTrigger::TIME;
        $when = match (true) {
            $sendOn === StartTrigger::START => self::SERVER,
            $sendOn === Weekly::SEND_ON => self::WEEKLY,
            Rules::has($row['starts_at'] ?? null) => self::DATE,
            default => self::NOW,
        };
        $repeats = in_array($when, [self::WEEKLY, self::SERVER], true);
        [$amount, $unit] = Rules::amount($row['stay_minutes'] ?? null);
        $filters = $when === self::SERVER && (Weekly::days($row['weekly_days'] ?? null) !== [] || Weekly::time($row['start_from'] ?? null) !== null || Weekly::time($row['start_until'] ?? null) !== null);

        return [
            'when' => $when,
            'often' => match (true) {
                (bool) ($row['start_once'] ?? false) => self::ONCE,
                $when === self::SERVER && (bool) ($row['start_daily'] ?? false) => self::DAY,
                default => self::EVERY,
            },
            'more' => $repeats && ($filters || Rules::has($row['ends_at'] ?? null)),
            'stop_after' => $repeats ? ($row['ends_at'] ?? null) : null,
            'stay_n' => $amount,
            'stay_unit' => $unit,
        ];
    }

    /**
     * The columns for what the form sent. A field the form did not show is not in $data and keeps what it had.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function clean(array $data): array
    {
        $when = in_array($data['when'] ?? null, self::WHEN, true) ? $data['when'] : self::NOW;
        $often = $data['often'] ?? self::EVERY;
        $more = (bool) ($data['more'] ?? false);
        $bar = (bool) ($data['bar'] ?? true);
        $repeats = in_array($when, [self::WEEKLY, self::SERVER], true);
        $stay = Rules::stay($data['stay'] ?? null);

        $out = array_diff_key($data, array_flip(self::VIRTUAL));
        $out['send_on'] = match ($when) {
            self::WEEKLY => Weekly::SEND_ON,
            self::SERVER => StartTrigger::START,
            default => StartTrigger::TIME,
        };
        $out['bar'] = $bar;
        // "Until a date" is for one at a time: one that comes back stays for a while, or until its next turn.
        $out['stay'] = $repeats && $stay === 'until' ? 'ended' : $stay;
        $out['stay_minutes'] = Rules::minutes($data['stay_n'] ?? null, $data['stay_unit'] ?? 'minutes');
        $out['start_once'] = $repeats && $often === self::ONCE;
        $out['start_daily'] = $when === self::SERVER && $often === self::DAY;

        if ($when === self::NOW) {
            $out['starts_at'] = null;
        }
        if ($when === self::WEEKLY) {
            $out['weekly_days'] = Weekly::days($data['weekly_days'] ?? null);
            $out['weekly_times'] = Weekly::times($data['weekly_times'] ?? null);
            $out['weekly_at'] = $out['weekly_times'][0] ?? null;
        }
        if ($when === self::SERVER) {
            $out['start_event'] = StartTrigger::event($data['start_event'] ?? null);
            $out['start_servers'] = StartTrigger::servers($data['start_servers'] ?? null);
            $out['weekly_days'] = $more ? Weekly::days($data['weekly_days'] ?? null) : [];
            $out['start_from'] = $more ? Weekly::time($data['start_from'] ?? null) : null;
            $out['start_until'] = $more ? Weekly::time($data['start_until'] ?? null) : null;
        }
        if ($repeats) {
            // One that comes back has no start of its own; "Stop after" is its end.
            $out['starts_at'] = null;
            $out['ends_at'] = $more ? ($data['stop_after'] ?? null) : null;
        }
        if (! $bar) {
            $out['bell_pinned'] = false;
            $out['in_game_repeat'] = 0;
        }
        $out['in_game_target'] = $when === self::SERVER && in_array($data['in_game_target'] ?? null, self::TARGETS, true) ? $data['in_game_target'] : ($when === self::SERVER ? 'started' : 'servers');
        $audience = $data['audience'] ?? 'everyone';
        $out['audience'] = in_array($audience, Rules::AUDIENCES, true) && ($audience !== Rules::SERVER_AUDIENCE || $when === self::SERVER) ? $audience : 'everyone';

        return $out;
    }

    /**
     * The examples above the form: each one fills in when, where and who, and leaves the words to the admin.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function examples(): array
    {
        $base = [
            'when' => self::NOW, 'often' => self::EVERY, 'more' => false, 'weekly_days' => [], 'start_from' => null, 'start_until' => null, 'stop_after' => null,
            'start_event' => 'start', 'bar' => true, 'stay' => 'ended', 'stay_n' => 3, 'stay_unit' => 'hours', 'in_bell' => true, 'bell_pinned' => false,
            'push' => true, 'in_game' => false, 'in_game_target' => 'started', 'in_game_repeat' => 0, 'audience' => 'everyone', 'category' => 'info',
        ];

        return [
            'maintenance' => [...$base, 'category' => 'warning', 'when' => self::DATE, 'stay' => 'for', 'stay_n' => 3, 'stay_unit' => 'hours'],
            'weekly' => [...$base, 'category' => 'success', 'when' => self::WEEKLY, 'weekly_days' => [5, 6], 'weekly_times' => ['19:00'], 'stay' => 'for', 'stay_n' => 3, 'stay_unit' => 'hours'],
            'online' => [...$base, 'category' => 'success', 'when' => self::SERVER, 'bar' => false, 'in_game' => true, 'audience' => Rules::SERVER_AUDIENCE],
            'weekend' => [...$base, 'when' => self::SERVER, 'often' => self::DAY, 'more' => true, 'weekly_days' => [6, 7], 'start_from' => '10:00', 'start_until' => '22:00', 'bar' => false, 'audience' => Rules::SERVER_AUDIENCE],
            'countdown' => [...$base, 'category' => 'warning', 'stay' => 'for', 'stay_n' => 30, 'stay_unit' => 'minutes', 'in_bell' => false, 'push' => false, 'in_game' => true, 'in_game_target' => 'servers', 'in_game_repeat' => 5],
            'crashed' => [...$base, 'category' => 'warning', 'when' => self::SERVER, 'start_event' => 'crash', 'bar' => false, 'audience' => 'admins'],
        ];
    }
}
