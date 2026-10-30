{{-- One notification under the bell and in the window with every notification; item comes from notifications(). --}}
<div class="ps-notify-item" :class="['is-'+item.tone, item.unread === false ? 'is-read' : '']">
    <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
    <strong x-text="item.title"></strong>
    {{-- ✕ moves one of Pelican's notifications to Archived (as in 0.7.0); nothing is deleted. --}}
    <button type="button" class="ps-notify-close" x-show="item.pelican && !item.archived && !item.pinned" x-on:click.stop="archiveNotifications([item.key])" :aria-label="@js($u('clear'))+': '+item.title" data-tip="{{ $u('clear') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
    <small x-text="item.text" x-show="item.text"></small>
    <code class="ps-notify-command" x-show="item.command" x-text="item.command"></code>
    <small class="ps-notify-time" x-show="item.time" x-text="item.time"></small>
    <span class="ps-notify-actions" x-show="item.action || item.pelican">
        <template x-if="item.action">
            <a class="ps-btn is-sm" :href="item.action.url" x-on:click="item.pelican && item.unread && markNotifications([item.key], true)" x-text="item.action.label"></a>
        </template>
        <button type="button" class="ps-btn is-sm is-subtle" x-show="item.pelican" x-on:click="markNotifications([item.key], item.unread)" x-text="item.unread ? @js($u('mark_read')) : @js($u('mark_as_unread'))"></button>
    </span>
</div>
