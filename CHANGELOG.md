# Changelog

## 1.1.2

- Add-on details on a server card (game version, address) get their own line under the server name, so the address stays readable. Compact cards leave them out.
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
