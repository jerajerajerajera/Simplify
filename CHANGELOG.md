# Changelog

## 1.4.3

- The notifications menu always ends inside the window, so its last item and "Show more" are no longer cut off when the top bar has a second row (announcements on a narrower window) or the panel is zoomed.
- Server page: Stop, Restart and Start work again. Since 1.3.1 they stayed greyed out on every server, because a value the buttons check was no longer set.
- Server page: the console title shows "Connected" again on Pelican versions that do not report the server's state to plugins themselves (before 1.0.0-rc.2), instead of "Connecting to Wings..." while the log already runs. The server card's status follows Wings there too.
- Server page: while a server is starting or stopping, the main power button is Force stop, and Restart works in every state but installing and restoring, as in Pelican's own console.
- Appearance → Panel name and logo has the switch "Show “× Pelican” after the name" again, as in PelicanSimplify 0.7.0. It is on by default; a 0.7.0 panel that had it off keeps it off.
- Host mode: Roles and Pelican's own settings tabs (General, Captcha, Mail, Backup, OAuth, Misc) have their icons again; Announcements, Automatic subdomains and Website get their own icons.
- Phone: the account menu's buttons get a row of their own and wrap, instead of the last ones being cut off.
- Server page: the Network usage card shows the traffic used without the cut-off "· since server start"; the whole line is its tooltip.
- Scrollbars everywhere in the panel (menus, content boxes, dropdowns, the console, host mode) are one dark rounded bar without a track or outline, with room above and below, instead of the browser's own bar. Firefox gets the thin bar in the same colour.

## 1.4.2

- Console: the output fits its box again. Lines no longer run past the right edge or end half hidden behind the command bar, and the whole console card, the command bar included, has one background colour.
- Appearance → Colours has the switch "Individual colours per server" again, as in PelicanSimplify 0.7.0. It is on by default; off, every server uses the panel's accent colour on the overview and its own page. A 0.7.0 panel that had it off keeps it off.
- Server cards show the icon uploaded for the server in Pelican, and without one the egg's icon large, as in PelicanSimplify 0.7.0. Before, a server without an icon of its own showed a plain cube with the egg's icon only as the small badge.

## 1.4.1

- Installing Simplify from Admin → Plugins or the Hub now also removes the old pelican-simplify folder. Those installs run in the panel's queue, where 1.4.0 only removed it once the queue worker restarted, so for a while both copies were loaded.

## 1.4.0

- The plugin id is now `simplify`, with its own listing on the Pelican Hub. Installing it on a panel with the old id `pelican-simplify` (Simplify 1.x, or PelicanSimplify 0.7.0 and before) removes the old plugin's folder once the install is done. Its tables and settings stay, so Simplify 1.x panels keep everything.
- From PelicanSimplify 0.6 and 0.7 Simplify now also takes over the announcements (with their in-game, bell and app notification choices, without sending them again), the panel's accent colour, name and logo, and the website list: its look and which servers are on it. Each part is only filled while it is still empty in Simplify. The old files stay where they are.

## 1.3.11

- On every server page, Pelican's own pages too, the page is one card at the window's height, rounded at the top and bottom like the menu next to it. It keeps that height with or without content, and only what is inside it scrolls; its title and buttons stay at the top.
- Pelican's buttons on a list (the bell on Backups, the filter on Webhooks) sit in the card's head, in the row of the search field, and the table lines up with the rest of the card.
- Minecraft, Plugins, Startup, Settings and Schedules have no separate boxes any more: their parts sit in that one card, split by lines, with Plugins' two columns side by side in it.
- Host mode in a small window: the page itself never scrolls. A server in the frame fits the frame's height too (the console gets smaller), and the panel's announcements sit in the title bar as short rows instead of large bars above it.
- Website: the preview of the saved list is as tall as the list instead of a fixed, mostly empty frame.

## 1.3.10

- A server's pages fit the window: the menu and the page next to it end at the bottom of the window, and only the page's own content scrolls, never the page itself. The console fills the space down to the footer.
- Nothing under the console any more: the server info card from add-ons is gone, because the same information is under Minecraft and Plugins in the menu.
- On a server page the announcements sit in the empty space of the top bar, between All servers and the icons. On narrower windows and phones they get a row of their own under it. The server card shows the MOTD again.
- Admin → Announcements shows each announcement's title and text across the full width on the top row, with its details (state, dates, audience, bell, push, in-game) on the row below.

## 1.3.9

- A server's software and version show once on its page. Paper reports "Paper 1.21.1" itself, so the server card read "Paper  Paper 1.21.1  Paper 1.21.1".

## 1.3.8

- Simplify icon buttons on every page of the panel, also in the normal admin panel (Announcements, Website embed, Egg Updates and Pelican's own pages): Tabler icons at one size in a quiet button, instead of Pelican's large coloured glyphs. Icons in table rows stay plain until hovered, and buttons with a label get the same icon size.

## 1.3.7

- The server menu next to the console lists all sections again. Since 1.3.5 its first item filled the whole menu, so only Console showed.

## 1.3.6

- On a server's pages (Website, Minecraft, Plugins and Pelican's own pages) the page title is gone: the menu on the left already says where you are. An icon action such as Save sits in the top corner of the page's card instead of outside it, and the page starts level with the menu.
- Pelican's icon actions on a server's pages and in host mode are Simplify icon buttons again, as in PelicanSimplify 0.7.0: the same Tabler icons, at the same size, in a quiet button instead of a large coloured glyph.

## 1.3.5

- On a server page the announcements sit in the server card, between the name and the power buttons, as short rows instead of bars above the console. With more than two, a +N button shows the rest.
- The console and the server menu on the left fit the height of the window, so the page no longer scrolls.

## 1.3.4

- In host mode the settings sit straight on the page again, as in PelicanSimplify 0.7.0. Simplify's settings, the add-ons' pages and Pelican's settings tabs no longer have a card around them or around each section, and the extra margin and the page header are gone. A thin line separates the sections.

## 1.3.3

- Opening the panel shows the Simplify overview again, also right after signing in, as in PelicanSimplify 0.7.0. Turn it off under Settings → General → Start page.
- In host mode, Simplify Hosting sits under Add-ons in the menu on the left, with the other add-ons, instead of under Other plugins.

## 1.3.2

- Installing Simplify again after an install that stopped halfway no longer fails with "table simplify_core_announcements already exists": Simplify keeps the tables that are already there.

## 1.3.1

Smaller inside, the same outside: a cleanup of the code after the three features moved into the core.

- The host health dock says "hosts reporting" in the panel's language instead of English.
- On the Website embed pages, Enter now saves the form, like on the subdomain pages.
- The "Card title" choice (alias or name) is gone from Customize overview: Simplify never had aliases, so it did nothing.
- Inside: one page base for the settings-like pages, one Minecraft check, no leftover code from PelicanSimplify 0.7.0, and texts that nothing showed any more are gone from all 32 languages.

## 1.3.0

The setup has a new first step that recommends the add-ons, and it now looks like the rest of Simplify.

- **Add-ons first**: a card for each add-on (Server Maker, Toolkit, Explain, Egg Updates and Hosting) shows whether it is installed. If it isn't, the card has a button to its page on the Pelican Hub. Install the ones you want, press **Check again**, and set everything up in one go.
- **Same style as the settings**: the Simplify logo in the heading, one card with the steps on the left, and the same flat sections.
- **Set up add-ons**: the last step asks the installed add-ons their most important questions and links to each add-on's own settings page.
- For add-on makers: the add-on API is now version 3, with the optional `setupFields()`, `setupState()` and `saveSetup()` (see ADDONS.md).
- Announcements, embed and subdomains are core features: always there, like the overview. The switches for them under Settings → Add-ons are gone, and they no longer show in the add-on list. Use the ones you want.
- Simplify no longer copies data from the separate Announcements, Embed and Subdomains plugins of Simplify 1.1; remove them before updating. Subdomains from PelicanSimplify 0.6 and 0.7 are still taken over.

## 1.2.0

Announcements, Embed and Subdomains are now part of Simplify, so you no longer need separate plugins for them.

- **Announcements**: info, warning and success bars at the top of every page, with a pin button, in the bell, as app notification and in game chats.
- **Embed**: your servers on your own website, with a script, an iframe or a status page. Websites that already show the list keep working.
- **Subdomains**: every server its own address under your domain, made in Cloudflare and removed with the server. Subdomains from PelicanSimplify 0.6 and 0.7 are taken over.
- The setup lists the add-ons that are still separate: Server Maker, Toolkit (formerly Mods), Explain, Egg Updates and Hosting. Plugins is no longer offered.

## 1.1.2

- Add-on details on a server card stay readable: the first one (such as the game version) joins game and status in the corner, the others (such as the address) get the full width under the server name. Compact cards leave them out.
- The overview starts right under the top bar instead of in the middle of the screen, so a short list no longer leaves an empty band above it.
- The setup's "Four short steps" line sits under the title, with room before the steps.

## 1.1.1

- Add-on details on a server card, such as the game version and the address, now sit on the bottom row instead of over the server name.
- Admins see one **Create server** button again, as in 0.7.0: the Server Maker when it is installed, otherwise Pelican's own form.

## 1.1.0

The Simplify you know from 0.7.0 is back, rebuilt in far less code.

- **Overview** as in 0.7.0: server cards with artwork, colour, live numbers and players; filter chips and a section per node; cards, compact or list; your own groups and server order.
- **Healthy** button in the top bar with the host details under it, and **Create server** next to it.
- **Server page** as in 0.7.0: a header card with status, address and power, number cards, the section menu and the live console, with quick commands on phones.
- **Host mode** for admins: the admin pages as a tree and the servers per node on the left, the page or server on the right, health at the bottom. Your choice is kept per device.
- **Settings with a menu on the left**: Simplify's own settings, every add-on, and Pelican's own settings tabs in one place.
- New notifications drop down from the bell, and the bell offers app notifications on a new device.
- Theme plugins such as Nord or Catppuccin now colour Simplify too.
- A status bar icon for Android, and update notices in each admin's own language.
- Hosting details moved to the new **Simplify Hosting** add-on: the billing link, a client area for customers and host mode as the default. The question "Who is this panel for?" is gone; the support link stays in Simplify.
- For add-on makers: `Addon::API` gives the contract version (2), with new `serverLinks()`, `customerLinks()` and `hostModeDefault()`. An add-on that throws no longer breaks the settings page.

## 1.0.0

Simplify, rebuilt from the ground up. Updates come from the Pelican Hub only. Add-ons such as Server Maker, Subdomains and Mods are separate plugins on the Hub and show up inside Simplify by themselves.

- **Overview** of all your servers with live CPU, memory and disk. Search, and start, stop, restart or kill from each card.
- Show all servers, only yours or only others'. Sort by name or date. Choose cards, compact or list. Your choice is saved per account.
- **Server groups** with your own name and colour.
- **Colour and banner per server**, set from the server page and shown on its card.
- **Server page**: the address to join and the SFTP login above Pelican's console, with copy buttons. Add-ons add their own sections here.
- **Host mode** for root admins: a compact server list per node, with owner, egg and address. Live numbers come from one request per node instead of one per server, so big panels stay fast.
- **Host health**: root admins see which nodes are under high load or do not answer, and why.
- **People & roles**: one page to add people (they choose their own password from an invite email), send reset links, change roles and delete accounts, with safety rules for the last root admin.
- **Setup wizard** on first use: who the panel is for (friends or a hosting company), start page, look, app and add-ons. Hosting panels get a billing link for customers and a support link in everyone's account menu.
- **Appearance**: accent colour, panel name, your own logo and app icon.
- **Install as an app** on phone or desktop, with an offline page.
- **App notifications** per device: messages from add-ons, and update notices for new Pelican and Wings versions for root admins. Encrypted so only that device can read them; admins can turn them off for the whole panel.
- **32 languages**, the same ones Pelican offers, including right-to-left for Arabic.
- New logo.
