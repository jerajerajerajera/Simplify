{{-- A few values as a row of small buttons: the font and style of the panel name, or the logo height. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="s-chips" role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($options as $value => $label)
            <label class="s-chip" wire:key="{{ $getStatePath() }}-{{ $value }}">
                <input type="radio" class="fi-sr-only" name="{{ $getStatePath() }}" value="{{ $value }}" wire:model="{{ $getStatePath() }}">
                @if ($fonts)
                    <span class="s-chip-sample s-brand-font-{{ $value }}">Aa</span>
                @endif
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
