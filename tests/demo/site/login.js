// The sign-in page of the demo: Pelican's own form, with one box underneath that opens the admin or the customer
// recording. Nothing is checked; there is no account behind it.
(() => {
  const form = document.querySelector('form');
  if (!form) return;
  const box = document.createElement('div');
  box.className = 'demo-login';
  box.innerHTML = '<div><b>Simplify demo</b> <span>· nothing you change is saved</span></div><div class="demo-login-row">'
    + '<a href="admin/index.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/></svg>Log in as admin</a>'
    + '<a href="customer/index.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>Log in as customer</a></div>';
  const css = document.createElement('style');
  css.textContent = '.demo-login{margin-top:20px;border:1px solid rgb(0 0 0/.1);border-radius:10px;padding:12px 14px;display:grid;gap:10px;font-size:13px}'
    + '.dark .demo-login{border-color:rgb(255 255 255/.12)}.demo-login b{font-weight:600}.demo-login span{opacity:.65}'
    + '.demo-login-row{display:flex;gap:8px;flex-wrap:wrap}.demo-login a{display:inline-flex;align-items:center;gap:6px;border:1px solid rgb(0 0 0/.15);border-radius:8px;padding:6px 10px;font-weight:500;color:inherit;text-decoration:none}'
    + '.dark .demo-login a{border-color:rgb(255 255 255/.18)}.demo-login a:hover{background:rgb(0 0 0/.04)}.dark .demo-login a:hover{background:rgb(255 255 255/.06)}.demo-login svg{width:16px;height:16px}';
  document.head.appendChild(css);
  form.after(box);
  form.addEventListener('submit', (e) => { e.preventDefault(); e.stopImmediatePropagation(); location.href = 'admin/index.html'; }, true);
})();
