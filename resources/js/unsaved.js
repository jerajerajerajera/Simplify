/* Unsaved changes (P-38): nothing in Simplify saves itself.
   A form marks itself with data-simplify-unsaved (optionally the Livewire properties to watch, comma separated; a
   Filament page watches "data" by default) and, when its save is not the form's own submit, data-simplify-save with
   the Livewire method. As soon as what it holds differs from what was loaded or last saved, a bar says "Changes made"
   with Discard and Save. Leaving the page with changes asks first: a link, a menu click (wire:navigate), going back,
   or closing the tab (the browser's own question). Add-ons use the same two attributes; on an older Simplify they do
   nothing. */
(() => {
  if (window.SimplifyUnsaved) return;
  const SELECTOR = '[data-simplify-unsaved]';
  const words = (key) => (window.simplifyUnsavedText || {})[key] || {
    changes_made: 'Changes made', save: 'Save', discard: 'Discard', leave_title: 'Unsaved changes',
    leave_message: 'Your changes on this page are not saved yet. Save them before you leave?', failed: 'Not saved. Check the fields and try again.',
  }[key];
  const cancelWord = () => (window.simplifyUnsavedText || {}).cancel || 'Cancel';
  const forms = new Map();
  let bar = null;
  let leaving = false;

  const livewire = (el) => {
    const host = el.closest('[wire\\:id]');
    try { return host && window.Livewire ? window.Livewire.find(host.getAttribute('wire:id')) : null; } catch { return null; }
  };
  /** A field typed back to what it was is no change: 20 and "20" are the same, and so are an empty field and none. */
  const same = (key, value) => (typeof value === 'number' ? String(value) : value === '' ? null : value);
  /** What the form holds now: its Livewire properties, or for a plain form the values of its fields. */
  const read = (el) => {
    const wire = livewire(el);
    if (wire) {
      const all = wire.__instance?.ephemeral || {};
      const named = (el.dataset.simplifyUnsaved || '').split(',').map((name) => name.trim()).filter(Boolean);
      const props = named.length ? named : ('data' in all ? ['data'] : Object.keys(all));
      return JSON.stringify(props.map((name) => { try { return wire.$get(name); } catch { return all[name]; } }), same);
    }
    return JSON.stringify([...el.querySelectorAll('input, select, textarea')].map((field) => (['checkbox', 'radio'].includes(field.type) ? field.checked : field.value)));
  };
  const entry = (el) => {
    let found = forms.get(el);
    // A Livewire form waits until Livewire knows it, so what it starts from is its Livewire state, never its fields.
    if (!found && el.closest('[wire\\:id]') && !livewire(el)) return null;
    if (!found) {
      found = { el, base: read(el), dirty: false, saving: false };
      forms.set(el, found);
    }
    return found;
  };
  /* A browser that remembers the admin's own password fills it into the first password field it sees, here an API
     key (CurseForge, Steam), and shows it to scripts on the first click anywhere: "Changes made" before anyone typed,
     and Save would store that password as the key. Fields in these forms never take a saved password, and one the
     browser filled before anyone went into it is emptied again. Picking a saved key from the field's own list still
     works, because that needs the field to have had focus. */
  const touched = new WeakSet();
  const autofilled = (field) => {
    for (const state of [':autofill', ':-webkit-autofill']) {
      try { return field.matches(state); } catch { /* an older browser knows only the other name */ }
    }
    return false;
  };
  const noSavedPasswords = (el) => el.querySelectorAll('input[type="password"]').forEach((field) => {
    if (field.getAttribute('autocomplete') !== 'new-password') field.setAttribute('autocomplete', 'new-password');
    if (touched.has(field) || field.value === '' || !autofilled(field)) return;
    field.value = '';
    field.dispatchEvent(new Event('input', { bubbles: true }));
  });
  const scan = () => {
    for (const el of forms.keys()) if (!el.isConnected) forms.delete(el);
    document.querySelectorAll(SELECTOR).forEach((el) => { noSavedPasswords(el); entry(el); });
  };
  const dirtyForms = () => [...forms.values()].filter((form) => form.el.isConnected && form.dirty);
  /** Changes nobody is saving yet: only these stop you leaving. */
  const unsavedForms = () => dirtyForms().filter((form) => !form.saving);

  /* The bar --------------------------------------------------------------------------------------- */
  const button = (label, primary, onClick) => {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 's-unsaved-btn' + (primary ? ' is-primary' : '');
    el.textContent = label;
    el.addEventListener('click', onClick);
    return el;
  };
  const showBar = (mode) => {
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 's-unsaved';
      bar.setAttribute('role', 'status');
      bar.setAttribute('aria-live', 'polite');
      document.body.append(bar);
    }
    bar.dataset.mode = mode;
    bar.replaceChildren();
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 8v4"/><path d="M12 16h.01"/>';
    const text = document.createElement('span');
    text.className = 's-unsaved-text';
    text.textContent = words(mode === 'failed' ? 'failed' : 'changes_made');
    const saveAll = button(words('save'), true, () => save());
    saveAll.disabled = dirtyForms().some((form) => form.saving);
    bar.append(icon, text, button(words('discard'), false, () => discard()), saveAll);
    bar.hidden = false;
  };
  const refreshBar = () => {
    const dirty = dirtyForms();
    if (dirty.length) showBar(dirty.some((form) => form.failed) ? 'failed' : 'changes');
    else if (bar) bar.hidden = true;
  };
  const check = () => {
    scan();
    for (const form of forms.values()) if (!form.saving) form.dirty = read(form.el) !== form.base;
    refreshBar();
  };
  let queued = 0;
  const queue = () => (queued ||= setTimeout(() => { queued = 0; check(); }, 60));

  /* Save and discard -------------------------------------------------------------------------------- */
  /** Saves every form with changes; resolves to true when all of them saved without errors. */
  const save = () => Promise.all(unsavedForms().map((form) => new Promise((resolve) => {
    form.saving = true;
    form.failed = false;
    form.done = resolve;
    showBar('changes');
    const method = form.el.dataset.simplifySave;
    const wire = livewire(form.el);
    const submit = form.el.matches('form') ? form.el : form.el.querySelector('form') || form.el.closest('form');
    if (method && wire) wire.call(method);
    else if (submit) submit.requestSubmit();
    else { form.saving = false; resolve(false); }
  }))).then((results) => results.every(Boolean));
  const finished = (form, ok) => {
    form.saving = false;
    if (ok) {
      form.base = read(form.el);
      form.dirty = false;
      form.failed = false;
    } else {
      form.failed = true;
    }
    form.done?.(ok);
    form.done = null;
    // The page's own message says it was saved (a Filament notification); the bar only goes away.
    refreshBar();
  };
  const discard = () => {
    leaving = true;
    forms.clear();
    location.reload();
  };

  /* Leaving ------------------------------------------------------------------------------------------- */
  /** Save, Discard or Cancel before leaving; resolves to 'save', 'discard' or null. */
  const ask = () => new Promise((resolve) => {
    const layer = document.createElement('div');
    layer.className = 's-unsaved-layer';
    const box = document.createElement('section');
    box.className = 's-unsaved-dialog';
    box.setAttribute('role', 'alertdialog');
    box.setAttribute('aria-modal', 'true');
    const title = document.createElement('h2');
    title.id = 's-unsaved-title';
    title.textContent = words('leave_title');
    box.setAttribute('aria-labelledby', title.id);
    const message = document.createElement('p');
    message.textContent = words('leave_message');
    const foot = document.createElement('footer');
    const close = (value) => { layer.remove(); resolve(value); };
    foot.append(button(cancelWord(), false, () => close(null)), button(words('discard'), false, () => close('discard')), button(words('save'), true, () => close('save')));
    box.append(title, message, foot);
    layer.append(box);
    layer.addEventListener('click', (event) => event.target === layer && close(null));
    layer.addEventListener('keydown', (event) => event.key === 'Escape' && (event.stopPropagation(), close(null)));
    document.body.append(layer);
    foot.lastChild.focus();
  });
  /** Asks when there are changes, then runs go(); go() is skipped on Cancel or when saving failed. */
  const guard = async (go) => {
    check();
    if (!unsavedForms().length) return go();
    const choice = await ask();
    if (choice === 'save' && !(await save())) return;
    if (!choice) return;
    leaving = true;
    forms.clear();
    go();
  };
  window.SimplifyUnsaved = { check, save, guard, dirty: () => unsavedForms().length > 0 };

  const leavesPage = (link, event) => {
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download')) return false;
    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return false;
    const url = new URL(link.href, location.href);
    return !(url.origin === location.origin && url.pathname === location.pathname && url.search === location.search);
  };
  // Livewire starts a wire:navigate link on mousedown (and goes on mouseup), before any click: with changes the press
  // stops here, so the click below can ask first.
  document.addEventListener('mousedown', (event) => {
    const link = event.target.closest?.('a[href][wire\\:navigate], a[href][wire\\:navigate\\.hover]');
    if (leaving || !leavesPage(link, event)) return;
    check();
    if (unsavedForms().length) event.stopImmediatePropagation();
  }, true);
  document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    if (leaving || !leavesPage(link, event)) return;
    check();
    if (!unsavedForms().length) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const navigate = link.hasAttribute('wire:navigate') || link.hasAttribute('wire:navigate.hover');
    guard(() => (navigate && window.Livewire?.navigate ? window.Livewire.navigate(link.href) : (location.href = link.href)));
  }, true);
  // A navigation Livewire starts itself (back and forward, a script): stopped and asked, then taken again.
  document.addEventListener('livewire:navigate', (event) => {
    if (leaving || !unsavedForms().length) return;
    const url = event.detail?.url?.toString?.() || event.detail?.url;
    if (!url || typeof event.preventDefault !== 'function') return;
    event.preventDefault();
    guard(() => window.Livewire.navigate(url));
  });
  addEventListener('beforeunload', (event) => {
    if (leaving) return;
    check();
    if (!unsavedForms().length) return;
    event.preventDefault();
    event.returnValue = '';
  });

  /* Watching ------------------------------------------------------------------------------------------ */
  for (const type of ['input', 'change', 'click', 'keyup']) document.addEventListener(type, (event) => event.target.closest?.(SELECTOR) && queue(), true);
  document.addEventListener('focusin', (event) => event.target.matches?.('input[type="password"]') && touched.add(event.target), true);
  const hook = () => {
    if (!window.Livewire?.hook || hook.done) return;
    hook.done = true;
    window.Livewire.hook('commit', ({ component, commit, succeed, fail }) => {
      const mine = [...forms.values()].filter((form) => form.el.isConnected && livewire(form.el)?.id === component.id);
      if (!mine.length) return;
      // A save is the method itself, or a Filament action of that name (the Save button in the page's header).
      const calls = (commit?.calls || []).flatMap((call) => (/^(mountAction|callMountedAction)$/.test(call.method) && typeof call.params?.[0] === 'string' ? [call.method, call.params[0]] : [call.method]));
      // Marked at once, before the answer: a save that sends you on (the end of the setup) never asks to leave.
      const saving = mine.filter((form) => form.saving || calls.includes(form.el.dataset.simplifySave || 'save'));
      saving.forEach((form) => (form.saving = true));
      succeed(() => setTimeout(() => {
        for (const form of mine) {
          if (saving.includes(form)) {
            const errors = component.snapshot?.memo?.errors || {};
            finished(form, !Object.keys(errors).length);
          } else if (!form.dirty) {
            // The server may tidy a value (a trimmed name): what it sends back is the new starting point.
            form.base = read(form.el);
          }
        }
        queue();
      }));
      fail(() => mine.forEach((form) => form.saving && finished(form, false)));
    });
  };
  document.addEventListener('livewire:init', hook);
  const start = () => { hook(); leaving = false; forms.clear(); setTimeout(check, 0); };
  document.addEventListener('livewire:navigated', start);
  document.addEventListener('livewire:initialized', () => { hook(); setTimeout(check, 0); });
  if (document.readyState === 'loading') addEventListener('DOMContentLoaded', start);
  else start();
})();
