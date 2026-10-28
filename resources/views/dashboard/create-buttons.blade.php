<template x-for="choice in createButtons()" :key="choice.url">
    <a class="ps-btn ps-create-button" :class="{'is-main': choice.primary}" :href="choice.url"><span class="ps-create-icon"><svg aria-hidden="true"><use :href="'#ps-'+(choice.primary ? 'host' : 'gamepad')"/></svg><span class="ps-create-badge" x-show="choice.primary"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span></span><span x-text="choice.label"></span></a>
</template>
