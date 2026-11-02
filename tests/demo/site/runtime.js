// Plays the recorded panel back in the browser: the page's own Livewire, Alpine and Filament code runs as usual, and
// every request it would send to the panel is answered from the recording (replay/*.json). Calls the recording never
// saw get a short "Not in the demo" note. The console is fed by a stand-in websocket with the recorded log lines.
// Loaded first in every recorded page, after key.js; window.DEMO_TREE names the recording (admin or customer).
(() => {
  if (window.demoNote) return;
  const K = window.DemoKey;
  const me = document.currentScript.src;
  const site = me.slice(0, me.lastIndexOf('/demo/') + 1);
  const root = site + (window.DEMO_TREE || 'admin') + '/';
  const MARK = '__DEMO_ROOT__/';
  const shards = {};
  const shard = (name) => (shards[name] ??= realFetch(site + 'demo/replay/' + (window.DEMO_TREE || 'admin') + '/' + name + '.json').then((r) => (r.ok ? r.json() : {})).catch(() => ({})));
  const realFetch = window.fetch.bind(window);
  // The marker in any spelling (slashes escaped inside JSON) becomes this recording's full address, spelled alike.
  const unmark = (text) => text.replace(/__DEMO_ROOT__(\\*)\//g, (m, esc) => root.split('/').join(esc + '/'));
  // Before Alpine and Livewire read the page: the marker in attributes becomes the full address.
  document.addEventListener('DOMContentLoaded', () => {
    for (const el of document.querySelectorAll('*')) for (const a of el.attributes) if (a.value.includes('__DEMO_ROOT__')) a.value = unmark(a.value);
  });

  // ---- Note ---------------------------------------------------------------------------------------------------
  let noteTimer;
  function note(text) {
    let el = document.getElementById('demo-note');
    if (!el) {
      el = document.createElement('div');
      el.id = 'demo-note';
      el.setAttribute('role', 'status');
      el.style.cssText = 'position:fixed;z-index:2147483000;left:50%;bottom:calc(20px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);max-width:min(92vw,420px);padding:10px 14px;border-radius:10px;font:500 13px/1.4 system-ui,sans-serif;background:#18181b;color:#fafafa;box-shadow:0 6px 24px rgba(0,0,0,.25);pointer-events:none;transition:opacity .2s';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.style.opacity = '1';
    clearTimeout(noteTimer);
    noteTimer = setTimeout(() => { el.style.opacity = '0'; }, 2600);
  }
  window.demoNote = note;
  const NOT = 'Not in the demo: this was not recorded.';

  // ---- Livewire ---------------------------------------------------------------------------------------------------
  // A recorded answer carries the component ids of the recording; the ids on this page differ, so they are swapped.
  function ids(recorded, snap) {
    const map = [[recorded.id, snap.memo.id]];
    const now = snap.memo.children || {};
    for (const [key, child] of Object.entries(recorded.children || {})) if (now[key]) map.push([child[1], now[key][1]]);
    return map.filter(([a, b]) => a && b && a !== b);
  }
  async function livewire(body) {
    const req = JSON.parse(body);
    let missed = false;
    const components = [];
    for (const c of req.components) {
      const [name, keys, snap] = K.keys(c);
      const table = await shard('lw-' + K.h(name));
      const hit = (table.items || [])[keys.map((k) => (table.keys || {})[k]).find((i) => i !== undefined)];
      // The console asks the panel for a Wings token once its socket opens; the stand-in socket takes any token.
      if (!hit && (c.calls || []).some((x) => x.method === '__dispatch' && x.params[0] === 'token-request')) {
        components.push({ snapshot: c.snapshot, effects: { returns: [null], dispatches: [{ name: 'sendAuthRequest', params: { token: 'demo' } }] } });
        continue;
      }
      if (!hit) {
        if (window.DEMO_DEBUG) console.log('demo miss', name, JSON.stringify(keys), JSON.stringify(c.calls), JSON.stringify(c.updates));
        // A call that only saves what the page already shows (an order, a preference) quietly does nothing; a call
        // that would open or show something new gets the note.
        if ((c.calls || []).some((x) => /^(mount|callMounted|open|show|view|load|create|edit)/i.test(x.method))) missed = true;
        components.push({ snapshot: c.snapshot, effects: { returns: (c.calls || []).map(() => null) } });
        continue;
      }
      let text = unmark(hit.res);
      for (const [a, b] of ids(hit.req, snap)) text = text.split(a).join(b);
      components.push(JSON.parse(text));
    }
    if (missed) note(NOT);
    return new Response(JSON.stringify({ components, assets: [] }), { status: 200, headers: { 'Content-Type': 'application/json' } });
  }

  // ---- Other requests (panel API, page fragments) ---------------------------------------------------------------
  async function other(method, url, body) {
    const path = url.pathname.slice(new URL(root).pathname.length - 1) + url.search;
    const table = await shard('x-' + K.h(method + ' ' + path.split('?')[0]));
    const hit = table[K.h(method + ' ' + path + ' ' + (method === 'GET' ? '' : body || ''))] || table['*'];
    if (hit) return new Response(unmark(hit.body), { status: hit.status, headers: { 'Content-Type': hit.type } });
    if (method !== 'GET') note(NOT);
    return new Response('{"errors":[{"detail":"Not in the demo"}]}', { status: 404, headers: { 'Content-Type': 'application/json' } });
  }

  window.fetch = async (input, init = {}) => {
    const url = new URL(typeof input === 'string' || input instanceof URL ? String(input) : input.url, location.href);
    const method = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    let body = init.body;
    if (body === undefined && input instanceof Request && method !== 'GET') body = await input.clone().text();
    if (url.href.startsWith(site)) {
      if (method === 'POST' && /\/livewire(-[0-9a-f]+)?\/update(\.html)?$/.test(url.pathname)) return livewire(String(body));
      const last = url.pathname.split('/').pop();
      // Files of the site (pages, scripts, styles, images) come from the host; a page that is missing gets the note.
      if (method === 'GET' && /\.[a-z0-9]{1,8}$/i.test(last)) {
        const r = await realFetch(input, init);
        if (!r.ok && last.endsWith('.html')) { note(NOT); throw new TypeError('demo: page not recorded'); }
        return r;
      }
      return other(method, url, typeof body === 'string' ? body : '');
    }
    // Nothing leaves the demo except the stylesheets and fonts it was recorded with.
    if (method !== 'GET') { note(NOT); return new Response('', { status: 204 }); }
    return realFetch(input, init);
  };

  // Plain links and forms to pages that were not recorded.
  addEventListener('click', (e) => {
    const a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank' || e.defaultPrevented) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || /^(javascript|mailto|tel):/.test(href)) return;
    const u = new URL(href, location.href);
    if (!u.href.startsWith(site)) { e.preventDefault(); window.open(u.href, '_blank', 'noopener'); return; }
    if (!(window.DEMO_PAGES || []).length) return;
    const rel = u.pathname.slice(new URL(site).pathname.length);
    if (rel.endsWith('.html') && !window.DEMO_PAGES.includes(rel)) { e.preventDefault(); e.stopPropagation(); note(NOT); }
  }, true);
  addEventListener('submit', (e) => { if (!e.target.hasAttribute('wire:submit')) { e.preventDefault(); note(NOT); } }, true);

  // ---- Console websocket ----------------------------------------------------------------------------------------
  const RealWS = window.WebSocket;
  class DemoSocket extends EventTarget {
    constructor(url) {
      super();
      this.url = String(url); this.readyState = 0; this.protocol = ''; this.extensions = ''; this.binaryType = 'blob';
      this.uuid = (this.url.match(/servers\/([0-9a-f-]{8,})/) || [])[1] || '';
      setTimeout(() => { this.readyState = 1; this.emit('open'); }, 50);
    }
    emit(type, data) {
      const ev = type === 'message' ? new MessageEvent('message', { data }) : new Event(type);
      const fn = this['on' + type]; if (fn) fn.call(this, ev);
      this.dispatchEvent(ev);
    }
    say(event, args) { if (this.readyState === 1) this.emit('message', JSON.stringify({ event, args })); }
    async send(raw) {
      let m; try { m = JSON.parse(raw); } catch { return; }
      const servers = await shard('servers');
      const s = servers[this.uuid] || Object.values(servers).find((x) => this.uuid && x.uuid.startsWith(this.uuid)) || { state: 'running', console: [] };
      if (m.event === 'auth') {
        this.say('auth success');
        this.say('status', [s.state]);
        clearInterval(this.timer);
        this.timer = setInterval(() => this.say('stats', [JSON.stringify({ memory_bytes: (s.memory || 900) * 1e6 * (0.97 + Math.random() * 0.06), memory_limit_bytes: 4096e6, cpu_absolute: (s.cpu ?? 12) * (0.8 + Math.random() * 0.4), network: { rx_bytes: 1e6, tx_bytes: 2e6 }, uptime: 3600e3, state: s.state, disk_bytes: 900e6 })]), 2000);
      } else if (m.event === 'send logs') {
        for (const line of s.console || []) this.say('console output', [line]);
      } else if (m.event === 'send command') {
        this.say('console output', ['> ' + (m.args || [])[0]]);
        this.say('console output', ['[demo] Commands are not run in the demo.']);
      } else if (m.event === 'set state') {
        note('Power buttons do nothing in the demo.');
      }
    }
    close() { clearInterval(this.timer); this.readyState = 3; this.emit('close'); }
  }
  for (const k of ['CONNECTING', 'OPEN', 'CLOSING', 'CLOSED']) DemoSocket[k] = RealWS[k];
  window.WebSocket = function (url, protocols) {
    return /\/api\/servers\/|\/ws\b/.test(String(url)) ? new DemoSocket(url) : new RealWS(url, protocols);
  };
  Object.assign(window.WebSocket, { CONNECTING: 0, OPEN: 1, CLOSING: 2, CLOSED: 3 });
})();
