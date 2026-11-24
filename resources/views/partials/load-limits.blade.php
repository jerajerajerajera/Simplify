{{-- Under Health's two limits: three bars in the colours they give, with the ranges as typed. It reads the form in the
     browser, so it follows every key without a round trip. --}}
@php($t = 'simplify::settings.general.')
<div class="s-load-sample" x-data="{
    get warning() { const n = parseInt($wire.$get('data.load_warning'), 10); return Number.isFinite(n) ? n : 85 },
    get critical() { const n = parseInt($wire.$get('data.load_critical'), 10); return Number.isFinite(n) ? n : 95 },
    say(text) { return text.replace(':warning', this.warning).replace(':critical', this.critical) },
}">
    <div class="s-load-ok"><small x-text="say(@js(__($t.'load_below')))">{{ __($t.'load_below', ['warning' => 85]) }}</small><span><span style="width: 55%"></span></span></div>
    <div class="s-load-warn"><small x-text="say(@js(__($t.'load_between')))">{{ __($t.'load_between', ['warning' => 85, 'critical' => 95]) }}</small><span><span style="width: 84%"></span></span></div>
    <div class="s-load-hot"><small x-text="say(@js(__($t.'load_from')))">{{ __($t.'load_from', ['critical' => 95]) }}</small><span><span style="width: 94%"></span></span></div>
</div>
