# Opening Admin tools

Medians of 5 warm rounds, ms from the start of the navigation (bytes and counts where named).

| | Simplify (shield) | Legacy (Pelican alone) |
|---|---|---|
| clickToReady | 734 | - |
| ttfb | 246 | 279 |
| html | 248 | 281 |
| htmlBytes | 230788 | 392690 |
| fcp | 332 | 577 |
| dcl | 507 | 540 |
| ready | 506 | - |
| menu | - | - |
| table | - | 326 |
| load | 2941 | 564 |
| nodes | 2127 | 1578 |
| inlineScript | 32295 | 8991 |
| svgSymbols | 95 | 0 |

Admin menu click (wire:navigate, until the new table): 45000 ms
Shield clicks that loaded a whole page: 0 of 5 (clickToReady: from the click until the Admin page and Simplify stand; the timings below it are of the last whole page)

## Files (last round)

| kind | Simplify count / KB over the wire / KB | Legacy |
|---|---|---|
| link | 12 / 2107 / 2355 | 6 / 1407 / 1477 |
| script | 18 / 360 / 787 | 14 / 450 / 720 |
| other | 2 / 13 / 13 | 2 / 13 / 13 |
| img | 6 / 1 / 688 | 1 / 0 / 0 |
| fetch | 4 / 322 / 320 | 2 / 76 / 75 |

## Calls after the page (Simplify)

- 2597 ms +140 ms /livewire-91805c62/update (1031 B)
- 4467 ms +251 ms /admin/health (324605 B)
- 5350 ms +131 ms /livewire-91805c62/update (1038 B)
- 19997 ms +135 ms /livewire-91805c62/update (1346 B)

## CPU by file (shield click)

- 16843 ms ((idle))
- 387 ms (getBoundingClientRect)
- 246 ms ((program))
- 227 ms /livewire-91805c62/livewire.min.js
- 77 ms /simplify
- 39 ms (getPropertyValue)
- 31 ms (get ready)
- 27 ms ((garbage collector))
- 24 ms /js/filament/support/support.js
- 20 ms (query)
- 17 ms /simplify/assets/dashboard.js
- 16 ms (focus)
- 12 ms (parseFromString)
- 9 ms ()
- 8 ms (querySelectorAll)
- 3 ms (querySelector)
- 2 ms (remove)
- 2 ms (addEventListener)
- 2 ms (fetch)
- 1 ms (replaceWith)

## CPU by function (shield click)

- 16843 ms (idle) ((idle)):0
- 387 ms getBoundingClientRect (getBoundingClientRect):0
- 246 ms (program) ((program)):0
- 134 ms e.set /livewire-91805c62/livewire.min.js:1
- 77 ms fit /simplify:164
- 39 ms getPropertyValue (getPropertyValue):0
- 31 ms get ready (get ready):0
- 27 ms (anonymous) /livewire-91805c62/livewire.min.js:1
- 27 ms (garbage collector) ((garbage collector)):0
- 21 ms isElementVisible /js/filament/support/support.js:25
- 19 ms query (query):3447
- 16 ms focus (focus):0
- 16 ms applyScale /simplify/assets/dashboard.js:653
- 15 ms (anonymous) /livewire-91805c62/livewire.min.js:113
- 13 ms (anonymous) /livewire-91805c62/livewire.min.js:5
- 12 ms parseFromString (parseFromString):0
- 8 ms querySelectorAll (querySelectorAll):0
- 4 ms (anonymous) ():3445
- 4 ms kt /livewire-91805c62/livewire.min.js:5
- 3 ms querySelector (querySelector):0
- 3 ms (anonymous) /livewire-91805c62/livewire.min.js:114
- 3 ms (anonymous) ():0
- 3 ms (anonymous) ():1
- 2 ms ei /livewire-91805c62/livewire.min.js:5
- 2 ms remove (remove):0
- 2 ms addEventListener (addEventListener):0
- 2 ms t /livewire-91805c62/livewire.min.js:1
- 2 ms bs /livewire-91805c62/livewire.min.js:13
- 2 ms $p /livewire-91805c62/livewire.min.js:5
- 2 ms fetch (fetch):0
- 1 ms get /livewire-91805c62/livewire.min.js:5
- 1 ms Sa /livewire-91805c62/livewire.min.js:5
- 1 ms replaceWith (replaceWith):0
- 1 ms appendChild (appendChild):0
- 1 ms has /livewire-91805c62/livewire.min.js:1
- 1 ms Zn /livewire-91805c62/livewire.min.js:1
- 1 ms n /livewire-91805c62/livewire.min.js:1
- 1 ms setProperty (setProperty):0
- 1 ms importNode (importNode):0
- 1 ms getClientRects (getClientRects):0

## CPU by file (menu click)

- 47351 ms ((idle))
- 85 ms ((program))
- 47 ms ((garbage collector))
- 23 ms (query)
- 9 ms (querySelectorAll)
- 8 ms ()
- 3 ms /livewire-91805c62/livewire.min.js
- 2 ms (getBoundingClientRect)
- 2 ms (querySelector)
- 2 ms (evaluate)
- 1 ms (fetch)
- 1 ms (setTimeout)
- 1 ms (innerSerialize)
- 1 ms (isElementVisible)
- 1 ms (builtinRequestAnimationFrame)
- 1 ms (getComputedStyle)
- 0 ms (check)
- 0 ms (parseEvaluationResultValue2)
- 0 ms (checkElementStates)
- 0 ms (encode)

## CPU by function (menu click)

- 47351 ms (idle) ((idle)):0
- 85 ms (program) ((program)):0
- 47 ms (garbage collector) ((garbage collector)):0
- 22 ms query (query):3447
- 9 ms querySelectorAll (querySelectorAll):0
- 5 ms (anonymous) ():0
- 2 ms (anonymous) ():1
- 2 ms getBoundingClientRect (getBoundingClientRect):0
- 2 ms querySelector (querySelector):0
- 2 ms evaluate (evaluate):228
- 1 ms fetch (fetch):0
- 1 ms get /livewire-91805c62/livewire.min.js:5
- 1 ms setTimeout (setTimeout):0
- 1 ms innerSerialize (innerSerialize):131
- 1 ms isElementVisible (isElementVisible):112
- 1 ms builtinRequestAnimationFrame (builtinRequestAnimationFrame):5068
- 1 ms (anonymous) ():3445
- 1 ms getComputedStyle (getComputedStyle):0
- 1 ms query (query):3262
- 1 ms (anonymous) /livewire-91805c62/livewire.min.js:5
- 0 ms querySelectorAll (querySelectorAll):5120
- 0 ms check (check):5403
- 0 ms parseEvaluationResultValue2 (parseEvaluationResultValue2):63
- 0 ms checkElementStates (checkElementStates):5380
- 0 ms Fe /livewire-91805c62/livewire.min.js:5
- 0 ms get /livewire-91805c62/livewire.min.js:1
- 0 ms mergeNewSnapshot /livewire-91805c62/livewire.min.js:11
- 0 ms encode (encode):0
- 0 ms text (text):0
- 0 ms Qr /livewire-91805c62/livewire.min.js:5
- 0 ms raf (raf):5429
- 0 ms get children /livewire-91805c62/livewire.min.js:11
- 0 ms serialize (serialize):120
- 0 ms checkVisibility (checkVisibility):0
- 0 ms getActiveMessages /livewire-91805c62/livewire.min.js:5

## Slowest files (Simplify)

- 251 ms /admin/health (317 KB, start 4467)
- 140 ms /livewire-91805c62/update (1 KB, start 2597)
- 135 ms /livewire-91805c62/update (1 KB, start 19997)
- 131 ms /livewire-91805c62/update (1 KB, start 5350)
- 17 ms /css/filament/filament/app.css?v=5.10.2.0 (672 KB, start 256)
- 17 ms /build/assets/index-Dn7Qm8Tx.js (12 KB, start 257)
- 16 ms /build/assets/app-B8Kiizla.css (633 KB, start 255)
- 16 ms /js/filament/filament/echo.js?v=5.10.2.0 (90 KB, start 257)
- 16 ms /build/assets/app-BjcOx26H.js (0 KB, start 257)
- 15 ms /js/filament/support/support.js?v=5.10.2.0 (191 KB, start 257)
- 15 ms /js/filament/filament/app.js?v=5.10.2.0 (16 KB, start 257)
- 14 ms /js/filament/schemas/schemas.js?v=5.10.2.0 (4 KB, start 257)
- 14 ms /js/filament/tables/tables.js?v=5.10.2.0 (28 KB, start 257)
- 13 ms /js/filament/notifications/notifications.js?v=5.10.2.0 (9 KB, start 257)
- 9 ms /css/charrafimed/global-search-modal/global-search-modal.css?v=5.0.2.0 (32 KB, start 256)
