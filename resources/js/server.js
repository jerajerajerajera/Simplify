/* Simplify server page (Alpine component): the header, the server card and the live numbers above Pelican's own
   page. It builds on the overview component (dashboard.js) for the header, its menus and the notifications. */
(() => {
  const t = (key, params) => window.Simplify.t(key, params);
  const COLORS = { '#ef4444': 'Red', '#f97316': 'Orange', '#eab308': 'Gold', '#84cc16': 'Lime', '#22c55e': 'Green', '#10b981': 'Mint', '#06b6d4': 'Cyan', '#3b82f6': 'Blue', '#6366f1': 'Indigo', '#a855f7': 'Purple', '#ec4899': 'Pink', '#f43f5e': 'Rose' };
  const SERIES = 30;

  window.simplifyServer = function (wire, boot) {
    const base = window.pelicanSimplify(wire, boot);
    return {
      ...base,
      server: boot.server,
      COLORS,
      stats: { cpu: null, memory: null, disk: null, rx: null, tx: null },
      rates: { rx: null, tx: null },
      series: { cpu: [], ram: [], disk: [], network: [] },
      metrics: [
        { key: 'cpu', label: t('dashboard.cpu_load'), icon: 'cpu' },
        { key: 'ram', label: t('dashboard.memory'), icon: 'ram' },
        { key: 'disk', label: t('dashboard.storage'), icon: 'disk' },
        { key: 'network', label: t('dashboard.network_usage'), icon: 'traffic' },
      ],
      infoMenu: false,
      colorMenu: false,
      // Set while a power action, rename or look change is on its way; Alpine treats an undefined name as an error.
      busy: false,
      // The last state Wings reported over the console socket, for Pelican versions without window.ServerStats.
      socketState: null,
      colorDraft: null,
      statusBarVisible: false,
      sample: null,

      init() {
        this.initShell();
        this.take(this.server);
        this.$watch('themeDark', () => this.paintAccent());
        // Pelican's console page already streams the numbers from Wings; every other page asks Simplify now and then.
        this.refreshTimer = setInterval(() => this.refresh(), this.refreshSeconds);
        if (boot.console) {
          if (new URLSearchParams(location.search).has('simplify_console')) document.documentElement.classList.add('ps-console-window');
          // Every Pelican version passes Wings' "status" on as this Livewire event, the first one right after connecting.
          window.Livewire?.on?.('console-status', (event) => {
            const state = (Array.isArray(event) ? event[0] : event)?.state;
            if (typeof state === 'string') this.socketState = state;
          });
          this.liveTimer = setInterval(() => this.live(), 1000);
          this.$nextTick(() => this.live());
        }
        // The page fits the window: again when the window or the cards above it change size.
        addEventListener('resize', () => this.fitPage());
        this.$nextTick(() => {
          this.fitPage();
          // The server card (a MOTD or status arriving later) and the page's content (Livewire filling in after load):
          // the page itself has the fixed height --ps-fit, so only its content changes size.
          const observer = new ResizeObserver(() => this.fitPage());
          [this.$root.querySelector('.ps-main'), document.querySelector('.fi-main-ctn > .fi-main .fi-page-content')].forEach(el => el && observer.observe(el));
        });
      },
      destroy() {
        base.destroy.call(this);
        clearInterval(this.liveTimer);
      },
      get status() {
        return this.server?.status || 'missing';
      },
      /** The page's colour reaches Pelican's own page too (the console's frame, the menu). */
      paintAccent() {
        for (const [key, value] of Object.entries(this.accentStyle(this.server.uuid))) document.documentElement.style.setProperty(key, value);
      },
      take(server) {
        this.server = server;
        const u = server.usage || {};
        this.record({ cpu: u.cpu_percent, memory: u.memory_bytes, disk: u.disk_bytes, rx: u.network_rx_bytes, tx: u.network_tx_bytes }, Date.now() / 1000);
        this.paintAccent();
      },
      record(sample, at) {
        const previous = this.sample;
        this.stats = sample;
        if (previous && sample.rx != null && sample.tx != null && at > previous.at && at - previous.at <= 120) {
          const reset = sample.rx < previous.rx || sample.tx < previous.tx;
          this.rates = { rx: reset ? 0 : (sample.rx - previous.rx) / (at - previous.at), tx: reset ? 0 : (sample.tx - previous.tx) / (at - previous.at) };
        }
        this.sample = { ...sample, at };
        const values = { cpu: sample.cpu, ram: sample.memory, disk: sample.disk, network: (this.rates.rx ?? 0) + (this.rates.tx ?? 0) };
        for (const [key, value] of Object.entries(values)) {
          if (value == null) continue;
          this.series[key].push(Number(value));
          if (this.series[key].length > SERIES) this.series[key].shift();
        }
      },
      /** The newest numbers from Pelican's console (window.ServerStats), once per new frame. */
      live() {
        const stats = window.ServerStats, latest = stats?.latest?.();
        const state = stats?.state?.() || this.socketState;
        this.consoleBar(!!state);
        if (state && state !== this.server.status) this.server = { ...this.server, status: state };
        if (!latest || latest.t === this.liveAt) return;
        this.liveAt = latest.t;
        this.record({ cpu: latest.cpu, memory: latest.memory, disk: latest.disk, rx: latest.rx, tx: latest.tx }, latest.t / 1000);
      },
      async refresh() {
        if (this.destroyed || document.hidden) return;
        try {
          this.pollNotifications();
          const server = await wire.refresh();
          if (this.destroyed) return;
          // On the console page the live numbers win; the rest of the card follows the refresh.
          if (boot.console && this.liveAt) this.server = { ...server, status: this.server.status };
          else this.take(server);
        } catch {}
      },

      /** Puts the title row above Pelican's console output (once) and shows whether Wings is connected. */
      consoleBar(connected) {
        const terminal = document.getElementById('terminal');
        if (!terminal) return;
        let bar = terminal.querySelector(':scope > .ps-console-title');
        if (!bar) {
          bar = this.$refs.consoleBar.content.firstElementChild.cloneNode(true);
          terminal.prepend(bar);
          const grow = bar.querySelector('.ps-console-grow');
          grow.addEventListener('click', () => {
            const big = document.documentElement.classList.toggle('ps-console-big');
            grow.setAttribute('aria-pressed', String(big));
            grow.setAttribute('aria-label', big ? grow.dataset.normal : grow.dataset.bigger);
            window.dispatchEvent(new Event('resize'));
          });
          bar.querySelector('.ps-console-window').addEventListener('click', () => {
            const url = new URL(location.href);
            url.searchParams.set('simplify_console', '1');
            if (!window.open(url, 'simplify-console-' + this.server.uuid, 'popup,width=960,height=640')) this.toast(t('dashboard.the_browser_blocked_the_new_window'), 'warn');
          });
        }
        const state = bar.querySelector('.ps-console-state');
        bar.querySelector('.ps-console-dot').classList.toggle('connected', connected);
        bar.querySelector('.ps-live-bars').hidden = !connected;
        this.quickCommands(terminal, bar.dataset.quick);
        if (!terminal.style.height) this.fitPage();
        state.textContent = connected ? state.dataset.connected : state.dataset.connecting;
      },

      /** The page never scrolls: the menu and the page next to it get the window's height under the cards, and only
          the page scrolls inside it (--ps-fit, dashboard.css). The console fills that height, with the menu as tall;
          Pelican's terminal fits its rows to the new height on the resize event. Phones scroll; a low window gets a smaller console. */
      fitPage() {
        const root = document.documentElement, main = document.querySelector('.fi-main-ctn > .fi-main');
        // Also in a low window, such as a server in host mode's frame: then the console gets smaller, the page never scrolls.
        const fit = !!main && innerWidth > 600 && !root.classList.contains('ps-console-window');
        root.classList.toggle('ps-fit', fit);
        if (!fit) {
          root.style.removeProperty('--ps-fit');
          return this.fitConsole(0, main);
        }
        const footer = document.querySelector('.fi-footer, [data-ps-footer]')?.offsetHeight || 0;
        const height = Math.max(160, Math.floor(innerHeight - (main.getBoundingClientRect().top + scrollY) - footer - 14));
        root.style.setProperty('--ps-fit', height + 'px');
        this.fitConsole(height, main);
      },
      fitConsole(space, main) {
        const terminal = document.getElementById('terminal'), card = terminal?.parentElement, menu = document.querySelector('.ps-server-menu');
        if (!card) return;
        let height = '';
        // The terminal takes whatever the page has left (or gives back what it is short), so nothing else needs measuring.
        const scroller = main.querySelector('.fi-page-main'), content = scroller?.querySelector('.fi-page-content');
        if (space && content) height = Math.max(120, Math.floor(terminal.offsetHeight + scroller.clientHeight - content.offsetHeight)) + 'px';
        if (terminal.style.height !== height) {
          terminal.style.height = height;
          dispatchEvent(new Event('resize'));
        }
        if (menu) menu.style.height = space ? card.offsetHeight + 'px' : '';
      },

      /** Minecraft: a row of common commands above Pelican's command line; a click puts one in the line to finish or send. */
      quickCommands(terminal, label) {
        const input = document.getElementById('send-command');
        if (!this.server.minecraft || !input || input.readOnly || terminal.parentElement.querySelector('.ps-quick-commands')) return;
        const row = document.createElement('div');
        row.className = 'ps-quick-commands ps-ui';
        row.setAttribute('role', 'group');
        row.setAttribute('aria-label', label);
        for (const text of ['list', 'say ', 'time set day', 'weather clear', 'save-all']) {
          const button = Object.assign(document.createElement('button'), { type: 'button', className: 'ps-quick-command', textContent: text.endsWith(' ') ? text + '…' : text });
          button.addEventListener('click', () => {
            input.value = text;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
            input.setSelectionRange(text.length, text.length);
          });
          row.append(button);
        }
        terminal.after(row);
      },

      /* Card ------------------------------------------------------------------------------------- */
      /** Like Pelican's own buttons: Stop while running, Force stop while it starts or stops (Wings refuses a stop then). */
      mainPower() {
        if (this.status === 'running') return { action: 'stop', label: t('dashboard.stop'), icon: 'stop', permission: 'control.stop' };
        if (['starting', 'stopping', 'restarting'].includes(this.status)) return { action: 'kill', label: t('dashboard.force_stop'), icon: 'kill', permission: 'control.stop' };
        return { action: 'start', label: t('dashboard.start'), icon: 'play', permission: 'control.start' };
      },
      /** Restart works in every state but these, as in Pelican. */
      canRestart() {
        return !['missing', 'installing', 'install failed', 'restoring'].includes(this.status);
      },
      async power(action) {
        if (this.busy) return;
        if (action === 'kill' && !(await window.Simplify.confirm({ title: t('dashboard.force_stop_title'), message: t('dashboard.force_stop_message'), confirmLabel: t('dashboard.force_stop'), tone: 'danger' }))) return;
        this.busy = true;
        try {
          await wire.power(action);
          this.server = { ...this.server, status: { start: 'starting', stop: 'stopping', restart: 'stopping', kill: 'offline' }[action] };
          setTimeout(() => this.refresh(), 1500);
        } catch {
          this.toast(t('dashboard.the_power_action_could_not_be'), 'danger');
        } finally {
          this.busy = false;
        }
      },
      /** "Paper 1.21.4", "Valheim" or "Not reported" for the line under the server name. */
      /** Software and version once: Paper reports "Paper 1.21.1" itself, and an add-on badge may already say the same. */
      softwareLabel() {
        const software = this.server.software || '', version = this.server.reported_version || '';
        const named = software && [version, ...(this.server.badges || [])].find(text => text.toLowerCase().includes(software.toLowerCase()));
        return named || (version ? (software + ' ' + version).trim() : software) || t('dashboard.not_reported');
      },
      /** The add-on badges the software label does not already say. */
      metaBadges() {
        const label = this.softwareLabel().toLowerCase();
        return (this.server.badges || []).filter(badge => !label.includes(badge.toLowerCase()));
      },
      async copyText(text, message = t('dashboard.address_copied')) {
        try {
          await navigator.clipboard.writeText(text);
          this.toast(message, 'ok');
        } catch {}
      },
      async saveName(title, confirmLabel) {
        const name = await window.Simplify.prompt({ title, value: this.server.name, confirmLabel });
        if (name === null || !name.trim() || this.busy) return;
        this.busy = true;
        try {
          this.server = await wire.saveName(name);
          this.toast(t('dashboard.server_renamed'), 'ok');
        } catch {
          this.toast(t('dashboard.the_action_could_not_be_completed'), 'danger');
        } finally {
          this.busy = false;
        }
      },
      openColorMenu() {
        this.infoMenu = false;
        this.colorDraft = this.server.color || '#3b82f6';
        this.colorMenu = true;
      },
      /** Colour and background in one save: a chosen file goes up first, then the look is stored. */
      async saveLook(color, file = null, remove = false) {
        if (this.busy) return;
        this.busy = true;
        try {
          if (file) await new Promise((resolve, reject) => wire.upload('banner', file, resolve, reject));
          this.take(await wire.saveLook(color, remove));
          this.colorMenu = false;
          this.toast(file || remove ? t('dashboard.server_background_saved') : t('dashboard.server_color_saved'), 'ok');
        } catch {
          this.toast(t('dashboard.the_action_could_not_be_completed'), 'danger');
        } finally {
          this.busy = false;
        }
      },
      /** Shows the status bar while the server card is scrolled out of view. */
      watchServerInfo(el) {
        new IntersectionObserver(([entry]) => (this.statusBarVisible = !entry.isIntersecting && entry.boundingClientRect.bottom < 0)).observe(el);
      },

      /* Numbers ---------------------------------------------------------------------------------- */
      metricValue(key) {
        if (key === 'network') return this.rateLabel(this.rates.rx, this.rates.tx);
        const value = this.stats[{ cpu: 'cpu', ram: 'memory', disk: 'disk' }[key]];
        if (value == null) return '—';
        return key === 'cpu' ? Number(value).toFixed(1) + '%' : this.trafficBytes(value);
      },
      metricLimit(key) {
        const u = this.server.usage || {};
        if (key === 'network') return t('dashboard.traffic_used', { traffic: this.stats.rx == null || this.stats.tx == null ? '—' : '↓ ' + this.trafficBytes(this.stats.rx) + ' / ↑ ' + this.trafficBytes(this.stats.tx) });
        if (key === 'cpu') return t('dashboard.of_allocated', { amount: (u.cpu_limit || '∞') + '%' });
        const limit = key === 'ram' ? u.memory_limit_bytes : u.disk_limit_bytes;
        return t('dashboard.of_allocated', { amount: limit ? this.trafficBytes(limit) : '∞' });
      },
      spark(key) {
        const a = this.series[key];
        if (a.length < 2) return 'M0 34 L200 34';
        const max = Math.max(...a, 1);
        return a.map((v, i) => `${i ? 'L' : 'M'}${((i * 200) / (a.length - 1)).toFixed(1)} ${(34 - (v / max) * 30).toFixed(1)}`).join(' ');
      },
    };
  };
})();
