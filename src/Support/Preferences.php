<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use App\Models\User;
use Simplify\Core\Models\Preference;

/** Reads and saves a user's choices. A saved value that is no longer allowed reads as the default. */
final class Preferences
{
    /** @param  list<string>  $allowed */
    public static function choice(User $user, string $key, array $allowed, string $default): string
    {
        $value = self::get($user, $key);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    public static function get(User $user, string $key): mixed
    {
        return Preference::query()->where('user_id', $user->id)->where('key', $key)->first()?->value;
    }

    public static function set(User $user, string $key, mixed $value): void
    {
        Preference::query()->updateOrCreate(['user_id' => $user->id, 'key' => $key], ['value' => $value]);
    }
}
