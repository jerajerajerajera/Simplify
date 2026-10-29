<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * How an announcement is posted in a game's chat, as plain functions, so they are tested without a panel.
 */
final class GameChat
{
    public const PLACEHOLDER = '{message}';

    public const MESSAGE_LENGTH = 240;

    public const COMMAND_LENGTH = 200;

    /** Typed by an admin to skip a game. */
    public const SKIP = '-';

    /** Minutes between two posts while it shows; 0 is only once. */
    public const REPEATS = [0, 5, 10, 15, 30, 60, 180];

    /**
     * The chat command for an egg, by its name; null when the game has no chat command on its console (Velocity,
     * Palworld, Valheim, …). Checked from top to bottom.
     */
    private const DEFAULTS = [
        '/velocity/i' => null,
        '/bungee|waterfall|travertine|flamecord/i' => 'alert {message}',
        '/minecraft|vanilla|paper|purpur|spigot|bukkit|folia|forge|fabric|quilt|sponge|modrinth|curseforge|ftb|tekkit|hexxit|technic|atlauncher|bedrock|nukkit|pocketmine|mohist|arclight|magma|modpack/i' => 'say {message}',
        '/counter-?strike|\bcs ?2\b|cs ?: ?go|garry|gmod|team fortress|\btf2\b|left 4 dead|\bl4d|insurgency|day of defeat|no more room|synergy|fistful of frags/i' => 'say {message}',
        '/\brust\b|terraria|tmodloader|tshock|unturned/i' => 'say {message}',
        '/zomboid/i' => 'servermsg "{message}"',
        '/factorio/i' => '{message}',
    ];

    public static function defaultCommand(string $eggName): ?string
    {
        foreach (self::DEFAULTS as $pattern => $command) {
            if (preg_match($pattern, $eggName) === 1) {
                return $command;
            }
        }

        return null;
    }

    /** The admin's own command first ("-" skips the game), then the default. */
    public static function commandFor(string $eggName, ?string $own): ?string
    {
        if ($own !== null && $own !== '') {
            return $own === self::SKIP ? null : $own;
        }

        return self::defaultCommand($eggName);
    }

    public static function isValidCommand(string $command): bool
    {
        return $command === self::SKIP || (
            mb_strlen($command) <= self::COMMAND_LENGTH
            && str_contains($command, self::PLACEHOLDER)
            && preg_match('/[\x00-\x1F\x7F]/u', $command) !== 1
        );
    }

    /** What players read: "[Announcement] Title - message", on one line. */
    public static function message(string $prefix, string $title, ?string $body): string
    {
        $body = trim((string) preg_replace('/\s+/u', ' ', (string) $body));
        $line = trim($prefix.' '.$title).($body !== '' ? ' - '.$body : '');
        // No control characters (a line break would be a second console command) and no double quotes, which some
        // commands wrap the message in.
        $line = str_replace('"', "'", (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $line));

        return mb_strlen($line) > self::MESSAGE_LENGTH ? rtrim(mb_substr($line, 0, self::MESSAGE_LENGTH - 1)).'…' : $line;
    }

    public static function render(string $command, string $message): string
    {
        return str_replace(self::PLACEHOLDER, $message, $command);
    }

    /** Due when it never went out, or when its repeat time has come. Times are Unix seconds. */
    public static function isDue(?int $lastAt, int $repeatMinutes, int $now): bool
    {
        if ($lastAt === null) {
            return true;
        }

        // A few seconds early is fine: the scheduler runs every minute, not exactly on the minute.
        return $repeatMinutes > 0 && $lastAt + $repeatMinutes * 60 - 20 <= $now;
    }
}
