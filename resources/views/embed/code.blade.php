{{-- The code to paste into another website, with copy buttons and a preview of the saved list. --}}
@if ($show === null)
    <p class="se-muted">{{ __('pelican-simplify::embed.code.pick_first') }}</p>
@else
    @php($snippet = \Simplify\Core\Embed\Services\Snippet::make(url('/'), $show))
    <div class="se-code" x-data="{
        copied: null,
        async take(key, text) {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = key;
            } catch (e) {
                const range = document.createRange();
                range.selectNodeContents(this.$refs[key]);
                getSelection().removeAllRanges();
                getSelection().addRange(range);
                this.copied = key + '-selected';
            }
            setTimeout(() => this.copied = null, 1800);
        },
    }">
        @foreach (['script', 'iframe', 'page'] as $key)
            <div class="se-block">
                <div class="se-head">
                    <strong>{{ __('pelican-simplify::embed.code.'.$key) }}</strong>
                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        :icon="$key === 'page' ? 'tabler-link' : 'tabler-copy'"
                        x-on:click="take({{ \Illuminate\Support\Js::from($key) }}, {{ \Illuminate\Support\Js::from($snippet[$key]) }})"
                    >
                        <span x-text="copied === {{ \Illuminate\Support\Js::from($key) }} ? {{ \Illuminate\Support\Js::from(__('pelican-simplify::embed.code.copied')) }} : (copied === {{ \Illuminate\Support\Js::from($key.'-selected') }} ? {{ \Illuminate\Support\Js::from(__('pelican-simplify::embed.code.selected')) }} : {{ \Illuminate\Support\Js::from(__('pelican-simplify::embed.code.'.($key === 'page' ? 'copy_link' : 'copy'))) }})">
                            {{ __('pelican-simplify::embed.code.'.($key === 'page' ? 'copy_link' : 'copy')) }}
                        </span>
                    </x-filament::button>
                </div>
                <pre x-ref="{{ $key }}">{{ $snippet[$key] }}</pre>
                <p class="se-muted">
                    {{ __('pelican-simplify::embed.code.'.$key.'_help') }}
                    @if ($key === 'page')
                        <a href="{{ $snippet['page'] }}" target="_blank" rel="noopener" class="se-link">{{ __('pelican-simplify::embed.code.open') }}</a>
                    @endif
                </p>
            </div>
        @endforeach
        {{-- The preview loads only once it is on screen, so the page itself is ready at once, and is as tall as the list (up to 420px). --}}
        <div class="se-block" x-data="{ shown: false }" x-intersect.once="shown = true">
            <strong>{{ __('pelican-simplify::embed.code.preview') }}</strong>
            <iframe
                x-bind:src="shown ? {{ \Illuminate\Support\Js::from(str_replace('page=1', 'page=0', $snippet['page'])) }} : null"
                title="{{ __('pelican-simplify::embed.code.preview') }}"
                class="se-preview"
                x-on:load="const fit = () => { try { $el.style.height = Math.min(420, Math.max(80, $el.contentDocument.documentElement.scrollHeight)) + 'px' } catch {} }; fit(); try { new ResizeObserver(fit).observe($el.contentDocument.body) } catch {}"
            ></iframe>
        </div>
    </div>
@endif
<style>
    .se-code { display: grid; gap: 16px; }
    .se-block { display: grid; gap: 6px; }
    .se-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .se-code pre { margin: 0; padding: 10px 12px; border-radius: 8px; background: #0b0b0e; color: #e4e4e7; font: 12.5px/1.55 ui-monospace, Menlo, Consolas, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
    .se-muted { margin: 0; font-size: 13px; color: rgb(113 113 122); }
    .se-link { font-weight: 600; text-decoration: underline; }
    .se-preview { width: 100%; height: 420px; border: 1px dashed rgb(212 212 216); border-radius: 8px; background: transparent; }
    .dark .se-preview { border-color: rgb(63 63 70); }
</style>
