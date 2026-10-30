// Answers the Minecraft status ping for every running Minecraft server of the demo (servers.json from seed.php), so the
// panel shows players, version and MOTD as it does with a real server. Only the status and ping packets.
// node fake-minecraft.cjs --servers servers.json
const fs = require('fs');
const net = require('net');

const arg = (name) => process.argv[process.argv.indexOf('--' + name) + 1];
const servers = JSON.parse(fs.readFileSync(arg('servers'), 'utf8'));

const varint = (n) => { const b = []; do { let x = n & 0x7f; n >>>= 7; if (n) x |= 0x80; b.push(x); } while (n); return Buffer.from(b); };
const packet = (id, body) => { const data = Buffer.concat([varint(id), body]); return Buffer.concat([varint(data.length), data]); };
const readVarint = (buf, at) => { let n = 0, shift = 0, i = at; for (;;) { if (i >= buf.length) return null; const b = buf[i++]; n |= (b & 0x7f) << shift; if (!(b & 0x80)) return [n, i]; shift += 7; if (shift > 35) throw new Error('varint'); } };

for (const [i, s] of servers.entries()) {
  const props = s.files?.['/server.properties'];
  if (!props || s.state !== 'running' || !s.port) continue;
  const motd = (props.match(/^motd=(.*)$/m)?.[1] || 'A Minecraft Server').replace(/\\u([0-9a-f]{4})/gi, (m, h) => String.fromCharCode(parseInt(h, 16)));
  const forge = !!s.files['/mods/create-1.21.1-6.0.4.jar'];
  const names = ['Mira', 'Tobias', 'Sven', 'Noor', 'Lucas', 'Fenna', 'Daan', 'Isa', 'Bram'];
  const online = [7, 3, 12, 2][i % 4];
  const status = JSON.stringify({
    version: { name: forge ? '1.21.1' : 'Paper 1.21.1', protocol: 767 },
    players: { max: 20, online, sample: names.slice(0, Math.min(online, 5)).map((name, n) => ({ name, id: `00000000-0000-4000-8000-00000000000${n}` })) },
    description: { text: motd },
  });
  net.createServer((socket) => {
    let buf = Buffer.alloc(0);
    socket.on('error', () => {});
    socket.on('data', (chunk) => {
      buf = Buffer.concat([buf, chunk]);
      for (;;) {
        const len = readVarint(buf, 0);
        if (!len || buf.length < len[1] + len[0]) return;
        const body = buf.subarray(len[1], len[1] + len[0]);
        buf = buf.subarray(len[1] + len[0]);
        const [id, at] = readVarint(body, 0);
        if (id === 0 && body.length === at) { const json = Buffer.from(status); socket.write(packet(0, Buffer.concat([varint(json.length), json]))); }
        else if (id === 1) { socket.end(packet(1, body.subarray(at))); }
      }
    });
  }).listen(s.port, '127.0.0.1', () => console.log('minecraft', s.name, 'on', s.port));
}
