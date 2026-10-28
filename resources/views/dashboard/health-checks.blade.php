{{-- One mark per health check of Pelican; $open is the Alpine expression that opens the health page. --}}
<template x-for="check in healthChecks()" :key="check.label">
    <button type="button" class="ps-health-check" :class="'is-'+check.status" x-on:click="{!! $open !!}"
            :data-tip="check.label+(check.status==='ok' ? '' : ': '+(check.message || check.status))" :aria-label="check.label+': '+check.status">
        <svg aria-hidden="true"><use :href="check.status==='ok' ? '#ps-check' : '#ps-alert'"/></svg>
    </button>
</template>
