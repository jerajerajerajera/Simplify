<x-filament-panels::page>
    {{-- On a phone every change saves itself (no Save button there), and a toast at the bottom says so. Pelican's tabs keep their Save button. --}}
    <form wire:submit="save" class="s-settings" x-init="last = JSON.stringify($wire.data)" x-on:change="autosave()" x-on:input="autosave()" x-on:click="autosave()"
          x-data="{ saved: false, timer: null, hide: null, last: null,
              autosave() {
                  if (! matchMedia('(max-width: 600px)').matches || this.$el.querySelector('.fi-active .s-pelican-frame')) return;
                  clearTimeout(this.timer);
                  this.timer = setTimeout(async () => {
                      const now = JSON.stringify(this.$wire.data);
                      if (now === this.last) return;
                      this.last = now;
                      await this.$wire.save(true);
                      this.saved = true; clearTimeout(this.hide); this.hide = setTimeout(() => this.saved = false, 2200);
                  }, 700);
              } }" style="--s-group-panel: {{ json_encode(__('simplify::dashboard.ui.panel'), JSON_UNESCAPED_UNICODE) }}; --s-group-addons: {{ json_encode(__('simplify::settings.addons.heading'), JSON_UNESCAPED_UNICODE) }}">
        {{ $this->form }}
        <div class="s-bar">
            {{-- On a Pelican tab the same button saves Pelican's form in the frame, whose own button stays hidden. --}}
            <x-filament::button type="submit" x-on:click="
                const frame = $el.closest('form').querySelector('.fi-active .s-pelican-frame iframe');
                if (! frame) return;
                $event.preventDefault();
                const buttons = [...(frame.contentDocument?.querySelectorAll('.fi-header-actions-ctn .fi-ac > button') ?? [])];
                (buttons.find((button) => /save/i.test(button.getAttribute('wire:click') ?? '')) ?? buttons[0])?.click();
            ">{{ __('simplify::settings.save') }}</x-filament::button>
        </div>
        <div class="s-saved" x-show="saved" x-cloak x-transition.opacity role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg><span>{{ __('simplify::settings.saved') }}</span></div>
    </form>
</x-filament-panels::page>
