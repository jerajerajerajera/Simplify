/* Simplify overview (Alpine component). Server data arrives through Livewire ($wire) calls; everything else is
   local UI state. Dialogs, toasts and tooltips come from core.js (window.Simplify). */
(() => {
  // Texts from lang/<locale>/js.php (core.js); the key itself when core.js is missing.
  const t = (key, params) => (window.Simplify?.t ? window.Simplify.t(key, params) : key);
  const tc = (key, count, params) => (window.Simplify?.tc ? window.Simplify.tc(key, count, params) : key);
  const MINECRAFT_VERSION = /(?:^|[^0-9])((?:1\.[0-9]+(?:\.[0-9]+)?|2[6-9]\.[0-9]+(?:\.[0-9]+)?))(?![0-9])/;
  const OVERVIEW_DEFAULTS = { showMotd: false, largeIcon: 'server', largeName: 'alias', groupByNode: 'auto', showCpu: true, showRam: true, quickActions: 'hover', minecraftAlwaysGreen: true, fullWidth: false, showGroupsButton: true, columns: 'auto', cardWidth: 'normal', serverScope: 'all', halloween: true, healthPlace: 'top-bar' };
  const OVERVIEW_SWITCHES = ['showMotd', 'showCpu', 'showRam', 'minecraftAlwaysGreen', 'fullWidth', 'showGroupsButton', 'halloween'];
  // Overview settings that pick one of a fixed set of values.
  const OVERVIEW_CHOICES = { largeIcon: ['server', 'egg'], largeName: ['alias', 'name'], groupByNode: ['auto', 'always', 'off'], quickActions: ['off', 'on', 'hover'], columns: ['auto', '2', '3', '4', '5', '6'], cardWidth: ['narrow', 'normal', 'wide'], serverScope: ['all', 'mine', 'others'], healthPlace: ['top-bar', 'above', 'bottom'] };
  const OVERVIEW_VIEWS = ['grid', 'compact', 'list'];
  // Own server groups in the overview: at most this many groups, names at most this long.
  const GROUP_LIMIT = 30;
  const GROUP_NAME_LENGTH = 40;
  // Server order for the overview, and per group (Overview follows the overview's order, Manual uses the arrows).
  const SERVER_ORDERS = [{ key: 'name', label: t('dashboard.name') }, { key: 'created', label: t('dashboard.created') }, { key: 'port', label: t('dashboard.port') }];
  const GROUP_ORDERS = [{ key: 'overview', label: t('dashboard.same_as_the_overview') }, { key: 'manual', label: t('dashboard.my_own_order') }, ...SERVER_ORDERS.map((o) => ({ ...o, label: { name: t('dashboard.name_a_to_z'), created: t('dashboard.oldest_first'), port: t('dashboard.port_lowest_first') }[o.key] }))];
  const POWER_LABELS = { start: t('dashboard.start'), stop: t('dashboard.stop'), restart: t('dashboard.restart'), kill: t('dashboard.force_stop') };
  // core.js (loaded with the page) owns the contrast calculation.
  const accentInk = (color) => window.SimplifyTheme.accentInk(color);

  window.pelicanSimplify = function (wire, boot) {
    return {
      boot,
      wire,
      // The server page (later) and host mode share this component; the overview has neither.
      server: null,
      hostMode: false,
      hostTab: 'admin',
      hostMenuOpen: false,
      hostSection: null,
      hostPage: null,
      hostServer: null,
      busy: false,
      modal: null,
      refreshSeconds: Math.max(5, Number(boot.performance?.overview_seconds) || 15) * 1000,
      overviewDockObserver: null,
      appearanceMenu: false,
      notifyMenu: false,
      noticeHistory: [],
      feed: { items: [], page: 1, more: false, loading: false, unread: 0 },
      adminMenu: false,
      overviewCompact: false,
      phone: false,
      overviewPreferences: { ...OVERVIEW_DEFAULTS },
      customizeSections: null,
      overviewGroups: [],
      overviewGroupsTimer: null,
      overviewServerOrder: 'name',
      groupsMenu: false,
      groupEditing: null,
      powerConfirmation: null,
      healthExpanded: false,
      healthPopover: false,
      healthOpenNodes: [],
      healthRefreshing: false,
      hostMetrics: [
        { key: 'cpu', label: t('dashboard.cpu'), icon: 'cpu' },
        { key: 'memory', label: 'RAM', icon: 'ram' },
        { key: 'disk', label: t('dashboard.storage'), icon: 'disk' },
        { key: 'network', label: t('dashboard.network'), icon: 'traffic' },
      ],
      overviewPowerBusy: null,
      overviewNetwork: {},
      overviewHistory: {},
      overviewRefreshing: false,
      gameFilter: 'all',
      nodeFilter: 'all',
      nodeSearch: '',
      overviewView: typeof innerWidth !== 'undefined' && innerWidth <= 600 ? 'list' : 'grid',
      accountMenu: false,
      createMenu: false,
      navMenu: false,
      SERVER_ORDERS,
      GROUP_ORDERS,
      theme: 'system',
      themeDark: false,
      installable: '',
      uiScale: 1,
      destroyed: false,

      init() {
        this.initShell();
        this.restoreOverviewView();
        this.restoreHostMode();
        this.$nextTick(() => this.initOverviewDock());
        setTimeout(() => this.releaseBats(), 600);
        this.recordOverview(this.boot.servers);
        this.refreshTimer = setInterval(() => this.refreshOverview(), this.refreshSeconds);
        // Host health is asked right after first paint when no snapshot was ready, so Wings never holds up the page.
        if (this.boot.features?.host_health && !this.boot.host_health?.state) this.refreshOverview();
      },
      /** What the overview and the server page (server.js) share: scale, preferences, theme, notifications. */
      initShell() {
        this.applyScale();
        // Measure again once the page has its content.
        this.$nextTick(() => this.applyScale());
        this.restoreOverviewPreferences();
        this.overviewGroups = this.cleanOverviewGroups(this.boot.overview_groups?.groups);
        this.overviewServerOrder = SERVER_ORDERS.some((o) => o.key === this.boot.overview_groups?.order) ? this.boot.overview_groups.order : 'name';
        this.feed = { ...this.feed, ...this.boot.notifications };
        this.initTheme();
        this.noticeHistory = window.Simplify?.history?.() || [];
        this.noticeListener = (event) => (this.noticeHistory = [event.detail, ...this.noticeHistory].slice(0, 20));
        window.addEventListener('simplify:notice', this.noticeListener);
        this.applyFavicon();
        this.enhanceCopyright();
      },
      destroy() {
        this.destroyed = true;
        this.overviewDockObserver?.disconnect();
        this.restoreFavicon();
        clearInterval(this.refreshTimer);
        window.removeEventListener('simplify:theme', this.themeListener);
        window.removeEventListener('simplify:notice', this.noticeListener);
        window.removeEventListener('simplify:installable', this.installListener);
      },
      initTheme() {
        this.theme = window.SimplifyTheme?.preference() || 'system';
        this.themeListener = (e) => {
          this.theme = e.detail.preference;
          this.themeDark = e.detail.dark;
        };
        window.addEventListener('simplify:theme', this.themeListener);
        this.applyTheme();
        // "Install app" in the panel menu on phones and tablets, only while the browser can add the panel to the home screen.
        this.installListener = () => { this.installable = matchMedia('(pointer: coarse)').matches ? window.SimplifyApp?.installable() || '' : ''; };
        window.addEventListener('simplify:installable', this.installListener);
        this.installListener();
      },

      /* Notifications ------------------------------------------------------------------------ */
      /** Pelican's notifications, with a host health alert on top while a node needs attention. */
      notifications() {
        const health = this.boot.host_health;
        const alert = ['warning', 'critical'].includes(health?.state)
          ? [{ id: 'health', tone: health.state === 'critical' ? 'danger' : 'warn', title: t('dashboard.host_health_title', { state: health.label || t('dashboard.attention_needed') }), text: tc('dashboard.hosts_reporting', health.hosts ?? 0, { available: health.available ?? 0 }) }]
          : [];
        return [...alert, ...this.feed.items];
      },
      unreadNotifications() {
        return this.notifications().filter((item) => !item.pelican).length + (Number(this.feed.unread) || 0);
      },
      async toggleNotifications() {
        this.notifyMenu = !this.notifyMenu;
        this.appearanceMenu = this.groupsMenu = this.accountMenu = false;
        if (this.notifyMenu) await this.loadNotificationPage(true);
      },
      /** The first (reset) or next page of Pelican's notifications, read ones included. */
      async loadNotificationPage(reset = false) {
        if (this.feed.loading) return;
        const page = reset ? 1 : this.feed.page + 1;
        this.feed.loading = true;
        try {
          const result = await wire.notificationPage(page);
          const known = new Set(reset ? [] : this.feed.items.map((n) => n.key));
          this.feed = { items: reset ? result.items : [...this.feed.items, ...result.items.filter((n) => !known.has(n.key))], page, more: !!result.more, unread: result.unread, loading: false };
        } catch {
          this.feed.loading = false;
          if (!reset) this.toast(t('dashboard.older_notifications_could_not_be_loaded'), 'warn');
        }
      },
      /** Marks the given notifications (all of them for []) read or unread again. */
      async markNotifications(keys, read) {
        try {
          const result = await wire.markNotifications(keys, read);
          this.feed = { ...result, page: 1, loading: false };
        } catch {
          this.toast(t('dashboard.the_notification_could_not_be_marked'), 'warn');
        }
      },

      /* Groups ------------------------------------------------------------------------------- */
      /** Shows the change at once and saves it shortly after, so quick clicks become one save. */
      saveOverviewGroups(groups) {
        this.overviewGroups = groups;
        clearTimeout(this.overviewGroupsTimer);
        this.overviewGroupsTimer = setTimeout(async () => {
          try {
            await wire.saveOverviewGroups(this.overviewGroups, this.overviewServerOrder);
          } catch {
            this.toast(t('dashboard.your_groups_could_not_be_saved'), 'danger');
          }
        }, 400);
      },

      /* Admin menu ----------------------------------------------------------------------------- */
      /** Pelican's admin pages in a few colour-coded sections; pages from other plugins go to Other plugins. */
      hostSections() {
        const plan = [
          { key: 'servers', label: t('dashboard.admin_groups.servers'), icon: 'host', color: '#5fb87a', pages: ['serverresource', 'eggresource', 'mountresource'] },
          { key: 'nodes', label: t('dashboard.admin_groups.nodes'), icon: 'monitor', color: '#5b9cf0', pages: ['noderesource', 'databasehostresource', 'backuphostresource'] },
          { key: 'people', label: t('dashboard.admin_groups.people'), icon: 'players', color: '#a78bfa', pages: ['userresource', 'roleresource', 'people'] },
          { key: 'integrations', label: t('dashboard.admin_groups.integrations'), icon: 'link', color: '#f0a35b', pages: ['apikeyresource', 'webhookresource'] },
          { key: 'panel', label: t('dashboard.admin_groups.panel'), icon: 'gear', color: '#94a3b8', pages: ['settings', 'pluginresource', 'listlogs'] },
          { key: 'extensions', label: t('dashboard.other_plugins'), icon: 'plugins', color: '#2dd4bf', pages: [] },
          { key: 'health', label: t('dashboard.admin_groups.health'), icon: 'heart', color: '#f87171', pages: ['health'] },
        ];
        const items = this.adminPages().flatMap((group) => group.items);
        const known = new Set(plan.flatMap((section) => section.pages));
        return plan.map((section) => ({
          ...section,
          pages: section.key === 'extensions' ? items.filter((item) => !known.has(item.key)) : section.pages.map((key) => items.find((item) => item.key === key)).filter(Boolean),
        })).filter((section) => section.pages.length);
      },
      adminHealthPage() {
        return this.adminPages().flatMap((group) => group.items).find((item) => item.key === 'health') || null;
      },
      /** Latest results of Pelican's health checks (admins only); empty until the checks have run once. */
      healthChecks() {
        return this.boot.health_checks || [];
      },

      /* Host mode -------------------------------------------------------------------------------- */
      /** Host mode: an admin's choice on this device; otherwise on for hosting panels with more than one node. */
      hostModeKey() {
        return 'pelicanSimplify.hostMode.' + encodeURIComponent(this.boot.user || 'default');
      },
      restoreHostMode() {
        if (!this.adminPages().length) return;
        let saved = null, last = {};
        try {
          saved = localStorage.getItem(this.hostModeKey());
          // Back from a server: reopen the section and page that were open (this browser tab only).
          last = JSON.parse(sessionStorage.getItem(this.hostModeKey() + '.place') || '{}') || {};
        } catch {}
        this.hostMode = saved === null ? !!this.boot.host_mode_auto && this.nodeOptions().length > 1 : saved === '1';
        if (!this.hostMode) return;
        const sections = this.hostSections();
        const section = sections.find((x) => x.key === last.section) || sections.find((x) => x.key === 'health') || sections[0];
        this.openHostSection(section);
        const page = section?.pages.find((item) => item.key === last.page);
        if (page) this.openHostLeaf(section, page);
      },
      setHostMode(on) {
        this.hostMode = !!on && this.adminPages().length > 0;
        if (this.hostMode && !this.hostPage) this.openHostSection(this.hostSections().find((x) => x.key === 'health') || this.hostSections()[0]);
        try {
          localStorage.setItem(this.hostModeKey(), this.hostMode ? '1' : '0');
        } catch {}
      },
      /** A section opens on its first page; a page picked in the tree opens with its section. */
      openHostSection(section) {
        if (section) this.openHostLeaf(section, section.pages[0]);
      },
      openHostLeaf(section, item) {
        this.hostSection = section;
        this.hostPage = item || null;
        this.hostServer = null;
        this.hostMenuOpen = false;
        try {
          sessionStorage.setItem(this.hostModeKey() + '.place', JSON.stringify({ section: section?.key || null, page: item?.key || null }));
        } catch {}
      },
      hostBranchOpen(section) {
        return section.pages.length > 1;
      },
      openHostServer(s) {
        this.hostServer = s;
        this.hostMenuOpen = false;
      },
      closeHostServer() {
        this.hostServer = null;
      },
      hostServerVersion(s) {
        const version = String(s.reported_version || '').trim();
        const software = String(s.software || s.game_label || '').trim();
        return version && !version.toLowerCase().includes(software.toLowerCase()) ? (software ? software + ' · ' + version : version) : version || software || '—';
      },
      hostNodes() {
        return this.nodeOptions().map((node) => ({ ...node, servers: (this.boot.servers || []).filter((s) => Number(s.node_id) === node.id) })).filter((node) => node.servers.length);
      },
      /** Same-origin URL with the embed marker, or '' for anything else. A framed page drops its own menus (head.blade.php). */
      frameUrl(href) {
        try {
          const url = new URL(href, location.origin);
          if (url.origin !== location.origin) return '';
          url.searchParams.set('simplify_embed', '1');
          return url.href;
        } catch {
          return '';
        }
      },
      /** Create buttons for the top bar or the toolbar: the add-ons' buttons, the main one first. */
      /** Add-on buttons first (the primary one leads), then Pelican's own form; the first one reads "Create server". */
      createButtons() {
        const buttons = [...(this.boot.create || [])].sort((a, b) => b.primary - a.primary);
        return buttons.map((button, index) => ({ ...button, primary: index === 0, label: index === 0 ? t('dashboard.create_server') : button.native ? t('dashboard.new_server') : button.label }));
      },

      /** 25 October to 2 November, every year. */
      halloweenSeason(date = new Date()) {
        const month = date.getMonth(), day = date.getDate();
        return (month === 9 && day >= 25) || (month === 10 && day <= 2);
      },
      /** The Halloween touches show in season, unless the panel or this person turned them off. */
      halloween(date = new Date()) {
        return this.boot.appearance?.seasonal !== false && this.overviewPreferences.halloween !== false && this.halloweenSeason(date);
      },
      /** A few bats leave one server card when the overview opens, once per visit. */
      releaseBats() {
        if (this.server || !this.halloween() || typeof document === 'undefined' || !document.body.animate) return;
        if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
        try {
          if (sessionStorage.getItem('pelicanSimplify.bats')) return;
          sessionStorage.setItem('pelicanSimplify.bats', '1');
        } catch {}
        const card = [...this.$root.querySelectorAll('.ps-server-card')].map((el) => el.getBoundingClientRect()).find((r) => r.width > 80 && r.top >= 0 && r.bottom <= innerHeight);
        if (!card) return;
        const ink = getComputedStyle(this.$root).getPropertyValue('--ps-ink').trim();
        [[0, .62, -1], [180, .5, .3], [340, .72, 1]].forEach(([delay, across, side], i) => {
          const bat = document.createElement('span');
          bat.className = 'ps-hw-bat';
          bat.setAttribute('aria-hidden', 'true');
          bat.style.color = ink;
          bat.innerHTML = '<svg viewBox="0 0 44 22"><path d="M22 6c1-2 2-3 2-3l1 3c2 0 3 1 3 2 3-3 8-5 14-4-3 2-4 5-4 8-2-2-5-2-7 0-1-2-3-3-5-2l-2 6-2-6c-2-1-4 0-5 2-2-2-5-2-7 0 0-3-1-6-4-8 6-1 11 1 14 4 0-1 1-2 3-2l1-3s1 1 2 3z"/></svg>';
          document.body.appendChild(bat);
          const x = card.left + card.width * across - 20, y = card.top + card.height * .35;
          const dx = side * (120 + i * 40) + 60, dy = -(y + 60);
          bat.animate([
            { transform: `translate(${x}px, ${y}px) scale(.4)`, opacity: 0 },
            { transform: `translate(${x + dx * .15}px, ${y - 40}px) scale(.9)`, opacity: .85, offset: .15 },
            { transform: `translate(${x + dx * .55}px, ${y + dy * .5 + 20}px)`, opacity: .85, offset: .55 },
            { transform: `translate(${x + dx}px, ${y + dy}px) scale(.8)`, opacity: 0 },
          ], { duration: 2400 + i * 250, delay: delay + 300, easing: 'cubic-bezier(.3, .6, .4, 1)', fill: 'both' }).finished.then(() => bat.remove(), () => bat.remove());
        });
      },
      accentStyle(id) {
        // A colour on one of your own groups wins for its servers (and its heading), over the common and the server's own colour.
        const group = String(id).startsWith('group-') ? this.overviewGroups.find((g) => 'group-' + g.id === id) : this.serverGroup(id);
        if (group?.color) return { '--ps-accent': group.color, '--ps-on-accent': accentInk(group.color) };
        if (String(id).startsWith('group-')) return {};
        const appearance = this.boot.appearance || {};
        const common = appearance.accent_color;
        // Halloween turns the overview's neutral accent orange; a panel accent of your own stays.
        if (id === 'overview' && this.halloween() && (common || '#878787').toLowerCase() === '#878787')
          return { '--ps-accent': this.themeDark ? '#f0a35a' : '#c2621a', '--ps-on-accent': this.themeDark ? '#111118' : '#ffffff' };
        if (/^#[0-9a-f]{6}$/i.test(common || '') && (id === 'overview' || appearance.individual_colors === false))
          return { '--ps-accent': common, '--ps-on-accent': accentInk(common) };
        if (id === 'overview') return { '--ps-accent': this.themeDark ? '#c0c0c5' : '#55555f', '--ps-on-accent': this.themeDark ? '#111118' : '#ffffff' };
        const color = (this.server?.uuid === id ? this.server : this.boot.servers?.find((s) => s.uuid === id))?.color;
        if (typeof color === 'string' && /^#[0-9a-f]{6}$/i.test(color)) return { '--ps-accent': color, '--ps-on-accent': accentInk(color) };
        let hash = 2166136261;
        for (const c of String(id)) hash = Math.imul(hash ^ c.charCodeAt(0), 16777619) >>> 0;
        return { '--ps-accent': `hsl(${hash % 360} 68% 76%)`, '--ps-on-accent': '#111118' };
      },
      applyScale() {
        if (typeof window === 'undefined' || !this.$root?.style) return;
        const root = this.$root;
        root.style.zoom = '';
        let scale = 1;
        if (window.innerWidth > 900 && root.scrollWidth > root.clientWidth + 1)
          scale = Math.max(0.75, Math.floor((root.clientWidth / root.scrollWidth) * 100) / 100);
        this.uiScale = scale;
        root.style.zoom = scale === 1 ? '' : String(scale);
        document.documentElement.style.setProperty('--ps-ui-scale', String(scale));
      },
      brand() {
        return this.boot.appearance?.brand || {};
      },
      brandStyle() {
        const common = this.boot.appearance?.accent_color;
        if (/^#[0-9a-f]{6}$/i.test(common || '')) return { '--ps-brand': common };
        return this.server ? {} : { '--ps-brand': this.themeDark ? '#60a5fa' : '#2563eb' };
      },
      async installApp() {
        this.navMenu = false;
        await window.SimplifyApp?.install();
        this.installListener?.();
      },
      setTheme(value) {
        window.SimplifyTheme?.set(value);
        this.theme = value;
        this.applyTheme();
      },
      applyTheme() {
        this.themeDark = window.SimplifyTheme?.dark() ?? false;
      },
      toast(message, tone = 'info') {
        this.notice = String(message || '');
        window.Simplify?.toast(this.notice, tone);
      },
      enhanceCopyright() {
        if (!document.querySelectorAll) return;
        for (const footer of document.querySelectorAll('footer,.fi-footer')) {
          if (footer.closest('.ps-modal') || footer.querySelector('[data-ps-attribution]') || !/©|copyright/i.test(footer.textContent)) continue;
          footer.setAttribute('data-ps-footer', '');
          const wrapper = footer.parentElement;
          if (wrapper && wrapper.children.length === 1 && !wrapper.matches('body,.fi-layout,.fi-main-ctn')) wrapper.setAttribute('data-ps-footer-wrapper', '');
          const line = [...(footer.querySelectorAll?.('p,div') || [])].reverse().find((el) => /©|copyright/i.test(el.textContent)) || footer;
          line.setAttribute('data-ps-copyright-line', '');
          const credit = document.createElement('span');
          credit.dataset.psAttribution = '';
          credit.append(document.createTextNode(' - ' + t('dashboard.enhanced_by') + ' '));
          const link = document.createElement('a');
          link.href = 'https://github.com/jerajerajerajera/Simplify';
          link.className = 'ps-project-link';
          link.textContent = 'Simplify';
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          credit.append(link);
          if (this.boot.build_version) credit.append(document.createTextNode(' · ' + t('dashboard.build', { version: this.boot.build_version })));
          line.append(credit);
        }
      },
      applyFavicon() {
        if (!this.boot.favicon_url || !document.head) return;
        this.previousFavicons = [...document.head.querySelectorAll('link[rel~="icon"]')];
        this.previousFavicons.forEach((el) => el.remove());
        this.favicon = document.createElement('link');
        this.favicon.rel = 'icon';
        this.favicon.type = 'image/svg+xml';
        this.favicon.href = this.boot.favicon_url;
        document.head.append(this.favicon);
      },
      restoreFavicon() {
        if (!this.favicon) return;
        this.favicon.remove();
        this.previousFavicons?.forEach((el) => document.head.append(el));
        this.favicon = null;
      },
      playerCount(s) {
        if (s?.status === 'offline') return '0 / 0';
        const count = Number(s?.players), max = Number(s?.max_players);
        if (s?.players == null || !Number.isFinite(count) || count < 0) return '—';
        return Math.floor(count) + ' / ' + (s?.max_players != null && Number.isFinite(max) && max >= 0 ? Math.floor(max) : 0);
      },
      openHostHealth() {
        if (this.server || this.busy || this.modal || !this.boot.features?.host_health) return;
        this.healthExpanded = !this.healthExpanded;
        this.healthOpenNodes = [];
      },
      healthNodes() {
        return this.boot.host_health?.nodes || [];
      },
      healthNodeOpen(node) {
        return this.healthOpenNodes.some((id) => String(id) === String(node.id));
      },
      toggleHealthNode(node) {
        if (!this.healthExpanded || this.server || this.busy || this.modal || !this.boot.features?.host_health || !this.healthNodes().some((n) => String(n.id) === String(node.id))) return;
        this.healthOpenNodes = this.healthNodeOpen(node) ? this.healthOpenNodes.filter((id) => String(id) !== String(node.id)) : [...this.healthOpenNodes, node.id];
      },
      nodeServers(node) {
        return (this.boot.servers || []).filter((s) => Number(s.node_id) === Number(node.id));
      },
      hostRate(h = this.boot.host_health) {
        return this.rateLabel(h?.network_rx_per_second, h?.network_tx_per_second);
      },
      rateLabel(rx, tx) {
        return rx == null || tx == null ? '—' : '↓ ' + this.trafficBytes(rx) + '/s / ↑ ' + this.trafficBytes(tx) + '/s';
      },
      serverRate(s) {
        const n = this.overviewNetwork[s.uuid];
        return this.rateLabel(n?.rx_per_second, n?.tx_per_second);
      },
      serverTraffic(s) {
        return s.usage?.network_rx_bytes == null || s.usage?.network_tx_bytes == null ? '—' : '↓ ' + this.trafficBytes(s.usage.network_rx_bytes) + ' / ↑ ' + this.trafficBytes(s.usage.network_tx_bytes);
      },
      networkSample(current, at, previous) {
        const rx = Number(current?.rx_bytes), tx = Number(current?.tx_bytes);
        if (current?.rx_bytes == null || current?.tx_bytes == null || !Number.isFinite(rx) || !Number.isFinite(tx) || rx < 0 || tx < 0) return null;
        const dt = at - (previous?.at ?? at), ready = previous && dt > 0 && dt <= 120;
        const reset = ready && (rx < previous.rx || tx < previous.tx);
        return { rx, tx, at, rx_per_second: ready ? (reset ? 0 : (rx - previous.rx) / dt) : null, tx_per_second: ready ? (reset ? 0 : (tx - previous.tx) / dt) : null };
      },
      powerActionLabel(action) {
        return POWER_LABELS[action] || '';
      },
      canOverviewPower(s, action) {
        const permission = { start: 'control.start', stop: 'control.stop', kill: 'control.stop', restart: 'control.restart' }[action];
        return this.quickActionsMode() !== 'off' && !!permission && !!s.linked && !!s.permissions?.[permission]
          && !['installing', 'install failed', 'restoring'].includes(s.status)
          && (action !== 'start' || s.status === 'offline')
          && (!['stop', 'kill', 'restart'].includes(action) || s.status !== 'offline');
      },
      async overviewPower(s, action, approved = false) {
        if (this.overviewPowerBusy || !this.canOverviewPower(s, action)) return;
        if (!approved) {
          this.powerConfirmation = { uuid: s.uuid, action };
          return;
        }
        if (this.powerConfirmation?.uuid !== s.uuid || this.powerConfirmation?.action !== action) return;
        this.powerConfirmation = null;
        this.overviewPowerBusy = s.uuid;
        try {
          await wire.overviewPower(s.uuid, action);
          this.toast(t('dashboard.power_sent', { action: POWER_LABELS[action], server: s.alias || s.name }), 'ok');
          await this.refreshOverview();
        } catch (error) {
          this.toast(error?.message || t('dashboard.the_power_action_could_not_be'), 'danger');
        } finally {
          this.overviewPowerBusy = null;
        }
      },
      /** Large and secondary card names, following the "Large name" preference. */
      cardNames(s) {
        if (!s.alias) return { large: s.name, small: '' };
        return this.overviewPreferences.largeName === 'name' ? { large: s.name, small: s.alias } : { large: s.alias, small: s.name };
      },
      fitCardTitle(row, name) {
        const title = row.querySelector('h3');
        if (!title) return;
        const base = { list: 21, compact: 18 }[this.overviewView] || 23;
        title.style.fontSize = base + 'px';
        if (this.overviewView !== 'list') {
          // Grid: the name may use two lines; only a name that still does not fit gets smaller.
          let size = base;
          while (size > 12 && (title.scrollHeight > title.clientHeight + 1 || title.scrollWidth > title.clientWidth + 1)) title.style.fontSize = --size + 'px';
          return;
        }
        const original = row.querySelector('.ps-card-real-name');
        const reserve = this.overviewView === 'list' && typeof innerWidth !== 'undefined' && innerWidth > 700 && original && !original.classList.contains('no-alias') ? original.getBoundingClientRect().width + 8 : 0;
        const available = Math.max(0, row.getBoundingClientRect().width - reserve);
        // Measure the text itself: scrollWidth rounds, so a name a fraction too wide would get "…" instead of fitting.
        let needed = title.scrollWidth;
        try {
          const range = document.createRange();
          range.selectNodeContents(title);
          needed = range.getBoundingClientRect().width;
        } catch {}
        if (available > 0 && needed > available) title.style.fontSize = Math.max(10, Math.floor((base * available) / needed * 10) / 10) + 'px';
      },
      /** Shrinks a MOTD box's text only as far as needed to show both full lines, never below 8px. */
      fitMotd(box) {
        if (!box) return;
        box.style.fontSize = '';
        let size = parseFloat(getComputedStyle(box).fontSize) || 12;
        while (size > 8 && (box.scrollHeight > box.clientHeight + 1 || box.scrollWidth > box.clientWidth + 1)) box.style.fontSize = (size -= .5) + 'px';
      },
      softwareTooltip(s) {
        const software = String(s.software || t('dashboard.server_software')).trim();
        const raw = String(s.reported_version || s.minecraft_version || '').trim();
        const version = s.game === 'minecraft' ? raw.match(MINECRAFT_VERSION)?.[1] || '' : raw;
        const label = software + (version ? ' · ' + version : '');
        return s.status === 'running' ? t('dashboard.running_software', { software: label }) : label;
      },
      gameLabel(s) {
        return s.game === 'minecraft' ? 'Minecraft' : String(s.game_label || t('dashboard.other_game')).trim() || t('dashboard.other_game');
      },
      gameKey(s) {
        return this.gameLabel(s).toLocaleLowerCase();
      },
      gameOptions() {
        const games = new Map();
        for (const s of this.boot.servers || []) games.set(this.gameKey(s), this.gameLabel(s));
        return [...games].map(([key, label]) => ({ key, label })).sort((a, b) => a.label.localeCompare(b.label));
      },
      visibleServers() {
        const scope = this.ownerFilterShown() ? this.overviewPreferences.serverScope : 'all';
        return (this.boot.servers || []).filter((s) => (this.gameFilter === 'all' || this.gameKey(s) === this.gameFilter) && (this.nodeFilter === 'all' || Number(s.node_id) === this.nodeFilter) && (scope === 'all' || !!s.mine === (scope === 'mine')));
      },
      /** My servers / Others only means something when the overview holds both (administrators, or everyone in friends mode). */
      ownerFilterShown() {
        const servers = this.boot.servers || [];
        return servers.some((s) => s.mine) && servers.some((s) => !s.mine);
      },
      /** Nodes that have servers in the overview (names from the listing, else the health data). */
      nodeOptions() {
        const names = new Map((this.boot.host_health?.nodes || []).map((n) => [Number(n.id), n.name]));
        const nodes = new Map();
        for (const s of this.boot.servers || []) {
          const id = Number(s.node_id);
          if (!nodes.has(id)) nodes.set(id, { id, name: s.node_name || names.get(id) || t('dashboard.node_number', { id }) });
        }
        return [...nodes.values()].sort((a, b) => a.name.localeCompare(b.name));
      },
      groupedByNode() {
        const mode = this.overviewPreferences.groupByNode;
        return mode === 'always' || (mode === 'auto' && this.nodeOptions().length > 1);
      },
      /** Servers, with a heading row before each own group, then each node when grouping by node is on. */
      overviewItems() {
        // The listing already comes sorted by name.
        const servers = this.overviewServerOrder === 'name' ? this.visibleServers() : this.sortServers(this.visibleServers(), this.overviewServerOrder);
        const items = [], placed = new Set();
        for (const group of this.overviewGroups) {
          const members = this.groupMembers(group, servers).filter((s) => !placed.has(s.uuid));
          if (!members.length) continue;
          members.forEach((s) => placed.add(s.uuid));
          items.push({ isNodeHeader: true, icon: 'files', uuid: 'group-' + group.id, name: group.name, count: members.length, color: group.color || null }, ...members);
        }
        const rest = servers.filter((s) => !placed.has(s.uuid));
        if (this.groupedByNode()) {
          for (const node of this.nodeOptions()) {
            const members = rest.filter((s) => Number(s.node_id) === node.id);
            if (members.length) items.push({ isNodeHeader: true, uuid: 'node-' + node.id, name: node.name, count: members.length }, ...members);
          }
          return items;
        }
        if (items.length && rest.length) items.push({ isNodeHeader: true, icon: 'grid', uuid: 'group-other', name: t('dashboard.other_servers'), count: rest.length });
        return items.concat(rest);
      },
      /** Name sorts by server name (A to Z, like Pelican's list); Created is oldest first and Port lowest first, then by name. */
      sortServers(list, order) {
        const key = { created: 'created', port: 'port' }[order];
        const name = (a, b) => String(a.name || '').localeCompare(String(b.name || ''));
        if (!key) return order === 'name' ? [...list].sort(name) : list;
        const value = (s) => (s[key] !== null && s[key] !== '' && Number.isFinite(Number(s[key])) ? Number(s[key]) : Infinity);
        return [...list].sort((a, b) => value(a) - value(b) || name(a, b));
      },
      /** A group's servers among list (already in the overview's order), in the group's own order. */
      groupMembers(group, list) {
        const members = list.filter((s) => group.servers.includes(s.uuid));
        if (group.sort === 'manual') return members.sort((a, b) => group.servers.indexOf(a.uuid) - group.servers.indexOf(b.uuid));
        return group.sort === 'overview' ? members : this.sortServers(members, group.sort);
      },
      /** A server sits in at most one group; the server checks the same rules. */
      cleanOverviewGroups(list) {
        const groups = [], seen = new Set();
        for (const group of Array.isArray(list) ? list : []) {
          if (groups.length >= GROUP_LIMIT || !group || typeof group.id !== 'string' || groups.some((g) => g.id === group.id)) continue;
          const name = String(group.name || '').trim().slice(0, GROUP_NAME_LENGTH) || t('dashboard.group');
          const servers = (Array.isArray(group.servers) ? group.servers : []).filter((uuid) => typeof uuid === 'string' && !seen.has(uuid) && seen.add(uuid));
          const sort = GROUP_ORDERS.some((o) => o.key === group.sort) ? group.sort : 'overview';
          const color = typeof group.color === 'string' && /^#[0-9a-f]{6}$/i.test(group.color) ? group.color.toLowerCase() : null;
          groups.push({ id: group.id.slice(0, 40), name, servers, sort, ...(color ? { color } : {}) });
        }
        return groups;
      },
      setServerOrder(order) {
        if (!SERVER_ORDERS.some((o) => o.key === order) || order === this.overviewServerOrder) return;
        this.overviewServerOrder = order;
        this.saveOverviewGroups(this.overviewGroups);
      },
      setGroupOrder(id, order) {
        if (!GROUP_ORDERS.some((o) => o.key === order)) return;
        const group = this.overviewGroups.find((g) => g.id === id);
        if (!group || group.sort === order) return;
        // Switching to My own order starts from the order shown right now.
        const servers = order === 'manual' ? [...this.groupMembers(group, this.sortServers(this.boot.servers || [], this.overviewServerOrder)).map((s) => s.uuid), ...group.servers.filter((uuid) => !(this.boot.servers || []).some((s) => s.uuid === uuid))] : group.servers;
        this.saveOverviewGroups(this.overviewGroups.map((g) => (g.id === id ? { ...g, sort: order, servers } : g)));
      },
      /** Arrows in My own order: swaps a server with the one shown above or below it. */
      moveGroupServer(id, uuid, step) {
        const group = this.overviewGroups.find((g) => g.id === id);
        if (!group || group.sort !== 'manual') return;
        const shown = this.groupMembers(group, this.boot.servers || []), at = shown.findIndex((s) => s.uuid === uuid), other = shown[at + step];
        if (at < 0 || !other) return;
        const servers = [...group.servers], a = servers.indexOf(uuid), b = servers.indexOf(other.uuid);
        [servers[a], servers[b]] = [servers[b], servers[a]];
        this.saveOverviewGroups(this.overviewGroups.map((g) => (g.id === id ? { ...g, servers } : g)));
      },
      /** The group's servers first, in its order, then every other server by name. */
      groupServerList(group) {
        const all = this.boot.servers || [];
        const members = this.groupMembers(group, this.sortServers(all, this.overviewServerOrder));
        const others = all.filter((s) => !group.servers.includes(s.uuid)).sort((a, b) => this.cardNames(a).large.localeCompare(this.cardNames(b).large));
        return [...members, ...others];
      },
      addOverviewGroup() {
        if (this.overviewGroups.length >= GROUP_LIMIT) return;
        let number = this.overviewGroups.length + 1;
        while (this.overviewGroups.some((g) => g.name === t('dashboard.group_number', { number }))) number++;
        const id = 'g' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        this.saveOverviewGroups([...this.overviewGroups, { id, name: t('dashboard.group_number', { number }), servers: [], sort: 'overview' }]);
        this.groupEditing = id;
      },
      renameOverviewGroup(id, name) {
        const clean = String(name || '').trim().slice(0, GROUP_NAME_LENGTH);
        // An empty name keeps the old one (the field shows it again).
        this.saveOverviewGroups(this.overviewGroups.map((g) => (g.id === id && clean ? { ...g, name: clean } : { ...g })));
        return this.overviewGroups.find((g) => g.id === id)?.name || '';
      },
      removeOverviewGroup(id) {
        this.saveOverviewGroups(this.overviewGroups.filter((g) => g.id !== id));
        if (this.groupEditing === id) this.groupEditing = null;
      },
      moveOverviewGroup(id, step) {
        const groups = [...this.overviewGroups], from = groups.findIndex((g) => g.id === id), to = from + step;
        if (from < 0 || to < 0 || to >= groups.length) return;
        [groups[from], groups[to]] = [groups[to], groups[from]];
        this.saveOverviewGroups(groups);
      },
      /** Puts a server in this group (taking it out of any other), or takes it out again. */
      toggleGroupServer(id, uuid) {
        const inGroup = this.overviewGroups.some((g) => g.id === id && g.servers.includes(uuid));
        this.saveOverviewGroups(this.overviewGroups.map((g) => {
          const servers = g.servers.filter((x) => x !== uuid);
          return { ...g, servers: g.id === id && !inGroup ? [...servers, uuid] : servers };
        }));
      },
      /** A group's colour, or null to go back to each server's own colour. */
      setGroupColor(id, color) {
        const clean = typeof color === 'string' && /^#[0-9a-f]{6}$/i.test(color) ? color.toLowerCase() : null;
        this.saveOverviewGroups(this.overviewGroups.map((g) => {
          if (g.id !== id) return { ...g };
          const { color: _old, ...rest } = g;
          return clean ? { ...rest, color: clean } : rest;
        }));
      },
      serverGroup(uuid) {
        return this.overviewGroups.find((g) => g.servers.includes(uuid)) || null;
      },
      /** Servers in a group that still exist (a removed server simply stops counting). */
      groupCount(group) {
        return (this.boot.servers || []).filter((s) => group.servers.includes(s.uuid)).length;
      },
      setGameFilter(key) {
        if (key === 'all' || this.gameOptions().some((g) => g.key === key)) this.gameFilter = key;
      },
      overviewPreferencesKey() {
        return 'pelicanSimplify.overviewAppearance.' + encodeURIComponent(this.boot.user || 'default');
      },
      restoreOverviewPreferences() {
        const preferences = { ...OVERVIEW_DEFAULTS };
        try {
          const saved = JSON.parse(localStorage.getItem(this.overviewPreferencesKey()) || 'null');
          if (saved && typeof saved === 'object') {
            for (const key of OVERVIEW_SWITCHES) if (typeof saved[key] === 'boolean') preferences[key] = saved[key];
            // Before Off/On/Hover, quick actions were a switch that meant "on hover".
            if (typeof saved.showQuickActions === 'boolean') preferences.quickActions = saved.showQuickActions ? 'hover' : 'off';
            for (const [key, values] of Object.entries(OVERVIEW_CHOICES)) if (values.includes(saved[key])) preferences[key] = saved[key];
          }
        } catch {}
        this.overviewPreferences = preferences;
      },
      /** Customize menus fold their sections; the first stays open by default and each choice is remembered on this device. */
      customizeOpen(key) {
        if (!this.customizeSections) {
          let saved = {};
          try {
            saved = JSON.parse(localStorage.getItem('ps.customize.sections') || '{}') || {};
          } catch {}
          this.customizeSections = typeof saved === 'object' ? saved : {};
        }
        return key in this.customizeSections ? !!this.customizeSections[key] : key.endsWith('.layout');
      },
      toggleCustomize(key) {
        this.customizeSections = { ...this.customizeSections, [key]: !this.customizeOpen(key) };
        try {
          localStorage.setItem('ps.customize.sections', JSON.stringify(this.customizeSections));
        } catch {}
      },
      setOverviewPreference(key, value) {
        if (OVERVIEW_SWITCHES.includes(key)) {
          if (typeof value !== 'boolean') return;
        } else if (!(OVERVIEW_CHOICES[key] || []).includes(value)) return;
        this.overviewPreferences = { ...this.overviewPreferences, [key]: value };
        if (key === 'quickActions' && value === 'off') this.powerConfirmation = null;
        try {
          localStorage.setItem(this.overviewPreferencesKey(), JSON.stringify(this.overviewPreferences));
        } catch {}
      },
      /** Off, on (always shown) or hover. A touch screen cannot hover, so there Hover shows them like On. */
      quickActionsMode() {
        const mode = this.overviewPreferences.quickActions;
        if (mode !== 'hover') return mode === 'on' ? 'on' : 'off';
        return typeof matchMedia !== 'undefined' && matchMedia('(hover: none)').matches ? 'on' : 'hover';
      },
      overviewMetrics() {
        return [
          { key: 'cpu', label: t('dashboard.cpu'), icon: 'cpu', show: this.overviewPreferences.showCpu },
          { key: 'ram', label: 'RAM', icon: 'ram', show: this.overviewPreferences.showRam },
        ].filter((metric) => metric.show);
      },
      overviewViewKey() {
        const phone = typeof innerWidth !== 'undefined' && innerWidth <= 600;
        return 'pelicanSimplify.overviewView.' + (phone ? 'phone.' : '') + encodeURIComponent(this.boot.user || 'default');
      },
      restoreOverviewView() {
        const phone = typeof innerWidth !== 'undefined' && innerWidth <= 600;
        this.overviewCompact = phone;
        this.phone = phone;
        this.overviewView = phone ? 'list' : 'grid';
        try {
          const view = localStorage.getItem(this.overviewViewKey());
          if (OVERVIEW_VIEWS.includes(view)) this.overviewView = view;
        } catch {}
      },
      /** Marks a sideways scrolling strip with the edges that hide more items, so the cut-off side fades out. */
      scrollHint(el) {
        const update = () => {
          const max = el.scrollWidth - el.clientWidth;
          const hint = max > 1 ? [el.scrollLeft > 1 && 'start', el.scrollLeft < max - 1 && 'end'].filter(Boolean).join(' ') : '';
          if ((el.dataset.psMore || '') === hint) return;
          if (hint) el.dataset.psMore = hint;
          else delete el.dataset.psMore;
        };
        el.addEventListener('scroll', update, { passive: true });
        if (typeof ResizeObserver === 'function') {
          const observer = new ResizeObserver(update);
          observer.observe(el);
          for (const child of el.children) observer.observe(child);
        }
        this.$nextTick?.(update);
      },
      setOverviewView(view) {
        if (!OVERVIEW_VIEWS.includes(view)) return;
        this.overviewView = view;
        try {
          localStorage.setItem(this.overviewViewKey(), view);
        } catch {}
      },
      /** Card width and column count for the grid views: a fixed count is the most per row, narrower screens wrap. */
      overviewGridStyle() {
        const compact = this.overviewView === 'compact';
        const width = (compact ? { narrow: 170, normal: 210, wide: 270 } : { narrow: 210, normal: 260, wide: 330 })[this.overviewPreferences.cardWidth] || 260;
        const fixed = Number(this.overviewPreferences.columns) || 0;
        const most = fixed || (compact ? { narrow: 7, normal: 6, wide: 5 } : { narrow: 5, normal: 4, wide: 3 })[this.overviewPreferences.cardWidth] || 4;
        const min = width - 30;
        return {
          '--ps-grid-width': `calc(${most} * ${width}px + ${most - 1} * 16px)`,
          '--ps-grid-template': fixed ? `repeat(auto-fill, minmax(min(100%, max(${min}px, calc((100% - ${(fixed - 1) * 16}px) / ${fixed}))), 1fr))` : `repeat(auto-fill, minmax(min(100%, ${min}px), 1fr))`,
        };
      },
      /** Keeps the health dock aligned with the rendered card grid (including scrollbar width). */
      initOverviewDock() {
        if (this.server || !this.$root || typeof ResizeObserver === 'undefined') return;
        this.overviewDockObserver?.disconnect();
        const overview = this.$root.querySelector('.ps-overview'), grid = overview?.querySelector('.ps-server-grid');
        if (!overview || !grid) return;
        const sync = () => {
          const parent = overview.getBoundingClientRect(), cards = grid.getBoundingClientRect();
          if (cards.width <= 0) return;
          const scale = this.uiScale || 1;
          overview.style.setProperty('--ps-health-width', cards.width / scale + 'px');
          overview.style.setProperty('--ps-health-left', (cards.left - parent.left) / scale + 'px');
        };
        this.overviewDockObserver = new ResizeObserver(sync);
        this.overviewDockObserver.observe(overview);
        this.overviewDockObserver.observe(grid);
        sync();
      },
      overviewColumns() {
        return Math.min(4, Math.max(1, this.visibleServers().length));
      },
      cpuLabel(s) {
        const n = s.usage?.cpu_percent;
        return typeof n === 'number' && Number.isFinite(n) ? n.toFixed(1) + '%' : '—';
      },
      memoryLabel(s) {
        const n = s.usage?.memory_bytes;
        if (typeof n !== 'number' || !Number.isFinite(n)) return '—';
        const cap = s.usage?.memory_limit_bytes || 0;
        const gb = Math.max(n, cap) >= 1073741824;
        const unit = gb ? 1073741824 : 1048576;
        const number = (v) => (gb ? (v / unit).toFixed(1) : Math.round(v / unit));
        return number(n) + (cap > 0 ? ' / ' + number(cap) : '') + (gb ? ' GB' : ' MB');
      },
      hostPercent(kind, h = this.boot.host_health) {
        if (!h || !h.available) return null;
        if (kind === 'cpu') return h.cpu_percent;
        const total = h[kind + '_total'];
        return total > 0 ? (h[kind + '_used'] / total) * 100 : null;
      },
      hostMetricState(kind, h = this.boot.host_health) {
        if (h?.metric_states?.[kind]) return h.metric_states[kind];
        const n = this.hostPercent(kind, h);
        if (typeof n !== 'number' || !Number.isFinite(n) || h.available < h.hosts) return 'unavailable';
        return n >= 95 ? 'critical' : n >= 85 ? 'warning' : 'healthy';
      },
      hostMetricLabel(kind, h = this.boot.host_health) {
        if (kind === 'network') return this.hostMetricState(kind, h) === 'healthy' ? t('dashboard.traffic_reported') : t('dashboard.unavailable');
        return { healthy: t('dashboard.healthy'), warning: t('dashboard.high_load'), critical: t('dashboard.critical_load'), unavailable: t('dashboard.unavailable') }[this.hostMetricState(kind, h)] || t('dashboard.unavailable');
      },
      hostFill(kind, h = this.boot.host_health) {
        if (kind === 'network') return (h?.network_rx_bytes ?? 0) + (h?.network_tx_bytes ?? 0) > 0 ? 100 : 0;
        return Math.min(100, Math.max(0, this.hostPercent(kind, h) || 0));
      },
      hostValue(kind, h = this.boot.host_health) {
        if (kind === 'network') return h?.network_rx_bytes == null || h?.network_tx_bytes == null ? '—' : '↓ ' + this.trafficBytes(h.network_rx_bytes) + ' / ↑ ' + this.trafficBytes(h.network_tx_bytes);
        const n = this.hostPercent(kind, h);
        if (typeof n !== 'number' || !Number.isFinite(n)) return '—';
        if (kind === 'cpu') return n.toFixed(1) + '%';
        return this.memoryLabel({ usage: { memory_bytes: h[kind + '_used'], memory_limit_bytes: h[kind + '_total'] } });
      },
      recordOverview(servers) {
        const ids = new Set();
        for (const s of servers || []) {
          ids.add(s.uuid);
          const h = this.overviewHistory[s.uuid] || (this.overviewHistory[s.uuid] = []);
          h.push({ cpu: s.usage?.cpu_percent ?? null, ram: s.usage?.memory_bytes ?? null });
          this.overviewNetwork[s.uuid] = this.networkSample({ rx_bytes: s.usage?.network_rx_bytes, tx_bytes: s.usage?.network_tx_bytes }, s.usage?.network_sampled_at ?? Date.now() / 1000, this.overviewNetwork[s.uuid]);
          if (h.length > 24) h.shift();
        }
        for (const id of Object.keys(this.overviewHistory)) {
          if (ids.has(id)) continue;
          delete this.overviewHistory[id];
          delete this.overviewNetwork[id];
        }
      },
      cardRamLabel(s) {
        return this.memoryLabel({ usage: { memory_bytes: s.usage?.memory_bytes } });
      },
      cardGraphY(s, key, value) {
        if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) return null;
        const limit = key === 'cpu'
          ? (s.usage?.cpu_limit > 0 ? s.usage.cpu_limit : 100)
          : (s.usage?.memory_limit_bytes > 0 ? s.usage.memory_limit_bytes : Math.max(1073741824, ...(this.overviewHistory[s.uuid] || []).map((p) => p.ram || 0)));
        return 26 - Math.min(1, value / limit) * 23;
      },
      cardGraph(s, key) {
        const h = this.overviewHistory[s.uuid] || [];
        let path = '', drawing = false;
        h.forEach((p, i) => {
          const y = this.cardGraphY(s, key, p[key]);
          if (y === null) {
            drawing = false;
            return;
          }
          const x = 2 + ((24 - h.length + i) / 23) * 96;
          path += (drawing ? ' L' : ' M') + x.toFixed(2) + ' ' + y.toFixed(2);
          drawing = true;
        });
        return path;
      },
      cardGraphPoint(s, key) {
        const h = this.overviewHistory[s.uuid] || [];
        return h.length ? this.cardGraphY(s, key, h[h.length - 1][key]) : null;
      },
      async refreshOverview() {
        if (globalThis.document?.hidden || this.server || this.destroyed || this.overviewRefreshing) return;
        this.overviewRefreshing = true;
        try {
          const [servers, health] = await Promise.all([wire.listing(), this.boot.features?.host_health ? wire.hostHealth() : Promise.resolve({})]);
          if (this.destroyed) return;
          const badges = Object.fromEntries(this.boot.servers.map((s) => [s.uuid, s.badges]));
          this.boot.servers = servers.map((s) => ({ ...s, badges: badges[s.uuid] || [] }));
          if (this.gameFilter !== 'all' && !this.gameOptions().some((g) => g.key === this.gameFilter)) this.gameFilter = 'all';
          this.boot.host_health = health;
          this.healthOpenNodes = this.healthOpenNodes.filter((id) => health.nodes?.some((n) => String(n.id) === String(id)));
          this.recordOverview(servers);
        } catch {
        } finally {
          this.overviewRefreshing = false;
        }
      },
      /** The refresh button: fresh numbers now instead of at the next poll (the server allows one fetch every few seconds). */
      async refreshHostHealth() {
        if (this.healthRefreshing || !this.boot.features?.host_health) return;
        this.healthRefreshing = true;
        try {
          const health = await wire.refreshHostHealth();
          if (this.destroyed) return;
          this.boot.host_health = health;
          this.healthOpenNodes = this.healthOpenNodes.filter((id) => health.nodes?.some((n) => String(n.id) === String(id)));
        } catch {
        } finally {
          this.healthRefreshing = false;
        }
      },
      /** Puts the top-bar health details right under the Healthy button, left edges in line, inside the screen. */
      placeHealthPopover() {
        const pill = this.$root?.querySelector('.ps-health-pill'), panel = this.$root?.querySelector('.ps-health-panel');
        if (!pill || !panel || typeof window === 'undefined') return;
        const scale = this.uiScale || 1, rect = pill.getBoundingClientRect(), screen = window.innerWidth / scale, gap = 8;
        const width = Math.min(560, screen - 2 * gap);
        panel.style.setProperty('--ps-pop-width', width + 'px');
        panel.style.setProperty('--ps-pop-left', Math.max(gap, Math.min(rect.left / scale, screen - width - gap)) + 'px');
        panel.style.setProperty('--ps-pop-top', rect.bottom / scale + gap + 'px');
      },
      /** Where host health shows on the overview: a button in the top bar (default), a bar above the servers or at the bottom. */
      healthPlace() {
        return this.boot.features?.host_health ? this.overviewPreferences.healthPlace : null;
      },
      safeImage(s) {
        if (typeof s !== 'string' || !s.trim()) return '';
        if (s.startsWith('data:image/png;base64,') && s.length < 150000) return s;
        try {
          const u = new URL(s, location.origin);
          return ['https:', 'http:'].includes(u.protocol) ? u.href : '';
        } catch {
          return '';
        }
      },
      safeLink(s) {
        try {
          const u = new URL(s);
          return ['https:', 'http:'].includes(u.protocol) ? u.href : '#';
        } catch {
          return '#';
        }
      },
      /** Colour banner: inline style for a chosen colour, {} for the accent-based one, null for image banners. */
      bannerColor(s) {
        const id = String(s?.background_id || '');
        if (id === 'color') return {};
        const match = id.match(/^color:(#[0-9a-f]{6})$/i);
        return match ? { '--ps-banner': match[1] } : null;
      },
      trafficBytes(v) {
        if (!Number.isFinite(Number(v)) || Number(v) < 0) return '—';
        v = Number(v);
        if (v >= 1073741824) return (v / 1073741824).toFixed(1) + ' GB';
        if (v >= 1048576) return (v / 1048576).toFixed(1) + ' MB';
        if (v >= 1024) return (v / 1024).toFixed(1) + ' KB';
        return Math.round(v) + ' B';
      },
      /** Read-only MOTD rendering for cards and the server header (the editor lives in motd.js). */
      motdPreview(value) {
        return window.psMotd.parse(value).flatMap((line, index) => [
          ...(index ? [{ text: '\n', style: {} }] : []),
          ...window.psMotd.runs(line).map(({ text, style, obfuscated }) => ({ text: obfuscated ? text.replace(/\S/g, '▒') : text, style })),
        ]);
      },
      adminPages() {
        return Array.isArray(this.boot.admin_pages) ? this.boot.admin_pages : [];
      },
    };
  };
})();
