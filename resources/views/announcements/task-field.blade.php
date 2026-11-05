{{-- The "Announcement" task of a schedule (AnnouncementTask): message, when and where, kept as one JSON payload. --}}
@php($t = 'simplify::announcements.task.')
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div wire:ignore class="s-task" x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        data: { message: '', send: 'now', game_chat: false, notify: true },
        init() {
            this.read();
            this.$watch('state', () => this.read());
        },
        read() {
            let parsed = null;
            try { parsed = JSON.parse(this.state || '') } catch { parsed = this.state ? { message: String(this.state) } : null }
            if (parsed && typeof parsed === 'object') this.data = { ...this.data, ...parsed };
        },
        write() { this.state = JSON.stringify(this.data) },
    }">
        <div class="s-task-row">
            <label class="s-task-label" for="{{ $getId() }}-message">{{ __($t.'message') }}<sup class="s-task-required">*</sup></label>
            <x-filament::input.wrapper>
                <x-filament::input type="text" id="{{ $getId() }}-message" maxlength="{{ $max }}" x-model="data.message" x-on:input="write()" />
            </x-filament::input.wrapper>
            <div class="s-task-count" aria-live="polite"><span x-text="(data.message || '').length"></span> / {{ $max }}</div>
        </div>
        <div class="s-task-row">
            <label class="s-task-label" for="{{ $getId() }}-send">{{ __($t.'send') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="{{ $getId() }}-send" x-model="data.send" x-on:change="write()">
                    <option value="now">{{ __($t.'send_now') }}</option>
                    <option value="online">{{ __($t.'send_online') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <p class="s-task-help" x-show="data.send === 'online'" x-cloak>{{ __($t.'send_online_help') }}</p>
        </div>
        <fieldset class="s-task-row">
            <legend class="s-task-label">{{ __($t.'send_to') }}</legend>
            <label class="s-task-check">
                <x-filament::input.checkbox x-model="data.game_chat" x-on:change="write()" />
                <span><span class="s-task-check-name">{{ __($t.'game_chat') }}</span><span class="s-task-help">{{ __($t.'game_chat_help') }}</span></span>
            </label>
            <label class="s-task-check">
                <x-filament::input.checkbox x-model="data.notify" x-on:change="write()" />
                <span><span class="s-task-check-name">{{ __($t.'notify') }}</span><span class="s-task-help">{{ __($t.'notify_help') }}</span></span>
            </label>
        </fieldset>
    </div>
</x-dynamic-component>
