// Wings stand-in for the Simplify lab: answers the daemon calls the panel makes (system info, server list and
// state, power, files) from memory, so the panel behaves as if a node is online.
// node fake-wings.js --port 8090 --servers servers.json --log wings.log
const http = require('http'), fs = require('fs'), url = require('url');
const arg = (name, fallback) => { const i = process.argv.indexOf('--' + name); return i > 0 ? process.argv[i + 1] : fallback; };
const port = Number(arg('port', 8090)), logFile = arg('log', 'wings.log'), serversFile = arg('servers', 'servers.json');
const servers = () => { try { return JSON.parse(fs.readFileSync(serversFile, 'utf8')); } catch { return []; } };
const disks = {};
const disk = (s) => disks[s.uuid] ??= Object.fromEntries(Object.entries(s.files || {}).map(([k, v]) => [k, Buffer.from(v, k.endsWith('.png') ? 'base64' : 'utf8')]));
const norm = (p) => '/' + String(p || '').split('/').filter((x) => x && x !== '.').join('/');
const entry = (s) => ({ state: s.state || 'running', is_suspended: false,
  utilization: { memory_bytes: 900e6, memory_limit_bytes: 4096e6, cpu_absolute: 12, network: { rx_bytes: 1e6, tx_bytes: 2e6 }, uptime: 3600, state: s.state || 'running', disk_bytes: 900e6 },
  configuration: { uuid: s.uuid, meta: { name: s.name } } });
const body = (req) => new Promise((ok) => { const parts = []; req.on('data', (c) => parts.push(c)); req.on('end', () => ok(Buffer.concat(parts))); });
http.createServer(async (req, res) => {
  const u = url.parse(req.url, true), path = u.pathname;
  fs.appendFileSync(logFile, new Date().toISOString() + ' ' + req.method + ' ' + path + (u.search || '') + '\n');
  const send = (code, data, type = 'application/json') => { res.writeHead(code, { 'Content-Type': type }); res.end(Buffer.isBuffer(data) || typeof data === 'string' ? data : JSON.stringify(data)); };
  const list = servers();
  if (path === '/api/system') return send(200, { architecture: 'amd64', cpu_count: 4, kernel_version: '6.8', os: 'linux', version: '1.0.0-lab' });
  if (path === '/api/system/utilization') return send(200, { memory_total: 16e9, memory_used: 6e9, swap_total: 0, swap_used: 0, load_average1: 0.5, load_average5: 0.4, load_average15: 0.3, cpu_percent: 23.5, disk_total: 500e9, disk_used: 120e9 });
  if (path === '/api/system/ips') return send(200, { ip_addresses: ['127.0.0.1'] });
  if (path === '/api/servers') return send(200, list.map(entry));
  const m = path.match(/^\/api\/servers\/([^/]+)(\/.*)?$/);
  if (!m) return send(404, { error: 'unknown path ' + path });
  const s = list.find((x) => x.uuid === m[1]); if (!s) return send(404, { error: 'not found' });
  const files = disk(s), rest = m[2] || '', input = await body(req);
  const json = () => { try { return JSON.parse(input.toString() || '{}'); } catch { return {}; } };
  if (rest === '') return send(200, entry(s));
  if (rest === '/files/contents') { const f = files[norm(u.query.file)]; return f ? send(200, f, 'text/plain') : send(404, { error: 'The requested resource does not exist on this instance.' }); }
  if (rest === '/files/write') { files[norm(u.query.file)] = input; return send(204, ''); }
  if (rest === '/files/list-directory') {
    const dir = norm(u.query.directory), seen = {};
    for (const [name, content] of Object.entries(files)) {
      if (!name.startsWith(dir === '/' ? '/' : dir + '/')) continue;
      const tail = name.slice(dir === '/' ? 1 : dir.length + 1), first = tail.split('/')[0];
      seen[first] ??= tail.includes('/') ? { name: first, mode: 'drwxr-xr-x', mode_bits: '755', size: 4096, file: false, symlink: false, mime: 'inode/directory', created: new Date().toISOString(), modified: new Date().toISOString() }
        : { name: first, mode: '-rw-r--r--', mode_bits: '644', size: content.length, file: true, symlink: false, mime: 'application/java-archive', created: new Date().toISOString(), modified: (s.modified || {})[name] || new Date(Date.now() - 864e5).toISOString() };
    }
    return Object.keys(seen).length || dir === '/' ? send(200, Object.values(seen)) : send(404, { error: 'The requested resource does not exist on this instance.' });
  }
  if (rest === '/files/create-directory') { const b = json(); files[norm(b.path + '/' + b.name) + '/.keep'] = Buffer.from(''); return send(204, ''); }
  if (rest === '/files/rename') { const b = json(); for (const f of b.files || []) { const from = norm(b.root + '/' + f.from), to = norm(b.root + '/' + f.to); if (!files[from]) return send(404, { error: 'missing ' + from }); files[to] = files[from]; delete files[from]; } return send(204, ''); }
  if (rest === '/files/delete') { const b = json(); for (const f of b.files || []) delete files[norm(b.root + '/' + f)]; return send(204, ''); }
  if (rest === '/files/pull') { const b = json(); files[norm(b.root + '/' + b.file_name)] = Buffer.from('jar from ' + b.url); fs.appendFileSync(logFile, '  pull ' + b.url + '\n'); return send(204, ''); }
  if (rest === '/power') return send(204, '');
  if (rest === '/logs') return send(200, { data: ['[lab] hello'] });
  return send(200, {});
}).listen(port, '127.0.0.1', () => console.log('fake wings on ' + port));
