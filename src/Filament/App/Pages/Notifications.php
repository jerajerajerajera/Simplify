<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use Simplify\Core\Models\PushDevice;
use Simplify\Core\Push\Push;

/**
 * App notifications for this account: turn them on per device, choose what to get, send a test, remove old devices.
 * The browser part (permission, subscription) runs in the page; the panel only stores what the browser hands over.
 */
final class Notifications extends Page
{
    protected static ?string $slug = 'simplify-notifications';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify::pages.notifications';

    public bool $messages = true;

    public bool $updates = true;

    public bool $pelican = true;

    /** The device id of this browser, once the page knows its subscription. */
    public ?string $current = null;

    public static function canAccess(): bool
    {
        return user() !== null && Push::enabled();
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::push.title');
    }

    public function mount(): void
    {
        $this->messages = Push::wants(user(), 'messages');
        $this->updates = Push::wants(user(), 'updates');
        $this->pelican = Push::wants(user(), 'pelican');
    }

    public function updatedMessages(): void
    {
        Push::choose(user(), 'messages', $this->messages);
    }

    public function updatedUpdates(): void
    {
        Push::choose(user(), 'updates', $this->updates);
    }

    public function updatedPelican(): void
    {
        Push::choose(user(), 'pelican', $this->pelican);
    }

    /** The panel's public key for the browser, and whether this browser's subscription is already known. */
    public function check(?string $endpoint): string
    {
        $this->current = Push::current(user(), $endpoint)?->device;

        return Push::publicKey();
    }

    /** @param  array<string, mixed>  $subscription */
    public function turnOn(array $subscription, string $label): bool
    {
        try {
            $this->current = Push::subscribe(user(), $subscription, $label)->device;
        } catch (InvalidArgumentException) {
            Notification::make()->title(__('simplify::push.unsupported'))->danger()->send();

            return false;
        }
        Notification::make()->title(__('simplify::push.on'))->success()->send();

        return true;
    }

    public function turnOff(string $endpoint): void
    {
        Push::remove(user(), $endpoint);
        $this->current = null;
    }

    public function remove(string $device): void
    {
        Push::remove(user(), $device);
        if ($device === $this->current) {
            $this->current = null;
        }
    }

    public function test(): void
    {
        $device = $this->current ? PushDevice::query()->where('user_id', user()->id)->where('device', $this->current)->first() : null;
        $result = $device ? Push::test($device, __('simplify::push.test_title'), __('simplify::push.test_body')) : 'gone';
        if ($result === 'gone') {
            $this->current = null;
        }
        Notification::make()->title(__('simplify::push.test_'.$result))->{$result === 'sent' ? 'success' : 'warning'}()->send();
    }

    /** @return list<array{device: string, label: string, added: string, last: ?string}> */
    public function devices(): array
    {
        return Push::devices(user())->map(fn (PushDevice $device) => [
            'device' => $device->device,
            'label' => $device->label,
            'added' => $device->created_at->diffForHumans(),
            'last' => $device->last_sent_at?->diffForHumans(),
        ])->all();
    }
}
