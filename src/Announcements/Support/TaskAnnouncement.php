<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * The "Announcement" task of Pelican's schedules, as plain functions so they are tested without a panel: its payload
 * (stored as JSON in the task), who gets it, and what to do while waiting for a server to come online.
 */
final class TaskAnnouncement
{
    public const NOW = 'now';

    public const ONLINE = 'online';

    /** "When the server is online": given up after this long (the scheduler looks every minute). */
    public const WAIT_SECONDS = 600;

    public const SEND = 'send';

    public const WAIT = 'wait';

    public const GIVE_UP = 'give_up';

    /** @return array{message: string, send: string, game_chat: bool, notify: bool} */
    public static function defaults(): array
    {
        return ['message' => '', 'send' => self::NOW, 'game_chat' => false, 'notify' => true];
    }

    /**
     * Reads a task's payload. Anything that is not this task's JSON (a payload typed before the action changed) is
     * read as the message.
     *
     * @return array{message: string, send: string, game_chat: bool, notify: bool}
     */
    public static function parse(?string $payload): array
    {
        $payload = (string) $payload;
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            $data = ['message' => $payload];
        }

        return self::clean($data);
    }

    /**
     * One line of at most 240 characters without control characters, a known moment and two switches.
     *
     * @param  array<mixed>  $data
     * @return array{message: string, send: string, game_chat: bool, notify: bool}
     */
    public static function clean(array $data): array
    {
        $defaults = self::defaults();
        $message = is_scalar($data['message'] ?? null) ? (string) $data['message'] : '';
        $message = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message)));
        if (mb_strlen($message) > GameChat::MESSAGE_LENGTH) {
            $message = rtrim(mb_substr($message, 0, GameChat::MESSAGE_LENGTH));
        }
        $send = in_array($data['send'] ?? null, [self::NOW, self::ONLINE], true) ? $data['send'] : $defaults['send'];

        return [
            'message' => $message,
            'send' => $send,
            'game_chat' => self::flag($data['game_chat'] ?? null, $defaults['game_chat']),
            'notify' => self::flag($data['notify'] ?? null, $defaults['notify']),
        ];
    }

    /** @param  array<mixed>  $data */
    public static function encode(array $data): string
    {
        return (string) json_encode(self::clean($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Why a payload cannot be saved, as a key under simplify::announcements.task, or null when it can.
     *
     * @param  array<mixed>  $data
     */
    public static function problem(array $data): ?string
    {
        $raw = is_scalar($data['message'] ?? null) ? trim((string) $data['message']) : '';
        if ($raw === '') {
            return 'message_required';
        }
        if (mb_strlen($raw) > GameChat::MESSAGE_LENGTH) {
            return 'message_long';
        }
        $clean = self::clean($data);

        return $clean['game_chat'] || $clean['notify'] ? null : 'send_to_required';
    }

    /** Waiting for "When the server is online": send, wait for the next check, or give up. Times are Unix seconds. */
    public static function step(int $startedAt, int $now, bool $running): string
    {
        if ($running) {
            return self::SEND;
        }

        return $now - $startedAt >= self::WAIT_SECONDS ? self::GIVE_UP : self::WAIT;
    }

    /**
     * One look at the announcements that wait for their server (TaskWaits, every minute): sends those whose server
     * runs, tells the people about those that waited 10 minutes, and forgets both; the rest wait for the next look.
     * A server that is gone is forgotten. Returns how many were finished.
     *
     * @param  iterable<array<string, mixed>>  $rows  id, server_id, payload, schedule, started_at
     * @param  callable(int): ?object  $server
     * @param  callable(object): bool  $running
     * @param  callable(object, array{message: string, send: string, game_chat: bool, notify: bool}, ?string, bool): mixed  $deliver
     * @param  callable(int): mixed  $forget
     */
    public static function runWaits(iterable $rows, int $now, callable $server, callable $running, callable $deliver, callable $forget): int
    {
        $finished = 0;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $found = $server((int) ($row['server_id'] ?? 0));
            if ($found === null) {
                $forget($id);

                continue;
            }
            $step = self::step((int) ($row['started_at'] ?? 0), $now, $running($found));
            if ($step === self::WAIT) {
                continue;
            }
            // Forgotten first: a delivery that fails is never sent twice.
            $forget($id);
            $schedule = is_string($row['schedule'] ?? null) ? $row['schedule'] : null;
            $deliver($found, self::parse(is_string($row['payload'] ?? null) ? $row['payload'] : null), $schedule, $step === self::SEND);
            $finished++;
        }

        return $finished;
    }

    /** Posted in the game chat only when asked, the game has a chat command and the server runs. */
    public static function postsInGame(bool $gameChat, ?string $command, bool $running): bool
    {
        return $gameChat && $command !== null && $running;
    }

    /**
     * The people of one server: its owner and the people it is shared with, each once.
     *
     * @param  array<mixed>  $subusers
     * @return list<int>
     */
    public static function recipients(?int $owner, array $subusers): array
    {
        $ids = [];
        foreach ([$owner, ...$subusers] as $id) {
            // Some database drivers hand ids back as strings.
            $id = is_string($id) && ctype_digit($id) ? (int) $id : $id;
            if (is_int($id) && $id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Recipients by language, so the fixed words of a notification ("did not come online") are in each person's
     * own language. The message someone typed stays as it is.
     *
     * @param  array<int, ?string>  $languages  user id => language
     * @return array<string, list<int>>
     */
    public static function byLanguage(array $languages, string $fallback = 'en'): array
    {
        $groups = [];
        foreach ($languages as $id => $language) {
            $language = is_string($language) && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $language) === 1 ? $language : $fallback;
            $groups[$language][] = $id;
        }

        return $groups;
    }

    private static function flag(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return $default;
    }
}
