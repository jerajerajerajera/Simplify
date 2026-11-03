{{-- Placeholders while Simplify starts: the page's shapes in grey, in the panel's light or dark theme, from the server's
     HTML alone. A layer over the page, shown only while Simplify's root still has x-cloak (until Alpine starts it), so a
     page never opens white (P-10) and the page's own layout is never touched. Its styles are inline: they work before any
     stylesheet of Simplify has been read. Should the scripts never start, it fades away after a few seconds. --}}
@php($kind = $kind ?? 'overview')
<div class="ps-boot is-{{ $kind }}" aria-hidden="true">
    <div class="ps-boot-top"><span class="ps-boot-sk ps-boot-name"></span><span class="ps-boot-tools">@for ($i = 0; $i < 5; $i++)<span class="ps-boot-sk"></span>@endfor</span></div>
    @if ($kind === 'overview')
        <div class="ps-boot-stage">
            <span class="ps-boot-sk ps-boot-chip"></span>
            <div class="ps-boot-grid">@for ($i = 0; $i < 8; $i++)<span class="ps-boot-card"><span class="ps-boot-sk ps-boot-icon"></span><span class="ps-boot-sk ps-boot-line"></span><span class="ps-boot-tiles"><span class="ps-boot-sk"></span><span class="ps-boot-sk"></span></span></span>@endfor</div>
        </div>
    @elseif ($kind === 'server')
        <div class="ps-boot-server"><span class="ps-boot-card ps-boot-info"><span class="ps-boot-sk ps-boot-icon"></span><span class="ps-boot-sk ps-boot-line"></span></span><span class="ps-boot-card"></span></div>
    @else
        <div class="ps-boot-server"><span class="ps-boot-card"></span><span class="ps-boot-card"></span></div>
    @endif
</div>
@once
<style>
    .ps-boot { display: none; }
    body:has([data-simplify-root][x-cloak]) .ps-boot {
        --ps-boot-card: var(--ps-surface, #fff); --ps-boot-line: var(--ps-line, #dedee5); --ps-boot-sk: #e8e8ed; --ps-boot-shine: #f2f2f5;
        position: fixed; inset: 0; z-index: 40; display: flex; flex-direction: column; gap: 24px; box-sizing: border-box; overflow: hidden; padding: 32px 36px 4px;
        background: var(--ps-bg, #f4f4f5); animation: ps-boot-away .3s 6s forwards;
    }
    :root.dark body:has([data-simplify-root][x-cloak]) .ps-boot { --ps-boot-card: var(--ps-surface, #18181c); --ps-boot-line: var(--ps-line, #2c2c33); --ps-boot-sk: #232329; --ps-boot-shine: #2c2c33; background: var(--ps-bg, #101012); }
    @keyframes ps-boot-away { to { visibility: hidden; opacity: 0; } }
    .ps-boot-sk { display: block; border-radius: 7px; background: linear-gradient(90deg, var(--ps-boot-sk) 40%, var(--ps-boot-shine) 50%, var(--ps-boot-sk) 60%) 0 0 / 300% 100%; animation: ps-boot-shine 1.4s linear infinite; }
    @keyframes ps-boot-shine { to { background-position: -150% 0; } }
    .ps-boot-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; max-width: 1400px; margin: 0 auto; }
    .ps-boot.is-admin .ps-boot-top { max-width: none; }
    .ps-boot-name { width: min(280px, 45%); height: 26px; }
    .ps-boot-tools { display: flex; gap: 8px; }
    .ps-boot-tools > .ps-boot-sk { width: 38px; height: 38px; border-radius: 10px; }
    .ps-boot-tools > .ps-boot-sk:first-child { width: 120px; }
    .ps-boot-stage { display: flex; flex-direction: column; gap: 16px; width: 100%; max-width: calc(4 * 260px + 3 * 16px); min-height: 0; margin: 0 auto; }
    .ps-boot-chip { width: 110px; height: 30px; border-radius: 999px; }
    .ps-boot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr)); gap: 16px; }
    .ps-boot-card { display: flex; flex-direction: column; gap: 14px; box-sizing: border-box; min-height: 320px; padding: 18px; border: 1px solid var(--ps-boot-line); border-radius: 14px; background: var(--ps-boot-card); }
    .ps-boot-icon { width: 48px; height: 48px; border-radius: 10px; }
    .ps-boot-line { width: 70%; height: 20px; }
    .ps-boot-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: auto; }
    .ps-boot-tiles > .ps-boot-sk { height: 84px; border-radius: 10px; }
    .ps-boot-server { display: grid; flex: 1 1 auto; grid-template-rows: auto 1fr; gap: 16px; width: 100%; max-width: 1400px; min-height: 0; margin: 0 auto 28px; }
    .ps-boot.is-admin .ps-boot-server { grid-template-columns: 260px 1fr; grid-template-rows: 1fr; max-width: none; }
    .ps-boot-info { min-height: 150px; }
    @media (max-width: 600px) {
        body:has([data-simplify-root][x-cloak]) .ps-boot { gap: 16px; padding: 18px 14px 8px; }
        .ps-boot.is-admin .ps-boot-server { grid-template-columns: minmax(0, 1fr); }
        .ps-boot.is-admin .ps-boot-server > :first-child { display: none; }
        .ps-boot-tools > .ps-boot-sk:nth-child(-n + 2) { display: none; }
        .ps-boot-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .ps-boot-card { flex-direction: row; align-items: center; min-height: 84px; padding: 14px; }
        .ps-boot-tiles { display: none; }
        .ps-boot-info { min-height: 120px; }
    }
    @media (prefers-reduced-motion: reduce) { .ps-boot-sk { animation: none; } }
    @media print { .ps-boot { display: none !important; } }
</style>
@endonce
