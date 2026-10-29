// Wings stand-in for the screen audit (copy of tests/demo/fake-wings.cjs, plus the console websocket): answers the daemon calls the panel makes (system info, server list and
// state, power, files) from memory, so the panel behaves as if a node is online.
// node fake-wings.cjs --port 8090 --servers servers.json --log wings.log
const http = require('http'), fs = require('fs'), url = require('url');
const arg = (name, fallback) => { const i = process.argv.indexOf('--' + name); return i > 0 ? process.argv[i + 1] : fallback; };
const port = Number(arg('port', 8090)), logFile = arg('log', 'wings.log'), serversFile = arg('servers', 'servers.json');
const servers = () => { try { return JSON.parse(fs.readFileSync(serversFile, 'utf8')); } catch { return []; } };
const disks = {};
const disk = (s) => disks[s.uuid] ??= Object.fromEntries(Object.entries(s.files || {}).map(([k, v]) => [k, Buffer.from(v, k.endsWith('.png') ? 'base64' : 'utf8')]));
const norm = (p) => '/' + String(p || '').split('/').filter((x) => x && x !== '.').join('/');
const entry = (s) => ({ state: s.state || 'running', is_suspended: false,
  utilization: { memory_bytes: (s.memory || 900) * 1e6, memory_limit_bytes: 4096e6, cpu_absolute: s.cpu ?? 12, network: { rx_bytes: 1e6, tx_bytes: 2e6 }, uptime: 3600, state: s.state || 'running', disk_bytes: 900e6 },
  configuration: { uuid: s.uuid, meta: { name: s.name } } });
const body = (req) => new Promise((ok) => { const parts = []; req.on('data', (c) => parts.push(c)); req.on('end', () => ok(Buffer.concat(parts))); });

// Console websocket (Pelican's console page connects to Wings directly): auth, status, stats, a log with short and
// very long lines, a new line every second, and an echo of every command.
const crypto = require('crypto');
const frame = (text) => { const b = Buffer.from(text), n = b.length; const head = n < 126 ? Buffer.from([0x81, n]) : n < 65536 ? Buffer.from([0x81, 126, n >> 8, n & 255]) : Buffer.concat([Buffer.from([0x81, 127, 0, 0, 0, 0]), Buffer.from([(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255])]); return Buffer.concat([head, b]); };
const unframe = (buf) => { if (buf.length < 2) return null; const op = buf[0] & 15; let n = buf[1] & 127, i = 2; if (n === 126) { n = buf.readUInt16BE(2); i = 4; } else if (n === 127) { n = Number(buf.readBigUInt64BE(2)); i = 10; } const masked = buf[1] & 128, mask = masked ? buf.slice(i, i + 4) : null; if (masked) i += 4; if (buf.length < i + n) return null; const data = Buffer.from(buf.slice(i, i + n)); if (mask) for (let k = 0; k < n; k++) data[k] ^= mask[k & 3]; return { op, text: data.toString(), rest: buf.slice(i + n) }; };
const logLines = (name) => ['[12:00:01 INFO]: Starting minecraft server version 1.21.4', '[12:00:01 INFO]: Loading properties', '[12:00:02 INFO]: [Chunky] Enabling Chunky v1.4.10',
  '[12:00:02 WARN]: [LuckPerms] A very long log line that goes on and on to see whether the console wraps or cuts it: ' + 'abcdefghij'.repeat(30),
  '[12:00:03 INFO]: Preparing level "world"', '[12:00:04 INFO]: Preparing spawn area: 0%', '[12:00:04 INFO]: Preparing spawn area: 48%', '[12:00:05 INFO]: Preparing spawn area: 96%',
  '[12:00:05 INFO]: Done (4.2s)! For help, type "help"', '[12:00:06 INFO]: Timings Reset', '[12:00:09 INFO]: Steve joined the game', '[12:00:12 INFO]: <Steve> hello ' + name,
  ...Array.from({ length: 40 }, (_, i) => `[12:0${Math.floor(i / 10)}:${String(i % 10 * 6).padStart(2, '0')} INFO]: tick ${i} \u001b[32mgreen\u001b[0m \u001b[33myellow\u001b[0m ${'x'.repeat(i * 7 % 200)}`)];
const stats = (s) => JSON.stringify({ memory_bytes: (s.memory || 900) * 1e6, memory_limit_bytes: 4096e6, cpu_absolute: s.cpu ?? 12, network: { rx_bytes: 31.1e6, tx_bytes: 1.0e9 }, uptime: 3600e3, state: s.state || 'running', disk_bytes: 900e6 });
const wsServer = (server) => server.on('upgrade', (req, socket) => {
  const m = (url.parse(req.url).pathname || '').match(/^\/api\/servers\/([^/]+)\/ws$/);
  const s = m && servers().find((x) => x.uuid === m[1]);
  if (!s) { socket.end('HTTP/1.1 404 Not Found\r\n\r\n'); return; }
  const key = crypto.createHash('sha1').update(req.headers['sec-websocket-key'] + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest('base64');
  socket.write('HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: ' + key + '\r\n\r\n');
  const send = (event, ...args) => { try { socket.write(frame(JSON.stringify({ event, args }))); } catch {} };
  let buf = Buffer.alloc(0), n = 0;
  const timer = setInterval(() => { send('stats', stats(s)); send('console output', `[12:01:${String(n++ % 60).padStart(2, '0')} INFO]: heartbeat ${n} ${'.'.repeat(n % 90)}`); }, 1000);
  socket.on('data', (chunk) => {
    buf = Buffer.concat([buf, chunk]);
    for (let f = unframe(buf); f; f = unframe(buf)) {
      buf = f.rest;
      if (f.op === 8) { socket.end(); return; }
      if (f.op !== 1) continue;
      let msg; try { msg = JSON.parse(f.text); } catch { continue; }
      fs.appendFileSync(logFile, new Date().toISOString() + ' WS ' + s.name + ' ' + msg.event + '\n');
      if (msg.event === 'auth') { send('auth success'); send('status', s.state || 'running'); send('stats', stats(s)); }
      if (msg.event === 'send logs') for (const line of logLines(s.name)) send('console output', line);
      if (msg.event === 'send stats') send('stats', stats(s));
      if (msg.event === 'send command') send('console output', '> ' + (msg.args || [])[0]);
      if (msg.event === 'set state') { send('status', msg.args[0] === 'stop' || msg.args[0] === 'kill' ? 'offline' : 'running'); }
    }
  });
  socket.on('close', () => clearInterval(timer)); socket.on('error', () => clearInterval(timer));
});

wsServer(http.createServer(async (req, res) => {
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
})).listen(port, '127.0.0.1', () => console.log('fake wings on ' + port));
