{{-- Debug build only (Diagnose::BUTTON): Test announcements on the overview, for root admins. Checks what every
     announcement would do now and why, with a log to copy; three buttons send a real test. English only: this never
     ships in a release. --}}
<span class="ps-test-announcements" x-data="{ wire: $wire, open: false, busy: false, report: null, server: '', copied: false,
        async load(action = null) {
            this.busy = true;
            try {
                let zone = null;
                try { zone = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch {}
                this.report = await this.wire.testAnnouncements(zone, action, this.server ? Number(this.server) : null);
            } catch (e) {
                this.report = { checks: [{ level: 'bad', title: 'The test itself failed', detail: String(e?.message || e) }], log: String(e?.message || e), servers: this.report?.servers || [] };
            }
            this.busy = false;
        },
        copyLog() {
            const text = this.report?.log || '';
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); };
            navigator.clipboard?.writeText(text).then(done, () => { this.$refs.log.focus(); this.$refs.log.select(); });
        } }">
    <button type="button" class="ps-btn" x-on:click="open = true; load()">
        <svg aria-hidden="true"><use href="#ps-flask"/></svg><span>Test announcements</span>
    </button>
    <template x-teleport="body">
        <div class="ps-modal-layer ps-ui ps-test-ann" :class="{ 'dark': document.documentElement.classList.contains('dark') }" x-show="open" x-cloak x-on:click.self="open = false" x-on:keydown.escape.window="open && (open = false)">
            <section class="ps-modal" role="dialog" aria-modal="true" aria-labelledby="ps-test-ann-title">
                <header class="ps-modal-head">
                    <h2 id="ps-test-ann-title">Test announcements</h2>
                    <button type="button" class="ps-btn is-sm is-subtle" x-on:click="load()" :disabled="busy">Test again</button>
                    <button type="button" class="ps-btn is-icon is-subtle" x-on:click="open = false" aria-label="Close" data-tip="Close"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                </header>
                <div class="ps-modal-body ps-test-ann-body">
                    <p class="ps-test-ann-note" x-show="busy">Checking…</p>
                    <p class="ps-test-ann-note">Only looks: nothing is sent, except with the three buttons at the bottom.</p>
                    <p class="ps-test-ann-done" x-show="report?.done" x-text="report?.done" role="status"></p>
                    <template x-for="(check, i) in (report?.checks || [])" :key="i">
                        <div class="ps-test-ann-row"><i :class="'is-' + check.level"></i><span><strong x-text="check.title"></strong><small x-text="check.detail"></small></span></div>
                    </template>
                    <textarea class="ps-test-ann-log" x-ref="log" readonly rows="10" x-text="report?.log || ''" aria-label="Log"></textarea>
                    <div class="ps-test-ann-send">
                        <span class="ps-label">Send a real test (it really goes out)</span>
                        <label class="ps-test-ann-server">Server <select x-model="server"><option value="">No server (bell and devices only)</option><template x-for="s in (report?.servers || [])" :key="s.id"><option :value="s.id" x-text="s.name"></option></template></select></label>
                        <div class="ps-test-ann-actions">
                            <button type="button" class="ps-btn is-sm" x-on:click="load('me')" :disabled="busy">Send a test to me now</button>
                            <button type="button" class="ps-btn is-sm" x-on:click="load('timed')" :disabled="busy">Test in 2 minutes (all admins)</button>
                            <button type="button" class="ps-btn is-sm" x-on:click="load('start')" :disabled="busy || !server">Test on next start of this server</button>
                        </div>
                        <small>"Send a test to me now" goes to your own bell and devices. The other two make an announcement titled "[Test] Simplify announcement test" for all admins, sent by the real scheduler (in game on the server you pick); delete it afterwards under Admin › Announcements.</small>
                    </div>
                </div>
                <footer class="ps-modal-foot">
                    <button type="button" class="ps-btn is-primary" x-on:click="copyLog()" x-text="copied ? 'Copied' : 'Copy log'"></button>
                </footer>
            </section>
        </div>
    </template>
</span>
