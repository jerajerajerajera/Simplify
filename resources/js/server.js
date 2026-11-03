/* Simplify server page (Alpine component): the header, the server card and the live numbers above Pelican's own
   page. It builds on the overview component (dashboard.js) for the header, its menus and the notifications. */
(() => {
  const t = (key, params) => window.Simplify.t(key, params);
  const SERIES = 30;

  /* Pelican's console paints its output on a half see-through background of its own, which the card shows as a lighter
     box inside the box. Its terminal is made with a clear background instead, so the card's colour runs underneath. */
  const clearTerminal = (xterm) => {
    if (!xterm?.Terminal || xterm.Terminal.simplifyClear) return xterm;
    class Terminal extends xterm.Terminal {
      constructor(options = {}) {
        super({ ...options, allowTransparency: true, theme: { ...options.theme, background: 'rgba(0, 0, 0, 0)' } });
      }
    }
    Terminal.simplifyClear = true;
    return { ...xterm, Terminal };
  };
  let xterm = clearTerminal(window.Xterm);
  try {
    Object.defineProperty(window, 'Xterm', { configurable: true, get: () => xterm, set: (value) => { xterm = clearTerminal(value); } });
  } catch {}

  /* The buttons of a page's header: its header actions and what an add-on puts in the header-actions render hook (Explain
     on Pelican's Console page), without dropdowns and the modals they open. Same set as frame.js. Pelican's own power
     buttons on the Console page are left out: the server card has those. */
  const HEADER_ACTIONS = ['.fi-ac > :is(button, a)', '> :is(button, a):is(.fi-btn, .fi-icon-btn, .fi-link)', '> :not(.fi-ac) :is(button, a):is(.fi-btn, .fi-icon-btn, .fi-link)'].map((part) => '.fi-header-actions-ctn ' + part).join(', ');
  const POWER = /mountAction\(\s*['"](start|restart|stop|kill)['"]/;
  const headerActions = (header) => [...header.querySelectorAll(HEADER_ACTIONS)]
    .filter((el) => !el.closest('.fi-dropdown, .fi-modal') && ![...el.attributes].some((attr) => POWER.test(attr.value)));
  const tooltip = (el) => el.getAttribute('x-tooltip')?.match(/content:\s*'((?:[^'\\]|\\.)*)'/)?.[1]?.replace(/\\'/g, "'") || '';
  const actionLabel = (el) => el.getAttribute('aria-label') || el.getAttribute('title') || tooltip(el) || el.textContent.trim();

  /* Pelican's admin pages while an add-on's own host mode is on (AdminTop): only the header, as in host mode. */
  window.simplifyAdminTop = function (wire, boot) {
    const base = window.pelicanSimplify(wire, boot);
    return {
      ...base,
      init() {
        this.initShell();
        // Healthy keeps its state here as on the overview: asked right after first paint when no snapshot was ready,
        // then at the overview's pace, so it never turns grey.
        if (this.boot.features?.host_health) {
          const health = () => !document.hidden && wire.hostHealth().then((h) => { if (!this.destroyed) this.boot.host_health = h; }).catch(() => {});
          if (!this.boot.host_health?.state) health();
          this.refreshTimer = setInterval(health, this.refreshSeconds);
        }
        // This header stays (@persist) while the menu loads the next page: its new footer gets the thin line again.
        document.addEventListener('livewire:navigated', () => this.enhanceCopyright());
        // Admin → Servers → Create (Pelican's link on its page) opens Simplify's Create server popup, as everywhere else.
        // Livewire starts wire:navigate on mousedown, so that press stays away from the link too.
        const create = (event) => {
          const link = event.target.closest?.('a[href]');
          if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || link.closest('.ps')) return null;
          return /\/servers\/create\/?$/.test(new URL(link.href, location.href).pathname) && this.createButtons().length ? link : null;
        };
        document.addEventListener('mousedown', (event) => create(event) && event.stopPropagation(), true);
        document.addEventListener('click', (event) => {
          if (create(event) && this.openCreate(null, this.createButtons()[0])) {
            event.preventDefault();
            event.stopPropagation();
          }
        }, true);
      },
    };
  };

  window.simplifyServer = function (wire, boot) {
    const base = window.pelicanSimplify(wire, boot);
    return {
      ...base,
      server: boot.server,
      COLORS: boot.colors || {}, // Looks::COLORS
      stats: { cpu: null, memory: null, disk: null, rx: null, tx: null },
      rates: { rx: null, tx: null },
      series: { cpu: [], ram: [], disk: [], rx: [], tx: [] },
      // 0 → 1 between two measurements: the bars slide one step to the left while the next number is on its way.
      glide: 1,
      metrics: [
        { key: 'cpu', label: t('dashboard.cpu_short'), title: t('dashboard.cpu_load'), icon: 'cpu' },
        { key: 'ram', label: t('dashboard.memory_short'), title: t('dashboard.memory'), icon: 'ram' },
        { key: 'disk', label: t('dashboard.storage_short'), title: t('dashboard.storage'), icon: 'disk' },
        { key: 'network', label: t('dashboard.network_short'), title: t('dashboard.network_usage'), icon: 'traffic' },
      ],
      infoMenu: false,
      colorMenu: false,
      bannerMenu: false,
      bannerChoices: [],
      // Set while a power action, rename or look change is on its way; Alpine treats an undefined name as an error.
      busy: false,
      // The last state Wings reported over the console socket, for Pelican versions without window.ServerStats.
      socketState: null,
      colorDraft: null,
      statusBarVisible: false,
      logLines: [],
      sample: null,
      // The installation view (as in 0.7.0): the banner after it finished here, a browser notification on request.
      installReady: false,
      // A Minecraft server that still waits for its EULA: the same banner asks for it (as in 0.7.0), once per page view.
      eulaNeeded: false,
      // The EULA box in the server card, ticked by the person themselves.
      eulaAccepted: false,
      // Set for a moment when Start is pressed while the EULA is still open, so the box stands out.
      eulaPulse: false,
      eulaChecked: false,
      installNotify: false,
      installNow: Date.now(),
      installTicks: 0,
      // The console is the page next to the menu (set by setPage, also after the menu changed the page).
      onConsole: false,

      init() {
        this.initShell();
        this.take(this.server);
        this.$watch('themeDark', () => this.paintAccent());
        // Pelican's console page already streams the numbers from Wings; every other page asks Simplify now and then.
        this.refreshTimer = setInterval(() => this.refresh(), this.refreshSeconds);
        // While installing: the clock ticks every second, and Pelican is asked every 5 seconds whether it is done.
        this.installTimer = setInterval(() => {
          if (this.server?.setup !== 'installing') return;
          this.installNow = Date.now();
          if (++this.installTicks % 5 === 0) this.refresh();
        }, 1000);
        // Elsewhere than the console, the last console lines can show under the server card (Customize).
        this.$watch('overviewPreferences.serverConsoleLog', (on) => (on && !this.onConsole ? this.readLog() : (this.logLines = [])));
        if (boot.console && new URLSearchParams(location.search).has('simplify_console')) document.documentElement.classList.add('ps-console-window');
        // Every Pelican version passes Wings' "status" on as this Livewire event, the first one right after connecting.
        window.Livewire?.on?.('console-status', (event) => {
          const state = (Array.isArray(event) ? event[0] : event)?.state;
          if (typeof state === 'string' && this.onConsole) this.socketState = state;
        });
        this.setPage(!!boot.console);
        // The menu changes only the page next to it (wire:navigate) and this top stays (@persist): follow the new page,
        // and its new footer, which comes without the thin row (and the height fitPage takes off) until marked again.
        this.navigated = () => {
          this.enhanceCopyright();
          this.setPage(document.querySelector('template[data-ps-server-page]')?.dataset.console === '1');
        };
        document.addEventListener('livewire:navigated', this.navigated);
        // The page fits the window: again when the window or the cards above it change size. Only the browser's own
        // resize: fitConsole sends one for Pelican's terminal, and following that one again never ends.
        this.fitResize = (event) => event.isTrusted && this.fitPage();
        addEventListener('resize', this.fitResize);
      },
      /** The console or another page next to the menu: live numbers from the console, or the log strip; the menu marks the page. */
      setPage(console) {
        this.onConsole = console;
        clearInterval(this.liveTimer);
        clearInterval(this.logTimer);
        this.socketState = null;
        this.liveAt = null;
        if (console) {
          this.logLines = [];
          this.liveTimer = setInterval(() => this.live(), 1000);
          this.$nextTick(() => this.live());
        } else {
          this.logTimer = setInterval(() => this.readLog(), 4000);
          this.$nextTick(() => this.readLog());
        }
        this.markMenu();
        this.$nextTick(() => {
          const menu = document.querySelector('.ps-server-menu');
          if (menu && !console) menu.style.height = '';
          this.fitPage();
          // The server card (a MOTD or status arriving later) and the page's content (Livewire filling in after load):
          // the page itself has the fixed height --ps-fit, so only its content changes size.
          this.fitObserver?.disconnect();
          this.fitObserver = new ResizeObserver(() => this.fitPage());
          [this.$root.querySelector('.ps-main'), document.querySelector('.fi-main-ctn > .fi-main .fi-page-content')].forEach(el => el && this.fitObserver.observe(el));
        });
      },
      /** The open page in the menus, after Livewire changed it: the longest address that the page's address starts with. */
      markMenu() {
        const path = location.pathname.replace(/\/+$/, '');
        const links = [...this.$root.querySelectorAll('a[data-ps-match]')];
        const length = (link) => Math.max(-1, ...link.dataset.psMatch.split(' ').filter(Boolean).map((match) => {
          match = match.replace(/\/+$/, '');
          return path === match || path.startsWith(match + '/') ? match.length : -1;
        }));
        const best = Math.max(-1, ...links.map(length));
        for (const link of links) {
          if (best >= 0 && length(link) === best) link.setAttribute('aria-current', 'page');
          else link.removeAttribute('aria-current');
        }
        const more = this.$root.querySelector('.ps-server-menu-more');
        if (more && more.querySelector('a[aria-current]')) more.open = true;
        const phoneMore = this.$root.querySelector('.ps-phone-more > .ps-phone-section');
        if (phoneMore) phoneMore.toggleAttribute('aria-current', !!this.$root.querySelector('.ps-phone-more-menu a[aria-current]'));
        if (phoneMore?.hasAttribute('aria-current')) phoneMore.setAttribute('aria-current', 'page');
      },
      destroy() {
        base.destroy.call(this);
        document.removeEventListener('livewire:navigated', this.navigated);
        removeEventListener('resize', this.fitResize);
        this.fitObserver?.disconnect();
        clearInterval(this.liveTimer);
        clearInterval(this.installTimer);
        clearInterval(this.logTimer);
      },
      async readLog() {
        if (this.destroyed || this.onConsole || document.hidden || !this.overviewPreferences.serverConsoleLog || !this.server.can_console || this.status === 'offline') return;
        try {
          this.logLines = await wire.logLines();
        } catch {
          this.logLines = [];
        }
      },
      get status() {
        return this.server?.status || 'missing';
      },
      /** The page's colour reaches Pelican's own page too (the console's frame, the menu). */
      paintAccent() {
        for (const [key, value] of Object.entries(this.accentStyle(this.server.uuid))) document.documentElement.style.setProperty(key, value);
      },
      take(server) {
        // An installation that finished while the page was open: the banner above the card offers Start.
        if (this.server?.setup === 'installing' && !server.setup) {
          this.installReady = true;
          if (this.installNotify) this.notifyInstalled();
        }
        this.server = server;
        const u = server.usage || {};
        this.record({ cpu: u.cpu_percent, memory: u.memory_bytes, disk: u.disk_bytes, rx: u.network_rx_bytes, tx: u.network_tx_bytes }, Date.now() / 1000);
        this.paintAccent();
        this.checkEula();
      },
      /** Once the server is installed and stopped, asks whether its Minecraft EULA is still open. */
      async checkEula() {
        if (this.eulaChecked || !this.server?.minecraft || this.server.setup || this.status !== 'offline' || !this.server.permissions?.['control.start']) return;
        this.eulaChecked = true;
        try {
          this.eulaNeeded = await wire.eulaNeeded();
        } catch {
          this.eulaNeeded = false;
        }
      },
      /** A server that just installed, or whose Minecraft EULA is still open, has not run yet: its card offers First start. */
      firstStart() {
        return (this.installReady || this.eulaNeeded) && this.status === 'offline' && !this.installView();
      },
      firstStartNote() {
        return t('dashboard.ready_first_start');
      },
      /** First start: writes eula.txt when the person ticked the EULA box, and starts. */
      async firstStartServer() {
        if (!this.eulaNeeded) {
          this.installReady = false;
          return this.power('start');
        }
        if (!this.eulaAccepted) return this.flashEula();
        this.busy = true;
        try {
          this.take(await wire.acceptEula());
          this.eulaNeeded = false;
          this.installReady = false;
          this.toast(t('dashboard.eula_accepted_start_sent'), 'ok');
        } catch {
          this.toast(t('dashboard.the_action_could_not_be_completed'), 'danger');
        } finally {
          this.busy = false;
        }
      },
      flashEula() {
        this.eulaPulse = false;
        this.$nextTick(() => (this.eulaPulse = true));
        setTimeout(() => (this.eulaPulse = false), 1600);
      },
      record(sample, at) {
        const previous = this.sample;
        this.stats = sample;
        if (previous && sample.rx != null && sample.tx != null && at > previous.at && at - previous.at <= 120) {
          const reset = sample.rx < previous.rx || sample.tx < previous.tx;
          this.rates = { rx: reset ? 0 : (sample.rx - previous.rx) / (at - previous.at), tx: reset ? 0 : (sample.tx - previous.tx) / (at - previous.at) };
        }
        this.sample = { ...sample, at };
        const values = { cpu: sample.cpu, ram: sample.memory, disk: sample.disk, rx: this.rates.rx ?? 0, tx: this.rates.tx ?? 0 };
        for (const [key, value] of Object.entries(values)) {
          if (value == null) continue;
          this.series[key].push(Number(value));
          // One more than is shown: the oldest bar slides out on the left while the newest slides in.
          if (this.series[key].length > SERIES + 1) this.series[key].shift();
        }
        this.slide(previous ? (at - previous.at) * 1000 : 0);
      },
      /** Moves the bars over the time between two measurements, so they glide instead of jumping. */
      slide(ms) {
        cancelAnimationFrame(this.glideFrame);
        if (!(ms > 0) || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return void (this.glide = 1);
        const start = performance.now(), duration = Math.min(Math.max(ms, 400), 5000);
        const step = (now) => {
          this.glide = Math.min(1, (now - start) / duration);
          if (this.glide < 1) this.glideFrame = requestAnimationFrame(step);
        };
        this.glide = 0;
        this.glideFrame = requestAnimationFrame(step);
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
      /** True while a new or reinstalled server is not usable yet: Pelican's own setup state. */
      installView() {
        return ['installing', 'install_failed', 'reinstall_failed', 'restoring_backup'].includes(this.server?.setup);
      },
      installFailed() {
        return ['install_failed', 'reinstall_failed'].includes(this.server?.setup);
      },
      installTitle() {
        if (this.server?.setup === 'restoring_backup') return t('dashboard.restoring_a_backup');
        if (this.installFailed()) return t('dashboard.the_installation_did_not_finish');
        return t('dashboard.setting_up', { server: this.server?.name });
      },
      installText() {
        if (this.server?.setup === 'restoring_backup') return t('dashboard.everything_opens_again_when_the_restore');
        if (this.installFailed()) return t('dashboard.the_last_lines_of_the_installation');
        return t('dashboard.this_takes_a_few_minutes_longer');
      },
      /** The server exists, the install script runs, then it is ready to start. */
      installSteps() {
        const software = this.server?.software || t('dashboard.the_game');
        const reached = 1;
        const steps = [
          { key: 'created', label: t('dashboard.server_created'), detail: t('dashboard.resources_reserved') },
          { key: 'install', label: t('dashboard.installing_software', { software }), detail: this.installFailed() ? t('dashboard.stopped_with_error') : t('dashboard.downloading_game_files') },
          { key: 'ready', label: t('dashboard.ready_to_start'), detail: t('dashboard.everything_opens') },
        ];
        return steps.map((step, index) => ({ ...step, state: index < reached ? 'done' : index > reached ? 'todo' : this.installFailed() ? 'failed' : 'active' }));
      },
      /** Time since Pelican started the installation, such as "2:14". */
      installElapsed() {
        const since = this.server?.install_since;
        if (!since || this.server?.setup !== 'installing') return '';
        const seconds = Math.max(0, Math.floor(this.installNow / 1000 - since));
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
      },
      /** How long this egg usually takes on this panel, such as "usually about 2 min". */
      installTypical() {
        const seconds = this.server?.install_typical;
        return seconds ? t('dashboard.usually_about', { count: Math.max(1, Math.round(seconds / 60)) }) : '';
      },
      async toggleInstallNotify() {
        if (!this.installNotify && typeof Notification === 'function' && Notification.permission === 'default') {
          try { await Notification.requestPermission(); } catch {}
        }
        this.installNotify = !this.installNotify;
      },
      notifyInstalled() {
        const name = this.server?.name;
        try {
          if (typeof Notification === 'function' && Notification.permission === 'granted' && document.hidden) new Notification(t('dashboard.is_ready', { server: name }), { body: t('dashboard.can_start_now') });
        } catch {}
        this.toast(t('dashboard.ready_toast', { server: name }), 'ok');
      },
      async reinstall() {
        if (this.busy) return;
        this.busy = true;
        try {
          this.take(await wire.reinstall());
          this.toast(t('dashboard.installation_started'), 'ok');
        } catch {
          this.toast(t('dashboard.the_action_could_not_be_completed'), 'danger');
        } finally {
          this.busy = false;
        }
      },
      async refresh() {
        // A hidden tab still hears when an installation finishes, for the notification.
        if (this.destroyed || (document.hidden && this.server?.setup !== 'installing')) return;
        try {
          this.pollNotifications();
          const server = await wire.refresh();
          if (this.destroyed) return;
          // On the console page the live numbers win; the rest of the card follows the refresh.
          if (this.onConsole && this.liveAt && !server.setup && !this.server.setup) this.server = { ...server, status: this.server.status };
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
          const size = (big) => {
            document.documentElement.classList.toggle('ps-console-big', big);
            grow.setAttribute('aria-pressed', String(big));
            grow.setAttribute('aria-label', big ? grow.dataset.normal : grow.dataset.bigger);
            this.fitPage();
            window.dispatchEvent(new Event('resize'));
          };
          // The bigger console is remembered on this device (Customize), so server pages open with it.
          grow.addEventListener('click', () => {
            const big = !document.documentElement.classList.contains('ps-console-big');
            this.setOverviewPreference('serverBiggerConsole', big);
            size(big);
          });
          if (this.overviewPreferences.serverBiggerConsole) size(true);
          bar.querySelector('.ps-console-window').addEventListener('click', () => {
            const url = new URL(location.href);
            url.searchParams.set('simplify_console', '1');
            if (!window.open(url, 'simplify-console-' + this.server.uuid, 'popup,width=960,height=640')) this.toast(t('dashboard.the_browser_blocked_the_new_window'), 'warn');
          });
        }
        this.consoleActions(bar);
        const state = bar.querySelector('.ps-console-state');
        bar.querySelector('.ps-console-dot').classList.toggle('connected', connected);
        bar.querySelector('.ps-live-bars').hidden = !connected;
        this.quickCommands(terminal, bar.dataset.quick);
        if (!terminal.style.height) this.fitPage();
        state.textContent = connected ? state.dataset.connected : state.dataset.connecting;
      },

      /** The Console page's own header is hidden (dashboard.css): its add-on buttons (Explain) come into the console's
          title row as icon buttons before the size tools, and a click goes to the hidden button. They follow the header
          when Livewire changes it. */
      consoleActions(bar) {
        const header = document.querySelector('.fi-header');
        const build = () => {
          const sources = header?.isConnected ? headerActions(header) : [];
          const next = JSON.stringify(sources.map((el) => [actionLabel(el), el.disabled || el.getAttribute('aria-disabled') === 'true']));
          if (bar.dataset.actions === next) return;
          bar.dataset.actions = next;
          bar.querySelectorAll(':scope > .ps-console-action').forEach((el) => el.remove());
          const before = bar.querySelector('.ps-console-grow');
          for (const source of sources) {
            const text = actionLabel(source);
            const btn = Object.assign(document.createElement('button'), { type: 'button', className: 'ps-console-clear ps-console-tool ps-console-action' });
            btn.setAttribute('aria-label', text);
            btn.dataset.tip = tooltip(source) || text;
            btn.disabled = source.disabled || source.getAttribute('aria-disabled') === 'true';
            const icon = source.querySelector('svg');
            if (icon) btn.append(icon.cloneNode(true));
            else btn.textContent = text;
            btn.addEventListener('click', () => source.click());
            bar.insertBefore(btn, before);
          }
        };
        build();
        if (header && bar.actionsWatch !== header) {
          bar.actionsObserver?.disconnect();
          bar.actionsWatch = header;
          bar.actionsObserver = new MutationObserver(build);
          bar.actionsObserver.observe(header, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'aria-disabled', 'style', 'class'] });
        }
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
        const room = () => Math.floor(innerHeight - (main.getBoundingClientRect().top + scrollY) - footer - 14);
        // A low window (a server in host mode's frame) folds the numbers into one slim row, so the box keeps a usable height.
        root.classList.remove('ps-low');
        if (room() < 320) root.classList.add('ps-low');
        const height = Math.max(160, room());
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

      /** Minecraft: a row of common commands above Pelican's command line; a click puts one in the line to finish or send.
          It lives in the terminal's own box (wire:ignore): next to it, each redraw of Pelican's console took it out again. */
      quickCommands(terminal, label) {
        const input = document.getElementById('send-command');
        if (!this.server.minecraft || !input || input.readOnly || terminal.querySelector(':scope > .ps-quick-commands')) return;
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
        terminal.append(row);
      },

      /* Card ------------------------------------------------------------------------------------- */
      /** Like Pelican's own buttons: Stop while running, Force stop while it starts or stops (Wings refuses a stop then). */
      mainPower() {
        if (this.status === 'running') return { action: 'stop', label: t('dashboard.stop'), icon: 'stop', permission: 'control.stop' };
        if (['starting', 'stopping', 'restarting'].includes(this.status)) return { action: 'kill', label: t('dashboard.force_stop'), icon: 'kill', permission: 'control.stop' };
        if (this.firstStart()) return { action: 'first', label: t('dashboard.first_start'), icon: 'play', permission: 'control.start' };
        return { action: 'start', label: t('dashboard.start'), icon: 'play', permission: 'control.start' };
      },
      /** Restart works in every state but these, as in Pelican, and never while the server installs. */
      canRestart() {
        return !this.installView() && !['missing', 'installing', 'install failed', 'restoring'].includes(this.status);
      },
      async power(action) {
        if (this.busy) return;
        if (action === 'first') return this.firstStartServer();
        // Minecraft stops on an open EULA: the box in the card asks for it first.
        if (['start', 'restart'].includes(action) && this.eulaNeeded) return this.flashEula();
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
      /** Stopped, or unable to run (unknown to Wings, failed install, suspended): the same list as ServerPage::deleteServer(). */
      canDelete() {
        return ['offline', 'missing', 'install failed', 'suspended'].includes(this.status);
      },
      /** Owners delete their own stopped server after typing its name; the page then goes back to the overview. */
      async deleteServer() {
        if (this.busy) return;
        const name = await window.Simplify.confirm({ title: t('dashboard.delete_server_title', { name: this.server.name }), message: t('dashboard.delete_server_message'), confirmLabel: t('dashboard.delete_server'), tone: 'danger', input: true, requireText: this.server.name });
        if (name !== this.server.name) return;
        this.busy = true;
        try {
          await wire.deleteServer(name);
          this.toast(t('dashboard.server_deleted'), 'ok');
        } catch {
          this.toast(t('dashboard.the_action_could_not_be_completed'), 'danger');
          this.busy = false;
        }
      },
      async openBannerMenu() {
        this.infoMenu = false;
        this.colorMenu = false;
        this.bannerMenu = true;
        try {
          this.bannerChoices = await wire.banners();
        } catch {}
      },
      async chooseBanner(id) {
        if (this.busy) return;
        this.busy = true;
        try {
          this.take(await wire.chooseBanner(id));
          this.toast(t('dashboard.server_background_saved'), 'ok');
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
          if (file) this.bannerChoices = await wire.banners();
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
        const value = this.stats[{ cpu: 'cpu', ram: 'memory', disk: 'disk' }[key]];
        if (value == null) return '—';
        return key === 'cpu' ? Number(value).toFixed(1) + '%' : this.trafficBytes(value);
      },
      /** Download (rx) or upload (tx) per second, such as "1.4 MB/s". */
      rate(way) {
        return this.rates[way] == null ? '—' : this.trafficBytes(this.rates[way]) + '/s';
      },
      metricLimit(key) {
        const u = this.server.usage || {};
        if (key === 'network') return t('dashboard.traffic_used', { traffic: this.stats.rx == null || this.stats.tx == null ? '—' : '↓ ' + this.trafficBytes(this.stats.rx) + ' / ↑ ' + this.trafficBytes(this.stats.tx) });
        const cap = this.metricCap(key), share = this.metricShare(key);
        if (!cap) return t('dashboard.of_allocated', { amount: '∞' });
        const amount = key === 'cpu' ? cap + '%' : this.trafficBytes(cap);
        return share == null ? t('dashboard.of_allocated', { amount }) : t('dashboard.share_of', { percent: share < 0.01 ? '<1%' : Math.round(share * 100) + '%', amount });
      },
      /** What the server may use of a metric: CPU in %, memory and storage in bytes; null without a limit. */
      metricCap(key) {
        const u = this.server.usage || {};
        const cap = { cpu: u.cpu_limit, ram: u.memory_limit_bytes, disk: u.disk_limit_bytes }[key];
        return cap > 0 ? Number(cap) : null;
      },
      metricShare(key) {
        const value = this.stats[{ cpu: 'cpu', ram: 'memory', disk: 'disk' }[key]], cap = this.metricCap(key);
        return value == null || !cap ? null : value / cap;
      },
      /** The colour of the load, as on the overview cards (W4): calm below 60%, warning from 60%, alarm from 85%. */
      metricLevel(key) {
        const share = this.metricShare(key);
        return share == null ? '' : share >= 0.85 ? 'is-hot' : share >= 0.6 ? 'is-warn' : 'is-ok';
      },
      /** One bar per measurement from 0 to the limit; network: download (rx) up and upload (tx) down to their shared peak. */
      spark(key, newest = false) {
        const a = this.series[key], net = key === 'rx' || key === 'tx';
        if (!a.length) return '';
        const top = net ? Math.max(...this.series.rx, ...this.series.tx, 1) : this.metricCap(key) || Math.max(...a, 1);
        const base = net ? 15 : 28, room = net ? 13 : 25, sign = key === 'tx' ? 1 : -1, w = 100 / SERIES;
        let d = '';
        a.forEach((v, i) => {
          if ((i === a.length - 1) !== newest) return;
          const x = (SERIES - a.length + i + 1 - this.glide) * w;
          const h = Math.max(net ? 0.6 : 1, Math.min(1, v / top) * room);
          d += `M${(x + 0.6).toFixed(2)} ${base}v${(sign * h).toFixed(2)}h${(w - 1.2).toFixed(2)}V${base}z`;
        });
        return d;
      },
    };
  };
})();
