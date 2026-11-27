# Simplify

A [Pelican](https://pelican.dev) plugin that gives you one clear overview of your servers, with announcements,
subdomains and your servers on your own website. Add-ons such as Server Maker are separate plugins that show
up inside Simplify when both are installed.

Simplify is a rebuild from the ground up. The earlier PelicanSimplify lives in
[PelicanSimplify](https://github.com/jerajerajerajera/PelicanSimplify).

## What it does

- An overview of all your servers as cards, compact rows or a list, with live numbers, players, filters, a section
  per node and your own groups. Start, stop and restart from each card. A server without a picture of its own shows an
  icon for its kind of server.
- A server page that fits the window: a header card with status, address and power, number cards, a short menu and
  the live console. While a server installs, a card shows the steps and how long it usually takes; after that, First
  start asks for the Minecraft EULA when it is still open. The menu changes only the page next to it. A suspended
  server tells its owner that the hosting company paused it.
- Create server opens in a Simplify popup from every entry point, with Server Maker when it is installed.
- The Admin page for every admin: the admin pages (or the servers per node) in a menu on the left, Pelican's page next
  to it, node health at the bottom. On a phone the menu is a sheet from the bottom bar. The legacy panel in the account
  menu shows Pelican's own pages.
- Announcements at the top of every page, in the bell, as app notifications and in game chats. Pin one to everyone's
  notifications.
- Your servers on your own website, with a script, an iframe or a status page: uptime of the last 30 days, the order
  you drag, a logo per egg, and a status page on an address of its own.
- A subdomain for every server under your own domain, made in Cloudflare and removed with the server.
- Your own name, logo, colours and app icon. Install the panel as an app with notifications per device; every
  notification option sits in the bell's window.
- On a phone one frame with a bar at the bottom and menus that open as a sheet from the bottom. Pull down on the
  overview to reload.
- Nothing saves by itself: a changed page says Changes made, with Discard and Save.
- A setup that checks the panel, asks who it is for and sets up the installed add-ons in one go.
- Add-ons open inside Simplify, also from the account menu.
- 32 languages.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/screenshots/overview-egg-icons-dark-desktop.png">
  <img src="docs/screenshots/overview-egg-icons-light-desktop.png" alt="The Simplify overview" width="720">
</picture>
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/screenshots/phone-overview-dark-phone.png">
  <img src="docs/screenshots/phone-overview-light-phone.png" alt="The Simplify overview on a phone" width="240">
</picture>

## Install

Install Simplify from the [Pelican Hub](https://hub.pelican.dev). Updates come from the Hub only.

## Status page on its own address

The status page always works at `https://<panel>/status`. Settings › Website › Status page can also give it an address
of its own, such as `status.example.com`; on that address the panel shows only the server list at `/` (and
`/simplify/embed?page=1` goes there), and every other path answers 404.

**With Cloudflare connected under Subdomains, Simplify sets it all up.** Type the address and press Save. Simplify:

- makes a proxied CNAME from the address to the panel's address, so Cloudflare gives it a certificate;
- checks the address the way a visitor reaches it (public DNS, no redirects followed) and says Live, or what is wrong
  in plain words: not in DNS yet, sent somewhere else (often a catch-all redirect rule), the web server shows another
  page, the certificate does not fit, Cloudflare cannot reach the server, or a token permission is missing;
- when Cloudflare cannot check the server's certificate for the address (error 525 or 526), adds one Configuration Rule
  with SSL "Full" for that address only, and checks again;
- on a new or empty address, removes the record and the rule it made, never anything else.

The token needs **Zone › DNS › Edit** (also used for server addresses) and, for the SSL rule, **Zone › Config Rules ›
Edit**. Cloudflare rewrites the Host header only on Enterprise plans, so the panel's web server must still answer for
the address. On a usual Pelican install (Pelican's nginx file as the only site) it already does; when it does not,
the check says so and the steps below apply.

**Without Cloudflare**, Settings shows these steps under Set it up by hand, with your own addresses filled in:

1. **DNS.** Add a CNAME from the address to the panel's address, such as `status.example.com CNAME panel.example.com`.
   Cloudflare's proxy may stay on.
2. **Web server.** Add the address to `server_name` in the panel's nginx file (usually
   `/etc/nginx/sites-available/pelican.conf`, in every `server` block), then `sudo nginx -t && sudo systemctl reload nginx`.
   On Apache, add `ServerAlias status.example.com` and reload Apache.
3. **Certificate.** With Cloudflare's proxy and SSL "Full (strict)", the origin certificate must cover the address (a
   Cloudflare origin certificate for `*.example.com` does). Without the proxy:
   `sudo certbot --nginx -d panel.example.com -d status.example.com`.

Press Save again to check: Live shows once the page answers there. Still see another page in your browser? It may
remember an old redirect; try a private window.

### The page's own look

Settings › Website › Status page also sets how the page of its own looks: a title, a logo (the panel's logo from
Settings › Branding, one uploaded for the page, or none), a short intro in plain text, a theme and an accent colour.
These change only the page at `/status`, on its own address and at `/simplify/embed?page=1`; the list on other
websites keeps following Look.

**Own CSS (advanced)** goes into that page only, at most 10 KB. The page runs on the panel's own address, so no HTML or
scripts are allowed, and Save cleans the CSS: `@import`, `url()` other than a small `data:` image (PNG, JPEG, GIF or
WebP), `image-set()` and other ways to load a file, `expression()`, `javascript:`, `behavior` and `-moz-binding`,
backslash escapes and `<` are removed or made invalid, and Settings says what went. The page also sends a strict
Content-Security-Policy, so nothing loads from elsewhere or runs except the list's own script. Style the list with
`#simplify-servers::part(card)`, `::part(name)`, `::part(state)` (also `state-online`, `state-offline`, …),
`::part(join)`, `::part(uptime)`, `::part(icon)` and `::part(list)`, and the page with `body`, `.page-head`,
`.page-logo`, `h1` and `.page-intro`.

### Pinned servers, incidents and alerts

The pin next to a server in Settings › Website keeps it at the top of the list. On the status page, a pinned server that
is unreachable longer than the set minutes gets a banner above the list (`::part(banner)`) and a red card
(`::part(card-down)`).

An incident opens by itself when a server on the website is unreachable for the set minutes, from the same check that
records the uptime every minute, and closes when the server is back. A minute without an answer from Wings counts as
unknown, not as down, and a server that is down again within 5 minutes of coming back reopens the same incident. A
Stop, Restart or Kill from the panel, a schedule's power task, or a server that installs or is suspended counts as
Planned and sends no alert. The status page lists the incidents of the last 14 days (`::part(incidents)`); Settings ›
Website lets you add a short note for visitors (plain text) or mark an incident as Planned. A list on another website
shows the banner and the log only with `data-incidents="on"`.

Alerts for incidents that are not planned:

- **Discord**: paste a channel's webhook (Edit Channel › Integrations › Webhooks). It is stored encrypted and never
  shown again, and only Discord's own webhook addresses are accepted. Each incident is one message that turns green
  when the server is back; nobody is mentioned, whatever a server's name holds.
- **RSS**: `/status/feed.xml` on the panel, or `/feed.xml` on the status page's own address.
- **Bell**: root admins get each incident in Pelican's bell, and on their devices when Updates is on under
  Notifications.

## Add-ons

See [ADDONS.md](ADDONS.md) for how an add-on plugs into Simplify.

## What it sends where

Simplify talks to your own panel and your Wings nodes, and to nothing else, except for the following:

- **App notifications.** When someone turns them on for a device, the panel sends their notifications to the push service of that browser: Google (`fcm.googleapis.com`), Mozilla (`push.services.mozilla.com`), Apple (`push.apple.com`) or Microsoft (`notify.windows.com`). The content is encrypted for that device only. Root admins can turn app notifications off for the whole panel in Simplify's settings.
- **Subdomains** (when a root admin sets them up): Cloudflare's API (`api.cloudflare.com`), with the token the admin pasted, to make and remove the DNS records of each server's subdomain. A sync runs every five minutes through Pelican's scheduler (`simplify:subdomains`).
- Update notices for root admins use Pelican's own version check and Pelican's cached node information; Simplify makes no extra calls for them. They run hourly through Pelican's scheduler (`simplify:update-notices`).

Open routes (no login): `/simplify/assets/{name}` serves Simplify's stylesheet and logo, and `/simplify/brand/{file}` serves the panel logo and app icon an admin uploaded in Simplify's settings. `/simplify/banners/{file}` serves server banners, only to people who are signed in. `/simplify/manifest.webmanifest`, `/simplify/app-icon-192.png`, `/simplify/app-icon-512.png` and `/simplify-worker.js` let a browser install the panel as an app; the worker caches only a small offline page. `/status`, `/simplify/embed`, `/simplify/embed.js` and `/simplify/embed/servers.json` show the servers an owner or root admin put on a website: name, game, status, players, the public join address, and what the owner fills in on the server's Website page (description, image, client download, a second public address and the position on the list), nothing else. `/simplify/embed/banners/{file}` serves an uploaded image only while its server is on the website. `/simplify/embed/logos/{file}` serves a logo uploaded for an egg in Settings › Website. On the status page's own address (if one is set) only the list and these files answer.

Announcements are delivered every minute through Pelican's scheduler (`simplify:announcements`), to the bell, as app notifications and in game chats.

## Development

- `bash tests/run.sh` runs the checks that need no panel, including `tests/hub-check.py`.
- `pint` formats the code (Laravel preset).
- `python3 scripts/build-package.py` builds the zip for the Hub.

## License

Source available, see [LICENSE.md](LICENSE.md). Copyright (c) 2026 Jeroen (jerajerajerajera).
