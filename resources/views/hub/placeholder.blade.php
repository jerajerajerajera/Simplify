{{-- Browse Hub before the list arrives: the bar and grey cards in their places, so nothing jumps. --}}
<div class="s-hub" aria-busy="true">
    <div class="s-hub-bar">
        <h2>{{ __('simplify::settings.hub.tab') }}</h2>
        <span class="s-hub-skel" style="width: 220px"></span>
        <span class="s-hub-skel" style="width: 230px"></span>
    </div>
    <div class="s-hub-grid">
        @for ($i = 0; $i < 6; $i++)
            <div class="s-hub-card"><span class="s-hub-skel s-hub-skel-shot"></span><span class="s-hub-skel"></span><span class="s-hub-skel" style="width: 60%"></span></div>
        @endfor
    </div>
</div>
