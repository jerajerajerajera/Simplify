{{-- A few values as a row of small buttons, such as what the top bar shows. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="s-chips" role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($options as $value => $label)
            <label class="s-chip" wire:key="{{ $getStatePath() }}-{{ $value }}">
                <input type="radio" class="fi-sr-only" name="{{ $getStatePath() }}" value="{{ $value }}" wire:model{{ $live ? '.live' : '' }}="{{ $getStatePath() }}">
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
