# Changelog

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
