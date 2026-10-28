/* Simplify core runtime, loaded with Simplify's own pages.
   1. Theme: the light/dark preference (shared with Filament's own switcher), applied before paint.
   2. UI: tooltips, toasts, confirm/prompt dialogs and a notice history, all in the Simplify style.
   3. Web app: "Install app" where the browser allows it. */
(() => {
  if (window.SimplifyTheme) return;
  const root = document.documentElement;
  const KEY = 'pelicanSimplify.theme';
  const CHOICES = ['light', 'dark', 'system'];
  const media = matchMedia('(prefers-color-scheme: dark)');
  let preference = 'system';
  // Texts from lang/<locale>/js.php in the user's Pelican language (English fills the gaps). Keys use dots
  // ('common.cancel'), placeholders Laravel's :name, and tc() Laravel's plural forms ('{0} none|{1} one|[2,*] :count').
  const fill = (text, params) => {
    for (const name of Object.keys(params).sort((a, b) => b.length - a.length)) text = text.split(':' + name).join(String(params[name]));
    return text;
  };
  const lookup = (key) => {
    const found = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), window.simplifyLang);
    return typeof found === 'string' ? found : key;
  };
  const t = (key, params = {}) => fill(lookup(key), params);
  const choice = (raw, count, params = {}) => {
    const forms = String(raw).split('|');
    let text = null;
    for (const form of forms) {
      const exact = form.match(/^\s*\{(\d+)\}\s*/);
      const range = form.match(/^\s*\[(\d+|\*),\s*(\d+|\*)\]\s*/);
      if (exact && Number(exact[1]) === count) text = form.slice(exact[0].length);
      else if (range && (range[1] === '*' || count >= Number(range[1])) && (range[2] === '*' || count <= Number(range[2]))) text = form.slice(range[0].length);
      if (text !== null) break;
    }
    if (text === null) {
      const plain = forms.map((form) => form.replace(/^\s*(\{\d+\}|\[(\d+|\*),\s*(\d+|\*)\])\s*/, ''));
      // The order of Laravel's plural forms: two (one|many), three (one|few|many), four (Slovenian) or six (Arabic).
      const order = { 2: { one: 0 }, 3: { one: 0, few: 1 }, 4: { one: 0, two: 1, few: 2 }, 6: { zero: 0, one: 1, two: 2, few: 3, many: 4 } }[plain.length] || {};
      let category = count === 1 ? 'one' : 'other';
      try { category = new Intl.PluralRules((root.lang || 'en').replace('_', '-')).select(count); } catch { /* English rule */ }
      text = plain[plain.length === 1 ? 0 : order[category] ?? plain.length - 1];
    }
    return fill(text, { count, ...params });
  };
  const tc = (key, count, params = {}) => choice(lookup(key), count, params);
  try {
    const saved = localStorage.getItem(KEY);
    if (CHOICES.includes(saved)) preference = saved;
  } catch {}

  const dark = () => preference === 'dark' || (preference === 'system' && media.matches);
  const accentInk = (color) => {
    const [r, g, b] = color.slice(1).match(/../g).map((v) => {
      const x = parseInt(v, 16) / 255;
      return x <= 0.04045 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4;
    });
    return r * 0.2126 + g * 0.7152 + b * 0.0722 > 0.179 ? '#111118' : '#ffffff';
  };

  const apply = () => {
    root.classList.toggle('dark', dark());
    ui.layer?.classList.toggle('dark', dark());
    // The phone's status bar follows the chosen theme, not only the system one.
    document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => meta.setAttribute('content', dark() ? '#101012' : '#f4f4f5'));
    window.dispatchEvent(new CustomEvent('simplify:theme', { detail: { preference, dark: dark() } }));
  };

  window.SimplifyTheme = {
    accentInk,
    dark,
    preference: () => preference,
    set(value) {
      if (!CHOICES.includes(value)) return;
      preference = value;
      try {
        localStorage.setItem(KEY, value);
        localStorage.setItem('theme', value);
      } catch {}
      apply();
    },
  };

  media.addEventListener('change', () => preference === 'system' && apply());
  window.addEventListener('storage', (event) => {
    if (event.key === KEY && CHOICES.includes(event.newValue)) {
      preference = event.newValue;
      apply();
    }
  });
  // Filament's own theme switcher dispatches this event. Filament also fires it while starting up with
  // its own stored theme, so early events are ignored.
  const loadedAt = Date.now();
  window.addEventListener('theme-changed', (event) => {
    if (Date.now() - loadedAt < 2000) return;
    const value = event.detail?.theme ?? event.detail;
    if (CHOICES.includes(value) && value !== preference) window.SimplifyTheme.set(value);
  });

  /* UI runtime ------------------------------------------------------------------------------ */
  const ui = { layer: null, tooltip: null, toasts: null, history: [], tipTarget: null, tipTimer: 0, dialogs: 0 };
  const icon = (name) => {
    const paths = {
      info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 8v5m0-9h.01',
      ok: 'm5 12 4 4 10-10',
      warn: 'M12 3 2 21h20Zm0 7v5m0 3h.01',
      danger: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm-3 6 6 6m0-6-6 6',
      close: 'm6 6 12 12M18 6 6 18',
    };
    return '<svg class="ps-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="' + (paths[name] || paths.info) + '"/></svg>';
  };
  // Dialogs open inside the active popup (if any) so its focus trap keeps working.
  const mountPoint = () => {
    const open = [...document.querySelectorAll('[aria-modal="true"]')].filter((el) => el.getClientRects().length);
    return open[open.length - 1] || layer();
  };
  const layer = () => {
    if (ui.layer?.isConnected) return ui.layer;
    ui.layer = document.createElement('div');
    ui.layer.className = 'ps-ui' + (dark() ? ' dark' : '');
    ui.layer.style.setProperty('--ps-accent', getComputedStyle(document.querySelector('.ps') || root).getPropertyValue('--ps-accent').trim() || '#878787');
    document.body.append(ui.layer);
    return ui.layer;
  };

  // Tooltips: every [data-tip] element; stray title attributes inside Simplify become tooltips too.
  const TIP_SCOPE = '.ps, .ps-ui';
  const placeTip = (target) => {
    const tip = ui.tooltip;
    const box = target.getBoundingClientRect();
    const own = tip.getBoundingClientRect();
    let top = box.bottom + 8;
    if (top + own.height > innerHeight - 8) top = box.top - own.height - 8;
    const left = Math.min(Math.max(8, box.left + box.width / 2 - own.width / 2), innerWidth - own.width - 8);
    tip.style.transform = `translate(${Math.round(left)}px, ${Math.round(Math.max(8, top))}px)`;
  };
  const showTip = (target) => {
    const text = target.getAttribute('data-tip');
    if (!text || !target.isConnected) return;
    if (!ui.tooltip) {
      ui.tooltip = document.createElement('div');
      ui.tooltip.className = 'ps-tooltip';
      ui.tooltip.setAttribute('role', 'tooltip');
      ui.tooltip.id = 'ps-tooltip';
      layer().append(ui.tooltip);
    }
    const label = target.getAttribute('data-tip-label');
    ui.tooltip.replaceChildren();
    if (label) {
      const small = document.createElement('small');
      small.textContent = label;
      ui.tooltip.append(small);
    }
    ui.tooltip.append(document.createTextNode(text));
    ui.tooltip.classList.add('is-visible');
    target.setAttribute('aria-describedby', 'ps-tooltip');
    placeTip(target);
  };
  const hideTip = () => {
    clearTimeout(ui.tipTimer);
    ui.tipTarget?.removeAttribute('aria-describedby');
    ui.tipTarget = null;
    ui.tooltip?.classList.remove('is-visible');
  };
  const tipTargetOf = (node) => {
    if (!(node instanceof Element)) return null;
    const titled = node.closest('[title]');
    if (titled && !titled.matches('iframe, svg, title') && titled.closest(TIP_SCOPE)) {
      titled.setAttribute('data-tip', titled.getAttribute('title'));
      titled.removeAttribute('title');
    }
    return node.closest('[data-tip]');
  };
  const enterTip = (event, delay) => {
    const target = tipTargetOf(event.target);
    if (target === ui.tipTarget) return;
    hideTip();
    if (!target) return;
    ui.tipTarget = target;
    ui.tipTimer = setTimeout(() => showTip(target), delay);
  };
  document.addEventListener('pointerover', (event) => event.pointerType !== 'touch' && enterTip(event, 350), { passive: true });
  document.addEventListener('focusin', (event) => enterTip(event, 0));
  document.addEventListener('pointerout', (event) => {
    if (ui.tipTarget && !ui.tipTarget.contains(event.relatedTarget)) hideTip();
  }, { passive: true });
  document.addEventListener('focusout', hideTip);
  document.addEventListener('pointerdown', hideTip, { passive: true });
  window.addEventListener('scroll', hideTip, { passive: true, capture: true });
  document.addEventListener('keydown', (event) => event.key === 'Escape' && hideTip());

  const toast = (message, tone = 'info', duration) => {
    const text = String(message ?? '').trim();
    if (!text) return;
    tone = ['ok', 'warn', 'danger', 'info'].includes(tone) ? tone : 'info';
    if (!ui.toasts?.isConnected) {
      ui.toasts = document.createElement('div');
      ui.toasts.className = 'ps-toasts';
      ui.toasts.setAttribute('role', 'status');
      ui.toasts.setAttribute('aria-live', 'polite');
      layer().append(ui.toasts);
    }
    const node = document.createElement('div');
    node.className = 'ps-toast is-' + tone;
    node.innerHTML = icon(tone) + '<span></span><button type="button" aria-label="Dismiss">' + icon('close') + '</button>';
    node.querySelector('span').textContent = text;
    const remove = () => node.remove();
    node.querySelector('button').addEventListener('click', remove);
    ui.toasts.append(node);
    while (ui.toasts.children.length > 3) ui.toasts.firstElementChild.remove();
    setTimeout(remove, duration || (tone === 'danger' ? 10000 : 6500));
    const entry = { id: Date.now() + Math.random(), text, tone, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) };
    ui.history = [entry, ...ui.history].slice(0, 20);
    window.dispatchEvent(new CustomEvent('simplify:notice', { detail: entry }));
  };

  /**
   * A Simplify confirmation dialog. Resolves to true/false, or to the typed text when `input` is set.
   * Options: title, message, confirmLabel, cancelLabel, tone ('danger' | 'primary'), input, inputType ('password'), placeholder, requireText.
   */
  const dialog = (opts = {}) => {
    return new Promise((resolve) => {
      const previous = document.activeElement;
      const wrap = document.createElement('div');
      wrap.className = 'ps-modal-layer';
      wrap.dataset.open = 'true';
      wrap.innerHTML =
        '<section class="ps-modal is-sm" role="alertdialog" aria-modal="true" aria-labelledby="ps-dialog-title">' +
        '<header class="ps-modal-head"><h2 id="ps-dialog-title"></h2></header>' +
        '<div class="ps-modal-body"><p class="ps-help" data-message></p></div>' +
        '<footer class="ps-modal-foot"><button type="button" class="ps-btn" data-cancel></button><button type="button" class="ps-btn" data-confirm></button></footer>' +
        '</section>';
      wrap.querySelector('h2').textContent = opts.title || t('common.are_you_sure');
      const message = wrap.querySelector('[data-message]');
      if (opts.message) message.textContent = opts.message;
      else message.remove();
      const cancel = wrap.querySelector('[data-cancel]');
      const confirm = wrap.querySelector('[data-confirm]');
      cancel.textContent = opts.cancelLabel || t('common.cancel');
      confirm.textContent = opts.confirmLabel || t('common.confirm');
      confirm.classList.add(opts.tone === 'danger' ? 'is-danger' : 'is-primary');
      let field = null;
      if (opts.input || opts.requireText) {
        const label = document.createElement('label');
        label.className = 'ps-field';
        label.textContent = opts.inputLabel || (opts.requireText ? t('common.type_to_confirm', { text: opts.requireText }) : '');
        field = document.createElement('input');
        field.placeholder = opts.placeholder || opts.requireText || '';
        field.autocomplete = 'off';
        if (opts.inputType === 'password') field.type = 'password';
        label.append(field);
        wrap.querySelector('.ps-modal-body').append(label);
        const check = () => (confirm.disabled = !!opts.requireText && field.value !== opts.requireText);
        field.addEventListener('input', check);
        check();
      }
      const finish = (value) => {
        wrap.remove();
        ui.dialogs--;
        if (!ui.dialogs) root.classList.remove('ps-ui-locked');
        try { previous?.focus?.(); } catch {}
        resolve(value);
      };
      cancel.addEventListener('click', () => finish(field ? null : false));
      confirm.addEventListener('click', () => !confirm.disabled && finish(field ? field.value : true));
      wrap.addEventListener('click', (event) => event.target === wrap && finish(field ? null : false));
      wrap.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { event.stopPropagation(); finish(field ? null : false); }
        if (event.key === 'Enter' && event.target === field && !confirm.disabled) finish(field.value);
        if (event.key === 'Tab') {
          const items = [...wrap.querySelectorAll('button:not(:disabled), input')];
          const index = items.indexOf(document.activeElement);
          const next = items[(index + (event.shiftKey ? -1 : 1) + items.length) % items.length];
          event.preventDefault();
          next?.focus();
        }
      });
      ui.dialogs++;
      root.classList.add('ps-ui-locked');
      const host = mountPoint();
      if (host !== ui.layer && !host.closest('.ps')) wrap.classList.toggle('dark', dark());
      host.append(wrap);
      (field || confirm).focus();
    });
  };

  window.Simplify = {
    t,
    tc,
    fill: (text, params = {}) => fill(String(text), params),
    choice,
    // A server's state from Pelican or Wings (running, install failed) as words in the panel's language.
    status: (state) => { const key = 'status.' + String(state || 'offline').replace(/ /g, '_'); const text = lookup(key); return text === key ? String(state) : text; },
    toast,
    confirm: dialog,
    prompt: (opts) => dialog({ ...opts, input: true }),
    history: () => ui.history.slice(),
    hideTooltip: hideTip,
  };

  // Inline style for the panel name in the top-left corner; the font stack comes from the server's whitelist.
  window.Simplify.brandTextStyle = (brand, stack, accent) => {
    const hex = (value) => /^#[0-9a-f]{6}$/i.test(value || '') ? value : null;
    const ink = hex(brand?.color1) || 'var(--ps-ink, currentColor)';
    const style = {
      'font-family': stack || 'inherit',
      'font-weight': String(Number(brand?.weight) || 700),
      ...(Number(brand?.spacing) ? { 'letter-spacing': Number(brand.spacing) / 100 + 'em' } : {}),
      'text-transform': brand?.uppercase ? 'uppercase' : 'none',
    };
    if (brand?.color_style !== 'gradient') return { ...style, background: 'none', color: ink, '-webkit-text-fill-color': ink };
    const end = hex(brand?.color2) || hex(accent) || 'var(--ps-brand, var(--ps-accent, #2563eb))';
    return { ...style, 'background-image': `linear-gradient(110deg, ${ink} 40%, ${end} 100%)`, 'background-clip': 'text', '-webkit-background-clip': 'text', color: 'transparent', '-webkit-text-fill-color': 'transparent' };
  };

  /* Web app ------------------------------------------------------------------------------------
     The panel can be added to a phone's home screen. The service worker only shows an offline page when the
     panel cannot be reached; Simplify offers "Install app" where the browser allows it (Android, desktop
     Chrome and Edge) and explains the Share menu on iPhone and iPad. */
  const webApp = {
    prompt: null,
    standalone: () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
    ios: () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
    /** 'prompt' (the browser can install), 'ios' (Share → Add to Home Screen) or '' (installed or not possible). */
    installable() {
      if (webApp.standalone()) return '';
      if (webApp.prompt) return 'prompt';
      return webApp.ios() && 'serviceWorker' in navigator ? 'ios' : '';
    },
    async install() {
      if (webApp.prompt) {
        const prompt = webApp.prompt;
        webApp.prompt = null;
        await prompt.prompt();
        const choice = await prompt.userChoice.catch(() => null);
        window.dispatchEvent(new CustomEvent('simplify:installable'));
        return choice?.outcome === 'accepted';
      }
      if (webApp.installable() === 'ios') await window.Simplify.confirm({ title: t('common.home_screen_title'), message: t('common.home_screen_message'), confirmLabel: t('common.ok'), cancelLabel: t('common.close') });
      return false;
    },
  };
  window.SimplifyApp = webApp;
  if (window.top === window) {
    // Installed: the app draws up to the screen edges and keeps clear of the notch and home bar itself.
    if (webApp.standalone()) {
      root.classList.add('ps-standalone');
      const viewport = document.querySelector('meta[name="viewport"]');
      if (viewport && !/viewport-fit/.test(viewport.content)) viewport.content += ', viewport-fit=cover';
    }
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      webApp.prompt = event;
      window.dispatchEvent(new CustomEvent('simplify:installable'));
    });
    window.addEventListener('appinstalled', () => {
      webApp.prompt = null;
      window.dispatchEvent(new CustomEvent('simplify:installable'));
    });
  }

  apply();
})();
