// Shared by the recorder (record.cjs) and the browser (runtime.js): the keys a Livewire call is filed under, from
// strict (same component state, same call, same typed values) to loose (same call).
(function (root) {
  const h = (s) => { let a = 5381, b = 52711; for (let i = 0; i < s.length; i++) { const c = s.charCodeAt(i); a = (a * 33) ^ c; b = (b * 33) ^ c; } return ((a >>> 0).toString(36) + (b >>> 0).toString(36)); };
  const stable = (v) => JSON.stringify(v, (k, x) => (x && typeof x === 'object' && !Array.isArray(x) ? Object.keys(x).sort().reduce((o, key) => ((o[key] = x[key]), o), {}) : x));
  // One component of a /livewire/update request -> [name, keys...]
  function keys(component) {
    const snap = typeof component.snapshot === 'string' ? JSON.parse(component.snapshot) : component.snapshot;
    const name = snap.memo.name;
    const calls = (component.calls || []).map((c) => [c.path || '', c.method, c.params || []]);
    const updates = component.updates || {};
    return [name, [
      'a' + h(stable([snap.data, calls, updates])),
      'b' + h(stable([calls, Object.keys(updates).sort()])),
      'c' + h(stable(calls)),
    ], snap];
  }
  // Where a panel URL lives in the static site: pages become <path>.html (a query gets its own file), files keep their path.
  const isPage = (path) => !/^\/livewire(-[0-9a-f]+)?\//.test(path) && !/\.[a-z0-9]{1,8}$/i.test(path);
  function file(pathAndQuery) {
    const [p, q] = pathAndQuery.split('?');
    const path = '/' + p.split('/').filter(Boolean).join('/');
    if (!isPage(path)) return path.slice(1);
    return (path === '/' ? 'index' : path.slice(1)) + (q ? '~' + h(q) : '') + '.html';
  }
  root.DemoKey = { keys, h, stable, file, isPage };
  if (typeof module !== 'undefined') module.exports = root.DemoKey;
})(typeof window !== 'undefined' ? window : globalThis);
