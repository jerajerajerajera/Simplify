<x-filament-panels::page>
    <div class="s-push" x-data="simplifyPush(@js(__('pelican-simplify::push.browser')))" x-init="start()">
        <div class="s-card">
            <div class="s-row">
                <x-filament::icon icon="tabler-device-mobile" class="s-row-icon" />
                <div class="s-row-text">
                    <div>{{ __('pelican-simplify::push.this_device') }}</div>
                    <div class="s-muted" x-show="state === 'on'" x-cloak>{{ __('pelican-simplify::push.this_on') }}</div>
                    <div class="s-muted" x-show="state === 'off'" x-cloak>{{ __('pelican-simplify::push.this_off') }}</div>
                    <div class="s-muted" x-show="state === 'blocked'" x-cloak>{{ __('pelican-simplify::push.blocked') }}</div>
                    <div class="s-muted" x-show="state === 'install'" x-cloak>{{ __('pelican-simplify::push.install') }}</div>
                    <div class="s-muted" x-show="state === 'unsupported'" x-cloak>{{ __('pelican-simplify::push.unsupported') }}</div>
                </div>
                <div class="s-bar">
                    <button type="button" class="s-btn s-btn-small" x-show="state === 'on'" x-cloak wire:click="test" :disabled="busy">
                        {{ __('pelican-simplify::push.test') }}
                    </button>
                    <button type="button" class="s-btn s-btn-small" x-show="state === 'on'" x-cloak x-on:click="off()" :disabled="busy">
                        {{ __('pelican-simplify::push.turn_off') }}
                    </button>
                    <button type="button" class="s-btn s-btn-small s-btn-primary" x-show="state === 'off' || state === 'blocked'" x-cloak x-on:click="on()" :disabled="busy">
                        {{ __('pelican-simplify::push.turn_on') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="s-card">
            <label class="s-row">
                <x-filament::icon icon="tabler-speakerphone" class="s-row-icon" />
                <span class="s-row-text">
                    <span>{{ __('pelican-simplify::push.messages') }}</span>
                    <span class="s-muted s-block">{{ __('pelican-simplify::push.messages_help') }}</span>
                </span>
                <input type="checkbox" class="s-switch" wire:model.live="messages">
            </label>
            @if (user()?->isRootAdmin())
                <label class="s-row">
                    <x-filament::icon icon="tabler-refresh-alert" class="s-row-icon" />
                    <span class="s-row-text">
                        <span>{{ __('pelican-simplify::push.updates') }}</span>
                        <span class="s-muted s-block">{{ __('pelican-simplify::push.updates_help') }}</span>
                    </span>
                    <input type="checkbox" class="s-switch" wire:model.live="updates">
                </label>
            @endif
        </div>

        @php($devices = $this->devices())
        @if ($devices !== [])
            <div class="s-card">
                <h2 class="s-section-title">{{ __('pelican-simplify::push.devices') }}</h2>
                @foreach ($devices as $device)
                    <div class="s-row" wire:key="device-{{ $device['device'] }}">
                        <div class="s-row-text">
                            <div>
                                {{ $device['label'] }}
                                @if ($device['device'] === $current)
                                    <span class="s-badge s-badge-ok">{{ __('pelican-simplify::push.this_badge') }}</span>
                                @endif
                            </div>
                            <div class="s-muted">
                                {{ __('pelican-simplify::push.added', ['time' => $device['added']]) }}
                                @if ($device['last'])
                                    · {{ __('pelican-simplify::push.last', ['time' => $device['last']]) }}
                                @endif
                            </div>
                        </div>
                        <button type="button" class="s-btn s-btn-icon" wire:click="remove(@js($device['device']))"
                            title="{{ __('pelican-simplify::push.remove') }}" aria-label="{{ __('pelican-simplify::push.remove') }}">
                            <x-filament::icon icon="tabler-trash" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @script
    <script>
        Alpine.data('simplifyPush', (fallback) => ({
            state: 'unsupported',
            busy: false,
            endpoint: null,
            key: null,
            async registration() {
                return navigator.serviceWorker.getRegistration();
            },
            async start() {
                const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext) {
                    // Safari on iPhone and iPad offers push only inside the installed app.
                    this.state = /iPhone|iPad/.test(navigator.userAgent) && !standalone ? 'install' : 'unsupported';
                    return;
                }
                const subscription = await (await this.registration())?.pushManager.getSubscription();
                this.endpoint = subscription?.endpoint || null;
                this.key = await $wire.check(this.endpoint);
                this.state = Notification.permission === 'denied' ? 'blocked' : ($wire.current ? 'on' : 'off');
            },
            label() {
                const agent = navigator.userAgent;
                const browser = /Edg\//.test(agent) ? 'Edge' : /Firefox\//.test(agent) ? 'Firefox' : /SamsungBrowser/.test(agent) ? 'Samsung Internet'
                    : /Chrome\//.test(agent) ? 'Chrome' : /Safari\//.test(agent) ? 'Safari' : fallback;
                const system = /iPhone/.test(agent) ? 'iPhone' : /iPad/.test(agent) ? 'iPad' : /Android/.test(agent) ? 'Android'
                    : /Windows/.test(agent) ? 'Windows' : /Mac OS X/.test(agent) ? 'Mac' : /Linux/.test(agent) ? 'Linux' : '';
                return browser + (system ? ' · ' + system : '');
            },
            async on() {
                this.busy = true;
                try {
                    if (await Notification.requestPermission() !== 'granted') {
                        this.state = 'blocked';
                        return;
                    }
                    const registration = await this.registration();
                    const bytes = Uint8Array.from(atob(this.key.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
                    let subscription = await registration.pushManager.getSubscription();
                    // A subscription made with an older panel key cannot be used anymore.
                    const old = subscription?.options?.applicationServerKey;
                    if (subscription && old && !new Uint8Array(old).every((value, index) => value === bytes[index])) {
                        await subscription.unsubscribe();
                        subscription = null;
                    }
                    subscription ||= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
                    if (await $wire.turnOn(subscription.toJSON(), this.label())) {
                        this.endpoint = subscription.endpoint;
                        this.state = 'on';
                    } else {
                        await subscription.unsubscribe().catch(() => {});
                    }
                } catch {
                    this.state = 'unsupported';
                } finally {
                    this.busy = false;
                }
            },
            async off() {
                this.busy = true;
                try {
                    await (await (await this.registration())?.pushManager.getSubscription())?.unsubscribe();
                } catch {}
                await $wire.turnOff(this.endpoint || '');
                this.state = 'off';
                this.busy = false;
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
