<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * "What this does": an announcement in plain sentences, from what the form holds. Shown under the examples while
 * the admin fills in the form, and in the list. Plain functions, so they are tested without a panel.
 */
final class Summary
{
    /**
     * @param  array<string, mixed>  $data  the form's state (FormData::fill() for a stored announcement)
     * @param  callable(string, array<string, string>): string  $text  a line of simplify::announcements.summary
     * @param  array{days: array<int, string>, servers: array<int, string>, date: callable(mixed): string}  $names
     */
    public static function text(array $data, callable $text, array $names): string
    {
        $when = $data['when'] ?? FormData::NOW;
        $bar = (bool) ($data['bar'] ?? true);
        $more = (bool) ($data['more'] ?? false);
        $days = fn (mixed $list): string => implode(', ', array_map(fn (int $day): string => $names['days'][$day] ?? (string) $day, Weekly::days($list)));
        $lines = [];

        if ($when === FormData::WEEKLY) {
            $chosen = $days($data['weekly_days'] ?? null);
            $times = implode(', ', Weekly::times($data['weekly_times'] ?? null));
            $lines[] = $text('weekly', ['days' => $chosen !== '' ? $chosen : '…', 'times' => $times !== '' ? $times : '…']);
            if (($data['often'] ?? null) === FormData::ONCE) {
                $lines[] = $text('once', []);
            }
        } elseif ($when === FormData::SERVER) {
            $servers = implode(', ', array_map(fn (int $id): string => $names['servers'][$id] ?? '#'.$id, StartTrigger::servers($data['start_servers'] ?? null)));
            $often = in_array($data['often'] ?? null, [FormData::DAY, FormData::ONCE], true) ? $data['often'] : FormData::EVERY;
            $lines[] = $text(StartTrigger::event($data['start_event'] ?? null).'_'.$often, ['servers' => $servers !== '' ? $servers : '…']);
            if ($more && $days($data['weekly_days'] ?? null) !== '') {
                $lines[] = $text('only_days', ['days' => $days($data['weekly_days'])]);
            }
            $from = Weekly::time($data['start_from'] ?? null);
            $until = Weekly::time($data['start_until'] ?? null);
            if ($more && ($from !== null || $until !== null)) {
                $lines[] = $text('only_hours', ['from' => $from ?? '00:00', 'until' => $until ?? '24:00']);
            }
        } else {
            $lines[] = $when === FormData::DATE && Rules::has($data['starts_at'] ?? null) ? $text('date', ['date' => $names['date']($data['starts_at'])]) : $text('now', []);
        }
        if ($more && $when !== FormData::NOW && $when !== FormData::DATE && Rules::has($data['stop_after'] ?? null)) {
            $lines[] = $text('stop_after', ['date' => $names['date']($data['stop_after'])]);
        }

        $places = [];
        if ($bar) {
            $places[] = $text('place_bar', []);
        }
        if ($data['in_bell'] ?? false) {
            $places[] = $text($bar && ($data['bell_pinned'] ?? false) ? 'place_bell_pinned' : 'place_bell', []);
        }
        if ($data['push'] ?? false) {
            $places[] = $text('place_push', []);
        }
        if (($data['email'] ?? false) && $when !== FormData::SERVER) {
            $places[] = $text('place_email', []);
        }
        if ($data['in_game'] ?? false) {
            $places[] = $text('place_game', []);
        }
        $audience = in_array($data['audience'] ?? null, Rules::AUDIENCES, true) ? $data['audience'] : 'everyone';
        $lines[] = $places === [] ? $text('nowhere', []) : $text('who_'.($audience === Rules::SERVER_AUDIENCE && $when !== FormData::SERVER ? 'everyone' : $audience), ['places' => implode(', ', $places)]);

        if (! $bar) {
            $lines[] = $text('no_bar', []);
        } else {
            $stay = Rules::stay($data['stay'] ?? null);
            $lines[] = match (true) {
                $stay === 'for' => $text('bar_for', ['time' => $text('time_'.(isset(Rules::UNITS[$data['stay_unit'] ?? null]) ? $data['stay_unit'] : 'minutes'), ['count' => (string) max(1, (int) ($data['stay_n'] ?? 1))])]),
                $stay === 'until' && $when !== FormData::WEEKLY && $when !== FormData::SERVER && Rules::has($data['ends_at'] ?? null) => $text('bar_until', ['date' => $names['date']($data['ends_at'])]),
                default => $text('bar_ended', []),
            };
        }

        return implode(' ', $lines);
    }
}
