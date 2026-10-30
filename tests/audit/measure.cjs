// Everything measurable about the current document (also run inside frames).
const measure = (opts = {}) => {
  const root = document.documentElement, W = innerWidth, H = innerHeight;
  const r = { url: location.pathname + location.search, w: W, h: H, dark: root.classList.contains('dark'), fit: root.classList.contains('ps-fit'), problems: [], info: {} };
  const push = (kind, text) => r.problems.push({ kind, text });
  const name = (el) => (el.getAttribute('aria-label') || el.id || (typeof el.className === 'string' && el.className.trim().split(/\s+/).slice(0, 3).join('.')) || el.tagName.toLowerCase()).slice(0, 60);
  const text = (el) => (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 50);
  // A closed Filament sidebar sits off the screen on purpose: nothing in it counts.
  const parked = (el) => { const sb = el.closest('.fi-sidebar'); if (!sb) return false; const b = sb.getBoundingClientRect(); return b.right <= 0 || b.left >= W; };
  const visible = (el) => { if (!(el instanceof Element)) return false; const b = el.getBoundingClientRect(); if (b.width < 1 || b.height < 1) return false; const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || cs.opacity === '0') return false; for (let a = el; a; a = a.parentElement) { const c = a === el ? cs : getComputedStyle(a); if (c.display === 'none' || c.visibility === 'hidden') return false; if (a.hasAttribute('inert') || a.getAttribute('aria-hidden') === 'true' || a.hidden) return false; } return !parked(el); };
  // Between a child and the box that clips it: a pane that scrolls, a fixed or absolute layer, or an animation (ticker) means the child is reachable or placed on purpose.
  const between = (child, box) => { for (let a = child; a && a !== box; a = a.parentElement) { const c = getComputedStyle(a); if (/(fixed|absolute|sticky)/.test(c.position) || c.animationName !== 'none' || (c.transform !== 'none' && a !== child)) return true; if (a !== child && /(auto|scroll)/.test(c.overflowY + c.overflowX) && (a.scrollHeight > a.clientHeight + 2 || a.scrollWidth > a.clientWidth + 2)) return true; } return false; };
  const scrollAncestor = (el, axis) => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) { const cs = getComputedStyle(a); const o = axis === 'x' ? cs.overflowX : cs.overflowY; if (/(auto|scroll)/.test(o) && (axis === 'x' ? a.scrollWidth > a.clientWidth + 2 : a.scrollHeight > a.clientHeight + 2)) return a; } return null; };
  const all = [...document.querySelectorAll('body *')].filter(visible);
  // 1. The page itself.
  r.info.pageScrollH = root.scrollHeight; r.info.pageScrollW = root.scrollWidth;
  const bodyScroll = document.body.scrollHeight;
  if (root.scrollWidth > W + 2 || bodyScroll > H + 2 && root.scrollWidth > W + 2) push('hscroll', `page is wider than the window (${root.scrollWidth} > ${W})`);
  if (root.scrollHeight > H + 2) {
    // Whether the page itself scrolls, read from the styles: a test scroll would fire a scroll event, and Simplify
    // closes its tooltips on scroll, so the screenshot taken next would lose them.
    const ov = (el) => getComputedStyle(el).overflowY;
    const moved = !['hidden', 'clip'].includes(ov(root)) && !['hidden', 'clip'].includes(ov(document.body)) && getComputedStyle(root).position !== 'fixed' && getComputedStyle(document.body).position !== 'fixed';
    push(moved ? 'pagescroll' : 'tallnoscroll', `page is taller than the window (${root.scrollHeight} > ${H})${moved ? ', the page scrolls' : ', nothing scrolls'}`);
  }
  // 2. Text cut off: single-line text hidden without an ellipsis, and text shortened with an ellipsis (info).
  const cut = [], ell = [];
  for (const el of all) {
    const cs = getComputedStyle(el);
    if (el.matches('.sr-only, .fi-sr-only, script, style, svg, svg *')) continue;
    if (cs.overflowX === 'hidden' && el.scrollWidth > el.clientWidth + 2 && (el.textContent || '').trim() && !el.querySelector('svg, img, canvas')) {
      if (cs.textOverflow === 'ellipsis' && cs.whiteSpace === 'nowrap') ell.push(name(el) + ': "' + text(el) + '"');
      else if (cs.whiteSpace === 'nowrap' || el.childElementCount === 0) cut.push(name(el) + ': "' + text(el) + '" (' + el.scrollWidth + '>' + el.clientWidth + ')');
    }
  }
  if (cut.length) push('cut', 'text cut off without an ellipsis: ' + cut.slice(0, 6).join(' | ') + (cut.length > 6 ? ` (+${cut.length - 6})` : ''));
  if (ell.length) r.info.ellipsis = ell.slice(0, 12);
  // 3. Controls off the screen (not inside a pane that scrolls to them).
  const off = [];
  for (const el of all) {
    if (!el.matches('button, a, input, select, textarea, [role=button], [role=tab]')) continue;
    const b = el.getBoundingClientRect();
    if (b.width < 8 || b.height < 8) continue;
    const right = b.right > W + 2 && !scrollAncestor(el, 'x'), bottom = b.bottom > H + 2 && !scrollAncestor(el, 'y') && !(root.scrollHeight > H + 2), left = b.left < -2 && !scrollAncestor(el, 'x') && scrollX === 0, top = b.top < -2 && !scrollAncestor(el, 'y') && scrollY === 0;
    if ((right || bottom || left || top) && off.length < 8) off.push(`${name(el)} "${text(el)}" (${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)})`);
  }
  if (off.length) push('offscreen', 'control off the screen: ' + off.join(' | '));
  // 4. Popups, menus and dialogs must sit inside the window.
  for (const el of document.querySelectorAll('[role=dialog], [role=menu], .fi-dropdown-panel, .fi-modal-window, .ps-dropdown, .ps-modal, .ps-sheet, .ps-account-menu, .ps-nav-items, .fi-global-search-results-ctn, .ps-phone-more-menu, .ps-tooltip.is-visible')) {
    if (!visible(el) || el.closest('.fi-sidebar')) continue;
    const b = el.getBoundingClientRect();
    const tall = el.scrollHeight > el.clientHeight + 2;
    r.info.popups = r.info.popups || []; r.info.popups.push(`${name(el)} ${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)}${tall ? ' scrolls' : ''}`);
    // Not counted: the phone navigation's menus, which sit in the page flow and scroll with it, and a Filament dialog
    // that is taller than the window but scrolls inside itself (on a phone Pelican's own dialogs are always taken as such).
    const inFlow = (el.closest('.ps-nav-items') && getComputedStyle(el).position === 'static') || (el.matches('.fi-modal-window') && (tall || W <= 600));
    if (!inFlow && (b.left < -2 || b.top < -2 || b.right > W + 2 || b.bottom > H + 2)) push('popupoff', `popup partly off the screen: ${name(el)} (${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)} in ${W}x${H})`);
  }
  // 5. Children sticking out of a box that clips (overflow hidden/clip): the notifications list, the account menu buttons.
  const stick = []; const reported = new Set();
  for (const el of all) {
    const cs = getComputedStyle(el);
    if (!/(hidden|clip)/.test(cs.overflowX) && !/(hidden|clip)/.test(cs.overflowY)) continue;
    if (el.matches('svg, svg *, .xterm, .xterm *, canvas, [aria-hidden=true], .ps-spark, .ps-account-menu, .ps-dropdown, .fi-dropdown-panel, [x-transition], .ps-motd-preview, .ps-marquee, .ps-ticker, .ps-ticker *, .ps-bar-text, .ps-announcement-bar *')) continue;
    if ([...reported].some((p) => p.contains(el))) continue;
    const b = el.getBoundingClientRect();
    if (b.width < 20 || b.height < 20) continue;
    for (const c of el.querySelectorAll('button, a, input, select, textarea, img, li, p, h1, h2, h3, strong, span, div')) {
      if (!visible(c) || c.matches('svg *') ) continue;
      const cb = c.getBoundingClientRect();
      if (cb.width < 6 || cb.height < 6) continue;
      const dx = Math.max(cb.right - b.right, b.left - cb.left), dy = Math.max(cb.bottom - b.bottom, b.top - cb.top);
      const clipX = /(hidden|clip)/.test(cs.overflowX) && dx > 4, clipY = /(hidden|clip)/.test(cs.overflowY) && dy > 4;
      if ((clipX || clipY) && !c.matches('.sr-only, .fi-sr-only, [x-cloak]') && !between(c, el)) {
        if (el.scrollHeight <= el.clientHeight + 2 && el.scrollWidth <= el.clientWidth + 2 && !clipX && cb.top >= b.top) continue;
        stick.push(`${name(c)} "${text(c)}" sticks ${Math.round(Math.max(dx, dy))}px out of ${name(el)}`); reported.add(el); break;
      }
    }
    if (stick.length >= 8) break;
  }
  if (stick.length) push('clipped', 'content clipped by its box: ' + stick.join(' | '));
  // 6. Panes that scroll inside the page (info), and the content card on Simplify pages.
  const panes = all.filter((el) => { const cs = getComputedStyle(el); return /(auto|scroll)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 4 && !el.matches('.xterm-viewport'); }).map((el) => `${name(el)} ${el.clientHeight}/${el.scrollHeight}`);
  if (panes.length) r.info.panes = panes.slice(0, 10);
  const main = document.querySelector('.fi-main-ctn > .fi-main');
  if (main && visible(main)) { const b = main.getBoundingClientRect(); r.info.card = `${Math.round(b.top)}..${Math.round(b.bottom)} of ${H}` + (r.fit ? ' fit=' + root.style.getPropertyValue('--ps-fit') : ''); if (r.fit && b.bottom > H + 2) push('cardoff', `content card ends below the window (${Math.round(b.bottom)} > ${H})`); }
  // 7. The console: output area, its card and the command bar must share one colour; the last row must sit above the command bar.
  const term = document.getElementById('terminal');
  if (term && visible(term)) {
    const card = term.parentElement, bar = card.querySelector(':scope > div:has(#send-command), form:has(#send-command)');
    const bg = (el) => el && getComputedStyle(el).backgroundColor;
    r.info.console = { card: bg(card), terminal: bg(term), xterm: bg(term.querySelector('.xterm')), viewport: bg(term.querySelector('.xterm-viewport')), bar: bg(bar) };
    const rows = term.querySelector('.xterm-rows'), screenEl = term.querySelector('.xterm-screen');
    if (screenEl) { const sb = screenEl.getBoundingClientRect(), tb = term.getBoundingClientRect(), barB = bar?.getBoundingClientRect();
      r.info.consoleBox = `screen ${Math.round(sb.left)},${Math.round(sb.top)} ${Math.round(sb.width)}x${Math.round(sb.height)}; terminal ${Math.round(tb.left)},${Math.round(tb.top)} ${Math.round(tb.width)}x${Math.round(tb.height)}` + (barB ? `; bar top ${Math.round(barB.top)}` : '');
      if (sb.right > tb.right + 1 || sb.bottom > tb.bottom + 1) push('consoleover', `console rows run out of the output box (screen bottom ${Math.round(sb.bottom)} / right ${Math.round(sb.right)}, box bottom ${Math.round(tb.bottom)} / right ${Math.round(tb.right)})`);
      if (barB && sb.bottom > barB.top + 1) push('consoleover', `last console row sits behind the command bar (${Math.round(sb.bottom)} > ${Math.round(barB.top)})`);
    }
    const colours = new Set([r.info.console.card, r.info.console.bar].filter((c) => c && c !== 'rgba(0, 0, 0, 0)'));
    if (colours.size > 1) push('colour', `console card and command bar differ in colour: ${[...colours].join(' vs ')}`);
  }
  // 8. Elements wider than the window (any), as a hint where sideways overflow comes from.
  if (root.scrollWidth > W + 2) { const wide = all.filter((el) => el.getBoundingClientRect().right > W + 2 && !scrollAncestor(el, 'x')).slice(0, 5).map((el) => name(el)); if (wide.length) r.info.wide = wide; }
  // 8b. The top bar: the panel name, the seasonal badges and the buttons on the right may not overlap.
  const brandEl = document.querySelector('.ps-top .ps-brand'), badgesEl = document.querySelector('.ps-top .ps-season-badges'), actionsEl = document.querySelector('.ps-top .ps-top-actions');
  if (brandEl && visible(brandEl)) {
    const bb = brandEl.getBoundingClientRect(), sb = badgesEl && visible(badgesEl) ? badgesEl.getBoundingClientRect() : null, ab = actionsEl && visible(actionsEl) ? actionsEl.getBoundingClientRect() : null;
    const over = (a, b) => a && b && a.right > b.left + 3 && b.right > a.left + 3 && a.bottom > b.top + 3 && b.bottom > a.top + 3;
    r.info.topbar = `brand ${Math.round(bb.left)}..${Math.round(bb.right)}` + (sb ? `, badges ${Math.round(sb.left)}..${Math.round(sb.right)}` : '') + (ab ? `, actions ${Math.round(ab.left)}..${Math.round(ab.right)}` : '');
    if (over(bb, sb)) push('overlap', `the seasonal badges overlap the panel name (${r.info.topbar})`);
    if (over(sb, ab)) push('overlap', `the top bar buttons overlap the seasonal badges (${r.info.topbar})`);
    if (over(bb, ab)) push('overlap', `the top bar buttons overlap the panel name (${r.info.topbar})`);
    if (bb.right > W + 2 || (sb && sb.right > W + 2)) push('overlap', `panel name or badges run out of the window (${r.info.topbar}, window ${W})`);
  }
  // 9. Simplify's style rules (project memory "UI-regels"): one content box, no box in a box, action buttons in the
  //    title bar of host mode and never under the form, Simplify's look instead of bare Filament, and the dropdowns.
  const isFrame = root.classList.contains('ps-frame'), native = isFrame && root.dataset.psFrame !== 'settings';
  const simplifyAdmin = (opts.simplifyAdmin || []).some((p) => location.pathname.startsWith(p));
  const simplifyPage = !!document.querySelector('.ps') || isFrame || simplifyAdmin || !!document.querySelector('.s-settings, .s-health, .s-people');
  r.info.scope = isFrame ? (root.dataset.psTree ? 'frame-tree' : 'frame') : simplifyPage ? 'simplify' : 'pelican';
  const layer = (el) => !!el.closest('[role=dialog], .fi-modal, .fi-dropdown-panel, .ps-dropdown, .ps-nav-items, .ps-account-menu, .fi-sidebar, .fi-topbar, .ps-top, .ps-tabbar, .ps-statusbar, .ps-host-head, .ps-host-menu, .ps-server-menu, .ps-phone-more-menu');
  const alpha = (c) => { const m = c && c.match(/rgba?\(([^)]+)\)/); if (!m) return 0; const p = m[1].split(',').map(parseFloat); return p.length > 3 ? p[3] : 1; };
  // A box: a sizeable block with its own visible border, shadow or outline; fields, buttons, tables and media are not boxes.
  const isBox = (el) => {
    if (el.matches('button, a, input, select, textarea, table, thead, tbody, tr, td, th, pre, code, img, canvas, iframe, svg, svg *, label, legend, nav, .fi-input-wrp, .fi-fo-field-wrp *, .fi-ta-table, .fi-tabs, .fi-sc-tabs-tab, .ps-server-card, .ps-server-card *, .xterm, .xterm *, .ps-console, .ps-console *, .fi-btn, .fi-ac, .ps-btn, [role=tablist], [role=tab], .fi-badge, .ps-stat, .ps-stat *, .fi-fo-rich-editor, .fi-fo-rich-editor *, .fi-fo-markdown-editor *, .fi-fo-code-editor *, .fi-fo-color-picker *, .fi-fo-file-upload *, .fi-fo-repeater *, .fi-fo-builder *, .fi-fo-key-value *, .fi-fo-checkbox-list *, .fi-fo-radio *, .fi-fo-toggle-buttons *, .fi-fo-select *, .fi-ta-cell *, .ps-health-tab, .ps-health-nodes, .ps-health-nodes *, .ps-motd-preview, .ps-motd-preview *, .ps-embed-preview, .ps-embed-preview *, .ps-code, .ps-code *, .fi-fo-placeholder *, .fi-section-header, .fi-section-footer, .fi-sc-fieldset, .fi-fieldset, .fi-fieldset *, [class*="preview"], [class*="preview"] *')) return false;
    const b = el.getBoundingClientRect(); if (b.width < 220 || b.height < 72) return false;
    const cs = getComputedStyle(el);
    if (/(fixed|absolute|sticky)/.test(cs.position)) return false;
    const border = ['Top', 'Right', 'Bottom', 'Left'].filter((s) => parseFloat(cs['border' + s + 'Width']) >= 1 && cs['border' + s + 'Style'] !== 'none' && alpha(cs['border' + s + 'Color']) > 0.05).length;
    const shadow = cs.boxShadow !== 'none' && cs.boxShadow.split(/,(?![^(]*\))/).some((seg) => { const col = seg.match(/(rgba?|oklab|oklch|hsla?)\([^)]*\)/)?.[0] || ''; const nums = seg.replace(col, '').trim().split(/\s+/).map(parseFloat); const a = /\/\s*([\d.]+)\s*\)$/.exec(col)?.[1] ?? (col.startsWith('rgba') ? col.split(',')[3] : 1); return parseFloat(a) > 0.03 && (Math.abs(nums[2] || 0) >= 1 || Math.abs(nums[3] || 0) >= 1 || Math.abs(nums[1] || 0) >= 1); });
    const outline = parseFloat(cs.outlineWidth) >= 1 && cs.outlineStyle !== 'none' && alpha(cs.outlineColor) > 0.05;
    // Its own background colour, different from the one behind it, with rounded corners: a card.
    let behind = 'rgba(0, 0, 0, 0)'; for (let a = el.parentElement; a; a = a.parentElement) { const c = getComputedStyle(a).backgroundColor; if (alpha(c) > 0.03) { behind = c; break; } }
    const own = cs.backgroundColor, tinted = alpha(own) > 0.03 && own !== behind && parseFloat(cs.borderRadius) >= 4;
    return border >= 3 || shadow || outline || tinted;
  };
  if (simplifyPage) {
    const boxes = all.filter((el) => !layer(el) && isBox(el));
    const nested = [];
    for (const outer of boxes) for (const inner of boxes) {
      if (inner === outer || !outer.contains(inner)) continue;
      // A box that fills its parent box edge to edge is the same box drawn twice (layout wrapper), not a box in a box.
      const ob = outer.getBoundingClientRect(), ib = inner.getBoundingClientRect();
      if (ib.width >= ob.width - 2 && ib.height >= ob.height - 2) continue;
      if (nested.some(([o]) => o.contains(inner) && o !== outer && outer.contains(o))) continue;
      nested.push([outer, inner]); break;
    }
    // Inside the content box every box is a box in a box: host mode's frame (the whole document), or the server page's
    // card (.fi-main; on the console page the console card itself is the one box).
    const serverFrame = isFrame && root.dataset.psFrame === 'server';
    const region = (isFrame && native && !serverFrame) ? document.body : (r.fit || serverFrame) ? document.querySelector('.fi-main-ctn > .fi-main') : null;
    const consolePage = !!document.querySelector('.ps-server-top.is-console');
    if (region) for (const el of boxes) if (el !== region && region.contains(el) && !nested.some(([, i]) => i === el) && !(consolePage && el.matches('.fi-wi-widget, .ps-console'))) nested.push([null, el]);
    if (nested.length) push('boxinbox', 'box inside a box: ' + nested.slice(0, 5).map(([o, i]) => `${name(i)} (${Math.round(i.getBoundingClientRect().width)}x${Math.round(i.getBoundingClientRect().height)}) in ${o ? name(o) : 'the content box'}`).join(' | ') + (nested.length > 5 ? ` (+${nested.length - 5})` : ''));
    r.info.boxes = boxes.slice(0, 8).map((el) => { const b = el.getBoundingClientRect(); return `${name(el)} ${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)}`; });
    // Action buttons that belong in the title bar: a page header's actions, a table's toolbar buttons, a form's save.
    const actionSel = '.fi-header-actions-ctn .fi-ac > :is(button, a), .fi-ta-header-toolbar > .fi-ta-actions > :is(button, a), .s-bar button, .fi-form-actions button, form > .fi-ac > button, .fi-fo-actions button, button[type=submit]:not(.fi-modal *)';
    const actions = [...document.querySelectorAll(actionSel + ', .s-toolbar :is(button, a), .s-btn-primary')].filter((el) => visible(el) && !layer(el) && !el.closest('.fi-ta-ctn .fi-ta-ctn, .fi-section .fi-ta-ctn, .fi-resource-relation-managers, .fi-sc-tabs .fi-ta-ctn, .fi-ta-cell, .fi-ta-row, .fi-global-search, li, tr, .s-row, .s-person, .s-item, [class*="-row"], [class*="-item"]'));
    // Filament's toggle buttons and radio groups are form fields, not buttons.
    const fiButtons = all.filter((el) => el.matches('.fi-btn') && !layer(el) && !el.closest('.fi-ta-ctn, .fi-ac-btn-group, .fi-fo-repeater, .fi-fo-builder, .fi-fo-file-upload, .fi-pagination, .fi-fo-actions-ctn.is-ps, .fi-fo-toggle-buttons, .fi-fo-radio, .fi-fo-checkbox-list, .fi-btn-group'));
    if (isFrame && native) {
      let head = null; try { head = window.frameElement?.closest('.ps-host-main')?.querySelector(':scope > .ps-host-head.is-page') || null; } catch {}
      const headShown = head ? parent.getComputedStyle(head).display !== 'none' : null;
      r.info.hostHead = head ? (headShown ? 'shown' : 'hidden') : 'unreachable';
      if (head && headShown) {
        const tools = head.querySelector('.ps-frame-tools'); const n = tools ? tools.querySelectorAll('button, a, input').length : 0;
        r.info.frameTools = n;
        if (actions.length) push('savebelow', 'action button in the page instead of in host mode\'s title bar: ' + actions.slice(0, 5).map((el) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().trim().split(/\s+/).slice(0, 2).join('.')} "${text(el)}" at y=${Math.round(el.getBoundingClientRect().top)}`).join(' | '));
        const sources = document.querySelectorAll('.fi-header-actions-ctn .fi-ac > :is(button, a), .fi-ta-header-toolbar > .fi-ta-actions > :is(button, a), .s-settings > .s-bar button[type=submit]').length;
        if (sources && !n) push('toolslost', `the page has ${sources} action button(s) but host mode's title bar shows none`);
        const header = document.querySelector('.fi-header'); if (header && visible(header) && !header.closest('.fi-modal')) r.info.frameHeader = text(header);
        const crumbs = document.querySelector('.fi-breadcrumbs'); if (crumbs && visible(crumbs)) push('filamentlook', 'Filament breadcrumbs shown inside host mode\'s frame: "' + text(crumbs) + '"');
      }
      if (fiButtons.length) push('filamentlook', 'bare Filament button(s) in host mode\'s frame: ' + fiButtons.slice(0, 4).map((el) => `"${text(el)}" at y=${Math.round(el.getBoundingClientRect().top)}`).join(' | '));
    } else if (!isFrame && r.fit && actions.length) {
      // A server page's content card: its save button must sit in the card's head, not under the form.
      const main = document.querySelector('.fi-main-ctn > .fi-main'); const mb = main?.getBoundingClientRect();
      const low = actions.filter((el) => mb && el.getBoundingClientRect().top > mb.top + 72 && !el.closest('.fi-header'));
      if (low.length) r.info.actionsBelow = low.slice(0, 4).map((el) => `"${text(el)}" at y=${Math.round(el.getBoundingClientRect().top)} (card ${Math.round(mb.top)}..${Math.round(mb.bottom)})`);
    } else if (!isFrame && simplifyAdmin) {
      if (actions.length) r.info.actionsBelow = actions.slice(0, 4).map((el) => `"${text(el)}" at y=${Math.round(el.getBoundingClientRect().top)}`);
      if (fiButtons.length) r.info.filamentButtons = fiButtons.slice(0, 4).map((el) => `"${text(el)}"`);
    }
    // Host mode's own page: the content box must end inside the window.
    const hostMain = document.querySelector('.ps-host-main');
    if (hostMain && visible(hostMain)) { const b = hostMain.getBoundingClientRect(); r.info.box = `${Math.round(b.top)}..${Math.round(b.bottom)} of ${H}`; if (b.bottom > H + 2) push('boxoff', `host mode's content box ends below the window (${Math.round(b.bottom)} > ${H})`); }
    else if (main && visible(main) && r.fit) { const b = main.getBoundingClientRect(); r.info.box = `${Math.round(b.top)}..${Math.round(b.bottom)} of ${H}`; }
  }
  // Dropdowns under the top bar: at most two thirds of the window, scrolling only when the content needs it,
  // and the arrow (the ::after of the button's wrapper) centred under the button.
  for (const dd of document.querySelectorAll('.ps-dropdown')) {
    // Inside a phone's menu a dropdown is a static block of the menu, not a floating panel.
    if (!visible(dd) || (dd.closest('.ps-nav-items') && getComputedStyle(dd).position === 'static')) continue;
    const b = dd.getBoundingClientRect(), limit = Math.round(H * 2 / 3);
    const scrolls = dd.scrollHeight > dd.clientHeight + 2;
    r.info.dropdown = r.info.dropdown || []; r.info.dropdown.push(`${name(dd)} ${Math.round(b.height)}px of ${limit} max${scrolls ? ', scrolls' : ''}`);
    if (b.height > limit + 2) push('dropdowntall', `dropdown higher than two thirds of the window: ${name(dd)} ${Math.round(b.height)}px > ${limit}px`);
    // A fixed bar under the dropdown (a phone's tab bar) is the floor; otherwise the window's edge with a 64px margin.
    const floor = Math.min(H, ...all.filter((el) => getComputedStyle(el).position === 'fixed' && el.getBoundingClientRect().top >= b.bottom - 4 && el.getBoundingClientRect().width > W / 2).map((el) => el.getBoundingClientRect().top));
    if (scrolls && b.height < limit - 24 && (b.bottom < floor - 64)) push('dropdownscroll', `dropdown scrolls although it could be taller: ${name(dd)} ${Math.round(b.height)}px of ${limit}px allowed`);
    const wrap = dd.parentElement, btn = wrap?.querySelector(':scope > button, :scope > a, :scope > [role=button]');
    if (wrap && btn) {
      const pa = getComputedStyle(wrap, '::after');
      if (pa.content !== 'none' && pa.content !== 'normal' && pa.transform !== 'none' && parseFloat(pa.width) > 0) {
        const wb = wrap.getBoundingClientRect(), width = parseFloat(pa.width);
        const left = pa.left !== 'auto' ? wb.left + parseFloat(pa.left) : wb.right - parseFloat(pa.right) - width;
        const centre = left + width / 2, bb = btn.getBoundingClientRect(), bc = bb.left + bb.width / 2;
        r.info.arrow = r.info.arrow || []; r.info.arrow.push(`${name(dd)} arrow ${Math.round(centre)} button ${Math.round(bc)}`);
        if (Math.abs(centre - bc) > 3) push('arrowoff', `dropdown arrow not centred under its button: ${name(dd)} arrow at ${Math.round(centre)}, button centre ${Math.round(bc)}`);
      } else { r.info.arrow = r.info.arrow || []; r.info.arrow.push(`${name(dd)} no arrow`); }
    }
  }
  return r;
};

module.exports = { measure };
