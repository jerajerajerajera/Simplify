{{-- Seasonal touches on the overview that sit behind the page (resources/js/seasons.js): snow drifting through the
     header at Christmas, warm light on the longest day. Clicks pass through; the rest is in seasons.css. --}}
<template x-if="!server && season('christmas')">
    <div class="ps-season-snow" aria-hidden="true">
        <template x-for="n in 12" :key="n"><svg :style="{ insetInlineStart: (n * 8 - 4) + '%', animationDuration: (5 + n % 4 * 1.3) + 's', animationDelay: (-n * .9) + 's' }"><use href="#ps-snowflake"/></svg></template>
    </div>
</template>
<template x-if="!server && season('midsummer')">
    <div class="ps-season-glow" aria-hidden="true"></div>
</template>
