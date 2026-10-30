<?php

declare(strict_types=1);

namespace Simplify\Core\Push;

use App\Models\User;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Simplify\Core\Models\PushDevice;
use Simplify\Core\Models\Setting;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;
use Throwable;

/**
 * App notifications: the devices each account turned them on for, what the account wants to get, and sending through
 * the browsers' push services. Messages from add-ons arrive through the `simplify.push` event (see ADDONS.md).
 */
final class Push
{
    /** Kinds a user can turn off. Updates only ever reach root admins. */
    public const KINDS = ['messages', 'updates', 'pelican'];

    public const DEVICE_LIMIT = 10;

    /** Per device, the tags of the last messages sent once. */
    private const RECEIVED_LIMIT = 100;

    private const KEYS = 'push_keys';

    public static function enabled(): bool
    {
        return (bool) PanelSettings::get('push');
    }

    /** The panel's public VAPID key, which a browser needs to subscribe. Made once. */
    public static function publicKey(): string
    {
        return self::keys()['public'];
    }

    public static function wants(User $user, string $kind): bool
    {
        return in_array($kind, self::KINDS, true) && Preferences::get($user, 'push.'.$kind) !== false;
    }

    public static function choose(User $user, string $kind, bool $on): void
    {
        if (in_array($kind, self::KINDS, true)) {
            Preferences::set($user, 'push.'.$kind, $on);
        }
    }

    /** @return Collection<int, PushDevice> */
    public static function devices(User $user): Collection
    {
        return PushDevice::query()->where('user_id', $user->id)->latest('id')->get();
    }

    /**
     * Stores a browser subscription (PushSubscription.toJSON()). The same browser replaces its old entry.
     *
     * @param  array<string, mixed>  $subscription
     */
    public static function subscribe(User $user, array $subscription, string $label): PushDevice
    {
        $endpoint = is_string($subscription['endpoint'] ?? null) ? $subscription['endpoint'] : '';
        $p256dh = is_string($subscription['keys']['p256dh'] ?? null) ? $subscription['keys']['p256dh'] : '';
        $auth = is_string($subscription['keys']['auth'] ?? null) ? $subscription['keys']['auth'] : '';
        if (! WebPush::endpointAllowed($endpoint) || ! WebPush::keysValid($p256dh, $auth)) {
            throw new InvalidArgumentException('This browser cannot get notifications from the panel.');
        }
        $label = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($label))), 0, 60) ?: 'Browser';
        $device = PushDevice::query()->firstOrNew(['device' => self::id($endpoint)]);
        if ($device->exists && $device->user_id !== $user->id) {
            // A browser that was signed in to another account: it now belongs to this one, without that account's history.
            $device->received = [];
        }
        $device->fill(['user_id' => $user->id, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth, 'label' => $label])->save();
        PushDevice::query()->where('user_id', $user->id)->latest('id')->skip(self::DEVICE_LIMIT)->take(100)->get()->each->delete();

        return $device;
    }

    /** Removes a device by its id, or by its endpoint for "turn off on this device". */
    public static function remove(User $user, string $device): void
    {
        $id = str_starts_with($device, 'https://') ? self::id($device) : $device;
        PushDevice::query()->where('user_id', $user->id)->where('device', $id)->delete();
    }

    public static function current(User $user, ?string $endpoint): ?PushDevice
    {
        return $endpoint ? PushDevice::query()->where('user_id', $user->id)->where('device', self::id($endpoint))->first() : null;
    }

    /** Sends a test to one device: "sent", "gone" (the browser dropped it; removed) or "failed". */
    public static function test(PushDevice $device, string $title, string $body): string
    {
        return self::deliver([[$device, ['title' => $title, 'body' => $body, 'url' => url('/'), 'tag' => 'simplify-test'], null]])[0];
    }

    /**
     * Sends to every device of these users that want this kind. With a tag, a device gets that message once.
     *
     * @param  iterable<User>  $users
     * @param  array{title: string, body?: string, url?: ?string, tag?: ?string}  $message
     * @return int devices that accepted it
     */
    public static function send(iterable $users, string $kind, array $message): int
    {
        if (! self::enabled()) {
            return 0;
        }
        $tag = isset($message['tag']) && is_string($message['tag']) && $message['tag'] !== '' ? mb_substr($message['tag'], 0, 80) : null;
        $message = [
            'title' => mb_substr(trim((string) $message['title']), 0, 120),
            'body' => mb_substr(trim((string) ($message['body'] ?? '')), 0, 600),
            'url' => self::sameOrigin($message['url'] ?? null),
            'tag' => $tag,
        ];
        $ids = [];
        foreach ($users as $user) {
            if (self::wants($user, $kind) && ($kind !== 'updates' || $user->isRootAdmin())) {
                $ids[] = $user->id;
            }
        }
        $jobs = [];
        foreach (PushDevice::query()->whereIn('user_id', $ids)->get() as $device) {
            if ($tag === null || ! in_array($tag, $device->received ?? [], true)) {
                $jobs[] = [$device, $message, $tag];
            }
        }

        return count(array_filter(self::deliver($jobs), fn (string $result) => $result === 'sent'));
    }

    /**
     * The `simplify.push` listener.
     *
     * @param  array<string, mixed>  $event
     */
    public static function fromEvent(array $event): int
    {
        if (! is_string($event['title'] ?? null) || trim($event['title']) === '' || ! is_array($event['users'] ?? null)) {
            return 0;
        }
        $ids = array_values(array_filter($event['users'], 'is_int'));
        $kind = ($event['kind'] ?? 'messages') === 'updates' ? 'updates' : 'messages';

        return self::send(User::query()->whereIn('id', $ids)->get(), $kind, $event);
    }

    /**
     * All requests go out at once, so one slow push service does not hold up the rest.
     *
     * @param  list<array{0: PushDevice, 1: array<string, mixed>, 2: ?string}>  $jobs
     * @return list<string> "sent", "gone" or "failed" per job
     */
    private static function deliver(array $jobs): array
    {
        if ($jobs === []) {
            return [];
        }
        $keys = self::keys();
        $requests = [];
        foreach ($jobs as $index => [$device, $message]) {
            try {
                $payload = json_encode(array_filter($message, fn ($value) => $value !== null), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $requests[$index] = WebPush::request(['endpoint' => $device->endpoint, 'p256dh' => $device->p256dh, 'auth' => $device->auth], $payload, $keys, self::subject());
            } catch (Throwable $error) {
                report($error);
            }
        }
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (array $request) => $pool->timeout(8)->connectTimeout(4)->withHeaders($request['headers'])
                ->withBody($request['body'], 'application/octet-stream')->post($request['url']),
            $requests,
        ));
        $responses = array_combine(array_keys($requests), array_values($responses));
        $results = [];
        foreach ($jobs as $index => [$device, , $tag]) {
            $response = $responses[$index] ?? null;
            $status = $response instanceof Response ? $response->status() : 0;
            if (in_array($status, [404, 410], true)) {
                $device->delete();
                $results[] = 'gone';
            } elseif ($status >= 200 && $status < 300) {
                $received = $device->received ?? [];
                if ($tag !== null) {
                    $received = array_slice([$tag, ...array_diff($received, [$tag])], 0, self::RECEIVED_LIMIT);
                }
                $device->forceFill(['received' => $received, 'last_sent_at' => now()])->save();
                $results[] = 'sent';
            } else {
                $results[] = 'failed';
            }
        }

        return $results;
    }

    /** @return array{public: string, private: string} */
    private static function keys(): array
    {
        $saved = Setting::query()->where('key', self::KEYS)->value('value');
        if (is_array($saved) && is_string($saved['public'] ?? null) && is_string($saved['private'] ?? null)) {
            try {
                return ['public' => $saved['public'], 'private' => decrypt($saved['private'])];
            } catch (Throwable $error) {
                // A new APP_KEY: the old key pair cannot be read anymore, so every browser has to subscribe again.
                report($error);
                PushDevice::query()->delete();
            }
        }
        $keys = WebPush::createKeys();
        Setting::query()->updateOrCreate(['key' => self::KEYS], ['value' => ['public' => $keys['public'], 'private' => encrypt($keys['private'])]]);

        return $keys;
    }

    /** A contact for the push services, which VAPID requires: the panel's https address, else its mail sender. */
    private static function subject(): string
    {
        $url = rtrim(url('/'), '/');
        if (str_starts_with($url, 'https://')) {
            return $url;
        }
        $mail = (string) config('mail.from.address');

        return 'mailto:'.($mail !== '' ? $mail : 'admin@localhost');
    }

    private static function sameOrigin(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $home = url('/');
        $absolute = str_starts_with($url, '/') && ! str_starts_with($url, '//') ? rtrim($home, '/').$url : $url;

        return parse_url($absolute, PHP_URL_HOST) === parse_url($home, PHP_URL_HOST)
            && parse_url($absolute, PHP_URL_SCHEME) === parse_url($home, PHP_URL_SCHEME) ? $absolute : null;
    }

    private static function id(string $endpoint): string
    {
        return substr(hash('sha256', $endpoint), 0, 16);
    }
}
