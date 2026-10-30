/* Simplify overview (Alpine component). Server data arrives through Livewire ($wire) calls; everything else is
   local UI state. Dialogs, toasts and tooltips come from core.js (window.Simplify). */
(() => {
  // Texts from lang/<locale>/js.php (core.js); the key itself when core.js is missing.
  const t = (key, params) => (window.Simplify?.t ? window.Simplify.t(key, params) : key);
  const tc = (key, count, params) => (window.Simplify?.tc ? window.Simplify.tc(key, count, params) : key);
  const MINECRAFT_VERSION = /(?:^|[^0-9])((?:1\.[0-9]+(?:\.[0-9]+)?|2[6-9]\.[0-9]+(?:\.[0-9]+)?))(?![0-9])/;
  /* A request that fails on the server shows Livewire's white error page over the whole panel. On Simplify's pages
     it becomes one short message in the panel's own style instead; the cause is in Pelican's log. */
  const quietServerErrors = () => {
    if (window.simplifyQuietErrors || !window.Livewire?.hook) return;
    window.simplifyQuietErrors = true;
    window.Livewire.hook('request', ({ fail }) => fail(({ status, preventDefault }) => {
      if (status < 500 || !document.querySelector('[data-simplify-root]')) return;
      preventDefault();
      window.Simplify?.toast(t('dashboard.server_error'), 'danger');
    }));
  };
  if (window.Livewire) quietServerErrors();
  else document.addEventListener('livewire:init', quietServerErrors, { once: true });
  const OVERVIEW_DEFAULTS = { showMotd: false, largeIcon: 'server', groupByNode: 'auto', showCpu: true, showRam: true, quickActions: 'hover', minecraftAlwaysGreen: true, fullWidth: false, showGroupsButton: true, columns: 'auto', cardWidth: 'normal', serverScope: 'all', halloween: true, healthPlace: 'top-bar' };
  const OVERVIEW_SWITCHES = ['showMotd', 'showCpu', 'showRam', 'minecraftAlwaysGreen', 'fullWidth', 'showGroupsButton', 'halloween'];
  // Overview settings that pick one of a fixed set of values.
  const OVERVIEW_CHOICES = { largeIcon: ['server', 'egg'], groupByNode: ['auto', 'always', 'off'], quickActions: ['off', 'on', 'hover'], columns: ['auto', '2', '3', '4', '5', '6'], cardWidth: ['narrow', 'normal', 'wide'], serverScope: ['all', 'mine', 'others'], healthPlace: ['top-bar', 'above', 'bottom'] };
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

  // Today's holidays (seasonsToday), kept outside Alpine's state so working them out never triggers a redraw.
  let seasonCache = null;
  window.pelicanSimplify = function (wire, boot) {
    return {
      boot,
      wire,
      // The server page (later) and host mode share this component; the overview has neither.
      server: null,
      hostMode: false,
      // An admin page opened from Admin tools outside host mode: Simplify's frame without the tree (host.blade.php).
      adminView: false,
      // Image URLs that did not load (an egg icon behind a wrong APP_URL, a deleted file): the next icon is used instead.
      brokenImages: {},
      hostTab: 'admin',
      hostMenuOpen: false,
      hostSection: null,
      hostPage: null,
      hostOpen: [],
      hostServer: null,
      refreshSeconds: Math.max(5, Number(boot.performance?.overview_seconds) || 15) * 1000,
      overviewDockObserver: null,
      appearanceMenu: false,
      notifyMenu: false,
      noticeHistory: [],
      feed: { items: [], page: 1, more: false, loading: false, unread: 0 },
      // What "Clear read" and ✕ moved away: a closed section of the window with every notification, loaded when it opens.
      archive: { open: false, items: [], page: 0, more: false, loading: false, loaded: false },
      incomingNotice: null,
      incomingShown: false,
      incomingQueue: [],
      pushOffer: false,
      notifyAll: false,
      push: { enabled: false, state: 'unsupported', busy: false, endpoint: null, key: null, messages: true, updates: null },
      adminMenu: false,
      adminOpen: null,
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
      seasonClock: 0,
      easterFound: false,

      init() {
        this.initShell();
        this.restoreOverviewView();
        this.restoreHostMode();
        this.openAdminFromHash();
        this.$nextTick(() => this.initOverviewDock());
        setTimeout(() => { this.releaseBats(); this.playSeason(); this.startSeasonClock(); }, 600);
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
        // Every menu under the top bar ends inside the window when it opens, and again when the window changes.
        const menus = { notifyMenu: 'ps-notify-menu', adminMenu: 'ps-admin-menu', appearanceMenu: 'ps-overview-appearance-menu', groupsMenu: 'ps-overview-groups-menu' };
        for (const [key, id] of Object.entries(menus)) this.$watch(key, (open) => open && this.$nextTick(() => this.fitDropdown(document.getElementById(id))));
        addEventListener('resize', () => Object.entries(menus).forEach(([key, id]) => this[key] && this.fitDropdown(document.getElementById(id))));
        this.restoreOverviewPreferences();
        this.overviewGroups = this.cleanOverviewGroups(this.boot.overview_groups?.groups);
        this.overviewServerOrder = SERVER_ORDERS.some((o) => o.key === this.boot.overview_groups?.order) ? this.boot.overview_groups.order : 'name';
        this.feed = { ...this.feed, ...this.boot.notifications };
        this.checkPushOffer();
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
        clearInterval(this.seasonTimer);
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
        const storage = this.feed.storage?.command
          ? [{ id: 'storage', tone: 'danger', title: t('dashboard.storage_not_writable'), text: t('dashboard.storage_not_writable_text'), command: this.feed.storage.command }]
          : [];
        return [...storage, ...alert, ...this.feed.items];
      },
      unreadNotifications() {
        return this.notifications().filter((item) => !item.pelican).length + (Number(this.feed.unread) || 0);
      },
      async toggleNotifications() {
        this.notifyMenu = !this.notifyMenu;
        this.appearanceMenu = this.groupsMenu = this.accountMenu = false;
        if (this.notifyMenu) await this.loadNotificationPage(true);
      },
      /** A menu under the top bar ends inside the window, wherever the bar ends (announcements can give it a second row). */
      fitDropdown(menu) {
        if (!menu || typeof window === 'undefined') return;
        // In the phone menu it is part of that menu, which scrolls itself.
        menu.style.maxHeight = '';
        if (getComputedStyle(menu).position !== 'absolute') return;
        const scale = this.uiScale || 1, top = menu.getBoundingClientRect().top;
        // The menu ends at the window, or higher where a box around it clips (on the overview the page above the footer).
        let bottom = window.innerHeight;
        for (let box = menu.parentElement; box && box !== document.body; box = box.parentElement) {
          if (getComputedStyle(box).overflowY !== 'visible') bottom = Math.min(bottom, box.getBoundingClientRect().bottom);
        }
        // At most two thirds of the window; a short menu stays as high as its content.
        menu.style.maxHeight = Math.max(160, Math.floor(Math.min(bottom - top - 12, window.innerHeight * 2 / 3) / scale)) + 'px';
      },
      /** New unread notifications since the last look drop down from the bell, one after another (as in 0.7.0). */
      async pollNotifications() {
        if (document.hidden || this.destroyed || this.notifyMenu || this.feed.loading) return;
        try {
          const result = await wire.notificationPage(1);
          const known = new Set(this.feed.items.map((n) => n.key));
          const fresh = result.items.filter((n) => n.unread && !known.has(n.key));
          this.feed = { ...this.feed, items: [...fresh, ...this.feed.items], unread: result.unread, storage: result.storage };
          this.incomingQueue.push(...fresh.reverse());
          this.showIncomingNotice();
        } catch {}
      },
      showIncomingNotice() {
        if (this.incomingNotice || !this.incomingQueue.length) return;
        this.incomingNotice = this.incomingQueue.shift();
        this.incomingShown = true;
        this.holdIncomingNotice();
      },
      holdIncomingNotice(delay = 6000) {
        clearTimeout(this.incomingTimer);
        this.incomingTimer = setTimeout(() => this.hideIncomingNotice(), delay);
      },
      hideIncomingNotice() {
        clearTimeout(this.incomingTimer);
        this.incomingShown = false;
        setTimeout(() => {
          this.incomingNotice = null;
          this.showIncomingNotice();
        }, 450);
      },
      /** The bell offers app notifications once per device, until they are on or the offer is dismissed. */
      async checkPushOffer() {
        try {
          if (!this.boot.notifications_url || localStorage.getItem('pelicanSimplify.pushOffer') === 'hidden') return;
          if (!('PushManager' in window) || Notification.permission === 'denied') return;
          const registration = await navigator.serviceWorker.getRegistration();
          this.pushOffer = !(await registration?.pushManager.getSubscription());
        } catch {}
      },
      hidePushOffer() {
        this.pushOffer = false;
        try {
          localStorage.setItem('pelicanSimplify.pushOffer', 'hidden');
        } catch {}
      },
      /** The window with every notification; above the list, what this account gets on its devices. */
      async openAllNotifications() {
        this.notifyMenu = false;
        this.notifyAll = true;
        this.archive = { ...this.archive, open: false, items: [], page: 0, more: false, loaded: false };
        if (!this.feed.items.length) this.loadNotificationPage(true);
        try {
          const registration = 'serviceWorker' in navigator && window.isSecureContext ? await navigator.serviceWorker.getRegistration() : null;
          const subscription = 'PushManager' in window ? await registration?.pushManager.getSubscription() : null;
          const settings = await wire.pushSettings(subscription?.endpoint || null);
          if (!settings) return (this.push.enabled = false);
          const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
          const state = !registration || !('PushManager' in window)
            ? (/iPhone|iPad/.test(navigator.userAgent) && !standalone ? 'install' : 'unsupported')
            : Notification.permission === 'denied' ? 'blocked' : settings.current ? 'on' : 'off';
          this.push = { ...this.push, ...settings, enabled: true, state, endpoint: subscription?.endpoint || null };
        } catch {
          this.push.enabled = false;
        }
      },
      pushLabel() {
        const agent = navigator.userAgent;
        const browser = /Edg\//.test(agent) ? 'Edge' : /Firefox\//.test(agent) ? 'Firefox' : /SamsungBrowser/.test(agent) ? 'Samsung Internet'
          : /Chrome\//.test(agent) ? 'Chrome' : /Safari\//.test(agent) ? 'Safari' : 'Browser';
        const system = /iPhone/.test(agent) ? 'iPhone' : /iPad/.test(agent) ? 'iPad' : /Android/.test(agent) ? 'Android'
          : /Windows/.test(agent) ? 'Windows' : /Mac OS X/.test(agent) ? 'Mac' : /Linux/.test(agent) ? 'Linux' : '';
        return browser + (system ? ' · ' + system : '');
      },
      async pushOn() {
        this.push.busy = true;
        try {
          if (await Notification.requestPermission() !== 'granted') return (this.push.state = 'blocked');
          const registration = await navigator.serviceWorker.getRegistration();
          const bytes = Uint8Array.from(atob(this.push.key.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
          let subscription = await registration.pushManager.getSubscription();
          // A subscription made with an older panel key cannot be used anymore.
          const old = subscription?.options?.applicationServerKey;
          if (subscription && old && !new Uint8Array(old).every((value, index) => value === bytes[index])) {
            await subscription.unsubscribe();
            subscription = null;
          }
          subscription ||= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
          const result = await wire.pushOn(subscription.toJSON(), this.pushLabel());
          this.toast(result.message, result.ok ? 'ok' : 'warn');
          if (result.ok) {
            this.push.endpoint = subscription.endpoint;
            this.push.state = 'on';
            this.pushOffer = false;
          } else {
            await subscription.unsubscribe().catch(() => {});
          }
        } catch {
          this.push.state = 'unsupported';
        } finally {
          this.push.busy = false;
        }
      },
      async pushOff() {
        this.push.busy = true;
        try {
          await (await (await navigator.serviceWorker.getRegistration())?.pushManager.getSubscription())?.unsubscribe();
        } catch {}
        await wire.pushOff(this.push.endpoint || '').catch(() => {});
        this.push.state = 'off';
        this.push.busy = false;
      },
      async pushTest() {
        this.push.busy = true;
        try {
          const result = await wire.pushTest(this.push.endpoint);
          if (result.gone) this.push.state = 'off';
          this.toast(result.message, result.ok ? 'ok' : 'warn');
        } catch {} finally {
          this.push.busy = false;
        }
      },
      async pushChoose(kind, on) {
        this.push[kind] = on;
        await wire.pushChoose(kind, on).catch(() => (this.push[kind] = !on));
      },
      /** The first (reset) or next page of Pelican's notifications, read ones included. */
      async loadNotificationPage(reset = false) {
        if (this.feed.loading) return;
        const page = reset ? 1 : this.feed.page + 1;
        this.feed.loading = true;
        try {
          const result = await wire.notificationPage(page);
          const known = new Set(reset ? [] : this.feed.items.map((n) => n.key));
          this.feed = { items: reset ? result.items : [...this.feed.items, ...result.items.filter((n) => !known.has(n.key))], page, more: !!result.more, unread: result.unread, archived: !!result.archived, storage: result.storage, loading: false };
        } catch {
          this.feed.loading = false;
          if (!reset) this.toast(t('dashboard.older_notifications_could_not_be_loaded'), 'warn');
        }
      },
      /** Marks the given notifications (all of them for []) read or unread again; unread takes one out of Archived. */
      async markNotifications(keys, read) {
        try {
          const result = await wire.markNotifications(keys, read);
          this.feed = { ...result, page: 1, loading: false };
          if (!read && this.archive.items.some((n) => keys.includes(n.key))) this.reloadArchive();
        } catch {
          this.toast(t('dashboard.the_notification_could_not_be_marked'), 'warn');
        }
      },
      /** "Clear read" shows while the list has read notifications. */
      canClearRead() {
        return this.feed.items.some((item) => item.unread === false);
      },
      /** "Clear read" ([]) moves every read notification to Archived, ✕ moves one (read). Nothing is deleted. */
      async archiveNotifications(keys = []) {
        try {
          const result = await wire.archiveNotifications(keys);
          this.feed = { ...result, page: 1, loading: false };
          this.reloadArchive();
        } catch {
          this.toast(t('dashboard.the_notification_could_not_be_cleared'), 'warn');
        }
      },
      async toggleArchive() {
        this.archive.open = !this.archive.open;
        if (this.archive.open && !this.archive.loaded) await this.loadArchivePage(true);
      },
      /** Archived as it is now: loaded again while open, otherwise the next time it opens. */
      reloadArchive() {
        this.archive = { ...this.archive, items: [], page: 0, more: false, loaded: false };
        if (this.archive.open) this.loadArchivePage(true);
      },
      async loadArchivePage(reset = false) {
        if (this.archive.loading) return;
        const page = reset ? 1 : this.archive.page + 1;
        this.archive.loading = true;
        try {
          const result = await wire.notificationPage(page, true);
          const known = new Set(reset ? [] : this.archive.items.map((n) => n.key));
          this.archive = { ...this.archive, items: reset ? result.items : [...this.archive.items, ...result.items.filter((n) => !known.has(n.key))], page, more: !!result.more, loaded: true, loading: false };
        } catch {
          this.archive.loading = false;
          this.toast(t('dashboard.archived_notifications_could_not_be_loaded'), 'warn');
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
        // The settings are leaves of the tree: Simplify's tabs, the add-ons' pages and Pelican's tabs, each in its group.
        const menu = this.boot.settings_menu || [];
        if (menu.length) {
          const tabs = (group) => menu.filter((tab) => tab.group === group).map((tab) => ({ ...tab, settings: true }));
          plan.splice(plan.findIndex((section) => section.key === 'panel'), 1,
            { key: 'panel', label: t('dashboard.admin_groups.settings'), icon: 'gear', color: '#94a3b8', pages: [], tabs: tabs('panel') },
            { key: 'addons', label: t('dashboard.admin_groups.addons'), icon: 'plugins', color: '#b48cf2', pages: ['pluginresource'], tabs: tabs('addons') },
            { key: 'pelican', label: t('dashboard.admin_groups.pelican_settings'), icon: 'settings', color: '#5b9cf0', pages: ['listlogs'], tabs: tabs('pelican') });
        }
        const items = this.adminPages().flatMap((group) => group.items);
        const known = new Set([...plan.flatMap((section) => section.pages), 'settings']);
        // An add-on's settings page that is also in Pelican's menu (Hosting) shows once, under Add-ons.
        const tabUrls = new Set(menu.map((tab) => tab.url));
        // Simplify's own pages (Announcements, Automatic subdomains, Website) join its settings, the add-ons' other pages
        // join Add-ons: Other plugins is only for plugins that are not Simplify.
        // An add-on page with the same name as the add-on's settings (Server Maker's own page) shows once.
        const tabLabels = new Set(menu.map((tab) => tab.label));
        const own = (origin) => items.filter((item) => item.origin === origin && !known.has(item.key) && !tabUrls.has(item.url) && !tabLabels.has(item.label));
        const extra = { panel: own('core'), addons: own('addon') };
        if (!menu.length) extra.panel = [];
        return plan.map((section) => ({
          ...section,
          pages: section.key === 'extensions'
            ? items.filter((item) => !known.has(item.key) && !tabUrls.has(item.url) && !(menu.length && item.origin))
            : [...(section.tabs || []), ...(extra[section.key] || []), ...section.pages.map((key) => items.find((item) => item.key === key)).filter(Boolean)],
        })).filter((section) => section.pages.length);
      },
      /** The Admin tools groups, without Health (its checks have their own row under the groups). */
      adminSections() {
        return this.hostSections().filter((section) => section.key !== 'health');
      },
      /** The open group: on a wide window always one (the first by default), in the phone menu none until tapped. */
      adminGroup() {
        const keys = this.adminSections().map((section) => section.key);
        if (keys.includes(this.adminOpen)) return this.adminOpen;
        return window.matchMedia('(max-width: 900px)').matches ? null : keys[0] || null;
      },
      adminSection() {
        return this.adminSections().find((section) => section.key === this.adminGroup()) || null;
      },
      pickAdminGroup(key) {
        const phone = window.matchMedia('(max-width: 900px)').matches;
        this.adminOpen = phone && this.adminGroup() === key ? null : key;
        this.$nextTick(() => this.fitDropdown(document.getElementById('ps-admin-menu')));
      },
      adminHealthPage() {
        return this.adminPages().flatMap((group) => group.items).find((item) => item.key === 'health') || null;
      },
      /** Admin tools open their pages inside Simplify: on the overview in place, from a server page via the overview. */
      adminLink(section, item) {
        return (this.boot.list_url || '/') + '#admin:' + encodeURIComponent(section.key) + '/' + encodeURIComponent(item.key);
      },
      openAdminPage(event, section, item) {
        if (event?.ctrlKey || event?.metaKey || event?.shiftKey || this.server) return;
        event?.preventDefault();
        this.adminMenu = false;
        this.navMenu = false;
        if (!this.hostMode) this.adminView = true;
        this.openHostLeaf(section, item);
      },
      openAdminHealth(event) {
        const section = this.hostSections().find((x) => x.key === 'health');
        if (section?.pages[0]) this.openAdminPage(event, section, section.pages[0]);
      },
      closeAdminView() {
        this.adminView = false;
        if (!this.hostMode) this.hostPage = null;
      },
      /** /simplify#admin:group/page (a link from Admin tools on a server page) opens that page once. */
      openAdminFromHash() {
        const match = /^#admin:([^/]+)\/(.+)$/.exec(location.hash);
        if (!match) return;
        history.replaceState(null, '', location.pathname + location.search);
        const section = this.hostSections().find((x) => x.key === decodeURIComponent(match[1]));
        const item = section?.pages.find((x) => x.key === decodeURIComponent(match[2]));
        if (item) this.openAdminPage(null, section, item);
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
        if (section && !this.hostOpen.includes(section.key)) this.hostOpen = [...this.hostOpen, section.key];
        this.hostSection = section;
        this.hostPage = item || null;
        this.hostServer = null;
        this.hostMenuOpen = false;
        try {
          sessionStorage.setItem(this.hostModeKey() + '.place', JSON.stringify({ section: section?.key || null, page: item?.key || null }));
        } catch {}
      },
      /** A group with more than one page folds open and shut; the open page's group opens with it. */
      hostBranchOpen(section) {
        return section.pages.length > 1 && this.hostOpen.includes(section.key);
      },
      toggleHostBranch(section) {
        this.hostOpen = this.hostOpen.includes(section.key) ? this.hostOpen.filter((key) => key !== section.key) : [...this.hostOpen, section.key];
      },
      hostAllOpen() {
        return this.hostSections().every((section) => section.pages.length < 2 || this.hostOpen.includes(section.key));
      },
      toggleHostBranches() {
        this.hostOpen = this.hostAllOpen() ? [] : this.hostSections().filter((section) => section.pages.length > 1).map((section) => section.key);
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
      /** One "Create server": an add-on's main button (Server Maker), else Pelican's own form; other add-on buttons keep their label. */
      createButtons() {
        const create = this.boot.create || [], main = create.find((b) => b.primary) || create.find((b) => b.native);
        const rest = create.filter((b) => b !== main && !b.native).map((b) => ({ ...b, primary: false }));
        return main ? [{ ...main, primary: true, label: t('dashboard.create_server') }, ...rest] : rest;
      },

      /** The holidays on today (seasons.js), for this panel, whether or not they are turned off. Worked out once a day. */
      seasonsToday() {
        const today = new Date(), stamp = today.toDateString();
        if (seasonCache?.stamp !== stamp) {
          const born = this.boot.panel_born ? new Date(this.boot.panel_born * 1000) : null;
          const list = window.SimplifySeasons?.active(today, { dutch: /^nl\b/i.test(document.documentElement.lang || ''), born }) || [];
          seasonCache = { stamp, list };
        }
        return seasonCache.list;
      },
      /** The seasonal touches show unless the panel (Settings, Appearance) or this person (Customize) turned them off. */
      seasons() {
        // The personal switch keeps its first name, halloween, so a choice made for Halloween still counts.
        if (this.boot.appearance?.seasonal === false || this.overviewPreferences.halloween === false) return [];
        return this.seasonsToday();
      },
      season(key) {
        return this.seasons().some((s) => s.key === key);
      },
      /** The space-separated keys for data-season on the page, which the stylesheet (seasons.css) follows. */
      seasonKeys() {
        return this.seasons().map((s) => s.key).join(' ');
      },
      /** For the Customize switch: "Halloween", or "Hanukkah and Christmas". */
      seasonNames() {
        return this.seasonsToday().map((s) => t(`seasons.${s.key}.name`)).join(' · ');
      },
      /** The icons next to the panel name (two at most), each with a tooltip saying what the day is. */
      seasonBadges() {
        return this.seasons().filter((s) => !['valentine', 'easter'].includes(s.key)).slice(0, 2).map((s) => ({
          key: s.key, icon: s.icon, color: s.color, day: s.day,
          label: s.key === 'lunar' ? t('seasons.lunar.name_year', { animal: t('seasons.zodiac.' + ((s.start.getFullYear() - 2020) % 12 + 12) % 12) })
            : s.key === 'hanukkah' ? t('seasons.hanukkah.name_night', { night: s.day }) : t(`seasons.${s.key}.name`),
          text: s.key === 'birthday' ? t('seasons.birthday.text', { count: s.years, date: this.seasonDate(this.boot.panel_born) }) : t(`seasons.${s.key}.text`),
        }));
      },
      seasonDate(timestamp) {
        const date = new Date(timestamp * 1000), options = { day: 'numeric', month: 'long', year: 'numeric' };
        try { return date.toLocaleDateString((document.documentElement.lang || 'en').replace('_', '-'), options); } catch { return date.toLocaleDateString('en', options); }
      },
      /** On 31 December from 23:00: the time left until the new year, as 0:42:10. */
      newYearCountdown() {
        const now = new Date(this.seasonClock || Date.now());
        if (now.getMonth() !== 11 || now.getDate() !== 31 || now.getHours() < 23 || !this.season('newyear')) return '';
        const left = Math.max(0, Math.ceil((new Date(now.getFullYear() + 1, 0, 1) - now) / 1000));
        return `0:${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
      },
      /** The countdown ticks only on New Year's Eve; at midnight the fireworks go off once. */
      startSeasonClock() {
        const now = new Date();
        if (this.seasonTimer || !(now.getMonth() === 11 && now.getDate() === 31 && this.season('newyear'))) return;
        this.seasonTimer = setInterval(() => {
          this.seasonClock = Date.now();
          if (new Date().getMonth() === 0) {
            clearInterval(this.seasonTimer);
            seasonCache = null;
            this.playSeason(true);
          }
        }, 1000);
      },
      /** A server's birthday: created on this day, at least a year ago (the number of years, or 0). */
      serverBirthday(s) {
        return this.seasons().length ? window.SimplifySeasons?.birthday(s.created) || 0 : 0;
      },
      serverBirthdayTip(s) {
        return t('seasons.server_birthday', { name: s.name, count: this.serverBirthday(s) });
      },
      /** Easter: one server card a day hides a small egg, until this person finds it (once a year, on this device). */
      easterEgg(s) {
        if (this.server || !this.season('easter') || this.easterFound) return false;
        try {
          if (localStorage.getItem('simplify.easter') === String(new Date().getFullYear())) return false;
        } catch {}
        const cards = (this.boot.servers || []).map((server) => server.uuid).sort();
        return cards.length > 0 && cards[new Date().getDate() % cards.length] === s.uuid;
      },
      findEasterEgg(event) {
        const box = event.currentTarget.getBoundingClientRect();
        window.SimplifySeasons?.confetti(box.left, box.top);
        this.easterFound = true;
        try { localStorage.setItem('simplify.easter', String(new Date().getFullYear())); } catch {}
        window.Simplify?.toast(t('seasons.easter.found'), 'ok');
      },
      /** The holiday that leads today plays its small animation once per visit, on the overview only. */
      playSeason(force = false) {
        const lead = this.seasons()[0];
        if (this.server || !lead || !window.SimplifySeasons) return;
        try {
          const key = 'simplify.season.' + lead.key;
          if (!force && sessionStorage.getItem(key)) return;
          sessionStorage.setItem(key, '1');
        } catch {}
        const brand = this.$root.querySelector('.ps-identity')?.getBoundingClientRect();
        if (!brand) return;
        const S = window.SimplifySeasons, top = brand.top + brand.height / 2;
        if (lead.key === 'newyear') S.fireworks([[brand.right + 80, top], [innerWidth * .45, top - 6], [innerWidth * .62, top + 8]], ['#7c5cd6', '#e2a41a', '#d6456e', '#3a9bd9']);
        if (lead.key === 'lunar') S.fireworks([[brand.right + 60, top], [innerWidth * .5, top + 4]], ['#d23b2f', '#e8b923']);
        if (lead.key === 'holi') S.powder(brand);
        if (lead.key === 'birthday') S.confetti(brand.right, top);
      },
      /** Halloween keeps its own name for what was built for it first: the web, the lantern and the bats. */
      halloween() {
        return this.season('halloween');
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
        // Some holidays (Halloween, King's Day, Christmas, Lunar New Year) tint the overview's neutral accent; a panel accent of your own stays.
        const tint = this.seasons()[0]?.accent || this.seasons().find((s) => s.accent)?.accent;
        if (id === 'overview' && tint && (common || '#878787').toLowerCase() === '#878787')
          return { '--ps-accent': tint[this.themeDark ? 1 : 0], '--ps-on-accent': this.themeDark ? '#111118' : '#ffffff' };
        if (id === 'overview' && /^#[0-9a-f]{6}$/i.test(common || ''))
          return { '--ps-accent': common, '--ps-on-accent': accentInk(common) };
        if (id === 'overview') return { '--ps-accent': this.themeDark ? '#c0c0c5' : '#55555f', '--ps-on-accent': this.themeDark ? '#111118' : '#ffffff' };
        // Individual colours off: every server takes the panel's accent, as the overview does.
        if (appearance.server_colours === false) return this.accentStyle('overview');
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
        if (this.server || !this.boot.features?.host_health) return;
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
        if (!this.healthExpanded || this.server || !this.boot.features?.host_health || !this.healthNodes().some((n) => String(n.id) === String(node.id))) return;
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
        return this.quickActionsMode() !== 'off' && !!permission && !!s.permissions?.[permission]
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
          this.toast(t('dashboard.power_sent', { action: POWER_LABELS[action], server: s.name }), 'ok');
          await this.refreshOverview();
        } catch (error) {
          this.toast(error?.message || t('dashboard.the_power_action_could_not_be'), 'danger');
        } finally {
          this.overviewPowerBusy = null;
        }
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
        const available = row.getBoundingClientRect().width;
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
        const others = all.filter((s) => !group.servers.includes(s.uuid)).sort((a, b) => a.name.localeCompare(b.name));
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
          this.pollNotifications();
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
      /** A server's picture: the game's own icon, the icon set in Pelican, its egg's icon, then Simplify's picture of the game; the first that loads. */
      serverIcon(s) {
        return [s?.server_icon, s?.icon, s?.egg_icon, s?.game_icon].map((url) => this.safeImage(url)).find((url) => url && !this.brokenImages[url]) || '';
      },
      /** The server page's software badge: the egg's icon, else Simplify's picture of the game. */
      softwareIcon() {
        return [this.server?.egg_icon, this.server?.game_icon].map((url) => this.safeImage(url)).find((url) => url && !this.brokenImages[url]) || '';
      },
      imageFailed(url) {
        if (url) this.brokenImages = { ...this.brokenImages, [url]: true };
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
