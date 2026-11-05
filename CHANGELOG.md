# Changelog

## Unreleased

## 2.0.0-beta.6

- Pelican's lists inside Simplify (servers, users, files, schedules and the rest) are Simplify's list of rows on every screen: the name, the other columns as short texts and chips, the row's buttons on the right. A "Sort by" menu with a direction button takes the place of the column titles.
- Server page: Server color and Server background open from a small pencil on the server's icon instead of the ⋯ menu; on a phone they open as a sheet.
- Phones: the server page's head is lower and its numbers (players, CPU, memory, disk) are one strip, so the console starts higher.
- Phones: the Admin menu slides away to the left over the page you tapped, and Admin, the breadcrumb's group or a swipe from the edge slide it back in, instead of the page jumping into place.
- Add-ons: a page with its own Blade view gets the same head as every other page with the partial `simplify::partials.page-head` (title, subtitle, actions with Save last; ADDONS.md, Page head).
- Admin: People & roles is gone; Pelican's Users and Roles make up the People group of the Admin menu, in the same row lists as every other admin list.
- Add-ons: an add-on puts its server pages among the Backups & tasks tabs itself, and can take over Pelican's server pages such as Settings and Startup, which then leave the server menu (add-on API 9, `serverTabs()` and `replacedServerPages()`).
- Uninstalling Simplify keeps its settings, announcements, subdomains and website details; installing it again brings everything back.
- Save is a button with its name and icon, as the last button in a page's head (Simplify settings, Automatic subdomains, a server's Website page).
- Settings › Add-ons: every add-on is a section that opens, with what it does first and its settings under it. Simplify add-ons in the Admin menu holds only add-ons with a page of their own (add-on API 8, `settingsForm()`).
- A tooltip (such as the bell's "Notifications") no longer stays on screen when you go to another page.
- Phones: fields you type in have 16px text, so Safari on an iPhone no longer zooms in on them and leaves the page sliding sideways.
- Server page: the quick commands above a Minecraft console on a phone, and the two-column layout of Toolkit's pages, come from Toolkit now; Simplify on its own no longer carries them.
- Errors: a server's page has the spot for an add-on's button (such as Explain) next to Try again when its installation failed, and Simplify gives that add-on the installation's last lines. A notification in the bell that carries an error id (`simplify_problem`) gets the same spot (ADDONS.md, Errors).
- Pelican's lists inside Simplify no longer show "Showing 1 to 6 of 6 results" or a "Per page" choice, and with one page the page bar is gone.
- Account menu: Legacy panel is the last item, under a line and in grey.
- Setup: an installed add-on shows its own name, description and icon in the add-on list.
- Admin: the breadcrumb no longer cuts "All servers" short when there is room to spare; a part only gives way when the names do not fit.
- Admin: a little room between a page's content and its scrollbar.
- Phones: in a list's toolbar the Sort by menu stays narrow and the search keeps the height of the buttons beside it.
- Admin: on a window around 1280 wide, a list with Group by and Sort by (Servers) keeps its toolbar on one row; the search gives way.
- Server page: a server on an IPv6 address shows it in brackets with the port, as Pelican does ("[2001:db8::1]:25565"), so the copied address works in a game.
- Errors: an add-on such as Explain can put its own button right after an error; on the Health page each check that needs attention has that spot (ADDONS.md, Errors).
- Admin: an action that shows its name from a certain width on (such as Test first and Update in Egg Updates) shows as one button again, not as an icon button and a button with text side by side.
- Add-on API 7: `serverSections()` is gone from the contract; no add-on used it since the console page fits the window (1.3.9).
- Checks: PHPStan (level 5) and ESLint run in CI, and a browser smoke (Playwright with axe and Lighthouse) opens the overview, a server's console, a menu click, Admin and Settings on a desktop and a phone.
- Styles: the Admin side reads the same design tokens as the overview and the server page (`tokens.css` loads everywhere; the `--s-*` names are aliases), so a corner or a shadow is the same size on every page.

## 2.0.0-beta.5

- Server page: a server with a working subdomain shows just that subdomain as its address, under the name and in the status bar, and the copy button copies it. Without a subdomain it stays the game address with port. The address is no longer cut off; a long software name gets the ellipsis instead.
- Phones: the Admin pages run on under the bottom bar, without a thin dark strip above it, and their last line scrolls fully above the bar.
- Phones: on the overview the copyright line stands at the bottom, just above the bottom bar, when there are only a few servers, and moves down with the list when there are more.
- Console: the buttons in the console's title row (an add-on's button such as Explain, Bigger and the pop-out window) can be clicked again; the console's output no longer lies over them.

## 2.0.0-beta.4

- Tablets: Admin lists keep their filter, search and buttons on one row, the Admin page's top bar keeps the panel's name on one line, and Customize Simplify opens inside the window.
- Health in the top bar no longer says Unavailable for a moment when you come back from Admin to the overview, so the buttons next to it stay where they are.
- New Simplify logo: the pelican S has a little more weight at the bottom. The favicon, the app icons on a phone's home screen, the notification badge and the Hub banner use it too.
- Phones: every menu, sheet and popup slides up as it opens and back down as it closes, also with ✕, Escape or a tap beside it, instead of fading or vanishing. While you drag one down it follows your thumb and the dimmed page behind it brightens; let go past a third of its height, or flick it, and it slides away, otherwise it springs back. A swipe up scrolls, a swipe sideways still goes back. The confirm dialog slides away too, a message at the bottom fades out and can be swiped aside, and buttons, rows and menu items give a little under your thumb. With reduced motion on, nothing slides. Computers keep the short fade.
- Light theme: the console's scrollbar is light again, so you can see it on the dark console.
- Legacy panel: Simplify's settings look as Pelican's pages do. With many add-ons the row of tabs goes onto a second line instead of running off the page with a scrollbar (phones keep one row that scrolls).
- Add-on buttons are back where Simplify hides a page's header. On a server's console page an add-on's button, such as Explain, sits in the console's title row next to the size tools; Pelican's power buttons stay in the server card. In host mode, a button that an add-on puts in a page's header, such as Explain on Logs, is in the title bar, and the window it opens shows.
- Plugins page in Admin: the categories (All, Plugin, Theme, Language Pack) are a filter button in the list's toolbar, on one row with sort, search and the import buttons. A choice changes only the list.
- Automatic subdomains no longer says Changes made when you only open the page: the browser no longer fills in your saved panel password as the Cloudflare token.
- Automatic subdomains explains itself: a How to set it up section with the four steps and a link to Cloudflare's token page (folded shut once everything is set), and what is still missing under the title. Until Cloudflare is set up, root admins still see Subdomain in the server menu, with what is missing and a Set up subdomains button; owners see it once it works.
- Subdomains keep working after the update from PelicanSimplify 0.7.0: the Cloudflare token and zone ID that 0.7.0 read from `.env` (`CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID`) are taken over once, encrypted, unless Simplify already has its own. `.env` is only read.
- Website: a server's Website page now sets what the website shows besides Pelican's own data: a description (empty uses Pelican's), an image (the server's banner, another banner or an upload), a client download (empty uses the download of the installed modpack version), a second address (for example for Bedrock, public addresses only) and the position on the list. Nothing is saved before Save, also the switch to show the server. `/simplify/embed/servers.json` gives each server `description`, `banner`, `download`, `address2` and `position`, and lists online servers first, then by position, then by name. Images are served at `/simplify/embed/banners/{file}`, only while their server is on the website.
- Simplify's settings: the head no longer repeats the version (it is in the panel's footer). What Save does is a small line under the settings, and Run setup again is a button on General only, not in the head of every tab.
- Browse Hub is hidden for now: it is no longer a tab of Simplify's settings or an item of the Admin menu. It can come back later.
- The Admin menu, the Admin page's card, the Settings tabs and Customize Simplify have Simplify's thin scrollbar again, instead of the browser's own bar with arrows.
- Phones: with the admin menu open, every tap does what it says again. Admin keeps the menu, Servers opens the overview and the account button opens the account menu; before, each of them showed the first admin page (Servers › Servers). The menu no longer slides along when you drag down on it.
- Customize Simplify is wider and shows what is in a section straight away: the sections (Theme, Server groups, Layout, Cards, Grouping, More) sit on the left and the chosen one's settings on the right, with no folding open. Server groups lists your groups with their colour and number of servers, and Edit groups opens their window. On a phone the sections are a row of chips at the top of the sheet. On a server's page the sections are Theme and Server pages; the Admin page and the account pages keep the narrow window with the theme only. It opens on the section you had last.
- Phones: the overview ends with Pelican's copyright line again, under the servers and above the bottom bar.
- Host health: opened, everything around the nodes lights up on hover, together with the card and its tab, and a click there folds it shut again. A node keeps its own hover and click.
- The Admin page and the account pages have the same background as the overview, in light and dark. Going from the overview to Admin and back, for example with Go to the panel at the end of the setup, no longer makes the background lighter or darker.
- Computers: on an Admin page the page's own buttons, such as Run setup again and Save on Settings › General, sit in the middle of the breadcrumb's row again, with room under the header, instead of at the top of that row.
- Admin tools open like the rest of an app: from the overview to the Admin page and back (the shield, Admin and Servers in the bottom bar, All servers in the breadcrumb) only the content changes. The header and the bottom bar stay where they are, without a whole page and its placeholders loading in between.
- The shield always opens Simplify's Admin page, also after the legacy panel was opened from the account menu.
- Health is the start page of Admin tools and the first item of its menu.
- The Admin menu is calmer: pages in regular type with more room and without tree lines, and groups you open stay open. On top: Health, Servers, Nodes, People & roles (one item instead of Users, Roles and People & roles; Pelican's own Users and Roles stay in the legacy panel), Integrations, Simplify settings with the list of add-ons last, and Pelican settings with Pelican's plugin list. Apart below them: Simplify add-ons, then Pelican plugins (pages of plugins that are not Simplify, called Other plugins before).
- Healthy stays in the top bar on the Admin page, on a phone too, and keeps its state there: it no longer turns into a grey "Health", and the buttons next to it no longer move when you switch.
- The console page loads without a jump: the empty card that stood above the console for a moment while Pelican's numbers loaded no longer shows, so the console stays where it is.
- Phones: the window with all notifications closes with a pull down, like the menus: from its top bar, or from the list when it is scrolled to the top. Popups and menus follow your thumb while you drag them, spring back after a short pull, slide away after a longer one, and stay put on a sideways swipe. Closing them never reloads the page.
- Website embed: the preview of the saved list follows the theme instead of showing a white block in dark mode, and is only as big as the cards it shows. The iframe code for other websites no longer shows a white block on a dark website either.
- Phones: every admin list fits the screen. A row shows the icon, the name and its buttons on the first line and the other columns under the name, on more lines when needed, so nothing scrolls sideways. The buttons, search and filter share one row right under the breadcrumb; the search field gets smaller when room runs out, down to just its magnifier, and opens over the row when tapped.

## 2.0.0-beta.3

- Texts say server or egg where they said game, because not every server is a game: Website embed picks one egg, Subdomains asks for the server address, the install steps and Force stop no longer speak of games, and the in-game chat commands of Announcements are set per egg (as they were already stored). In all 32 languages.
- The filters are back on the overview. On a desktop each filter is a dropdown of its own above the servers: Egg first and always there, then Show (all, mine, others), Node and Group when there is something to choose. On a phone one Filter opens them all as a sheet, and it no longer disappears when every server has the same egg, node and owner. The overview filters per egg instead of per game, because not every server is a game. Every choice shows how many servers it holds.
- CPU and RAM on a server card show the same bars as the server page instead of a line: one bar per refresh from 0 to the server's limit, the newest bar solid, sliding one step left on each refresh. Only the bars take the colour of the load (green below 60%, orange from 60%, red from 85%, grey when unknown); the value stays neutral and sits on one line with its label. On a phone each value in the list row gets a small bar chart behind it, and the icon names it instead of the words CPU and RAM.
- Customize is now called Customize Simplify, and the theme (Light, Dark, System) is its first row instead of an item in the account menu. On the Admin page and the account pages Customize Simplify opens too, with the theme only.
- Connections in the account menu, right after Profile: one page for the outside accounts that add-ons use for you, such as a Steam account. It shows only when an add-on has a row for it. Add-ons give their rows with `connections()` (ADDONS.md, API 6).
- Beta versions name the browser after the version in the footer (browser, system, window size, app or browser, light or dark), so a screenshot shows where it was taken. It is worked out in the browser and never sent. Stable versions show the version only.
- Phones: Admin in the bottom bar opens the admin menu as a page of its own, with the same tree as on a computer and Health at the bottom. A link opens its page, and the page's title is where you are, such as Servers › Eggs; tap the group, tap Admin again or swipe from the left edge to go back to the menu. The separate Menu tab is gone, so the bar is Servers, New and Admin again, and the page sits straight on the background without a card around it.
- Computers: the Admin page belongs to the overview instead of looking like a separate site. The header is the same as on the overview, with Healthy (which opens the Health page) and Create server, and the shield lights up while you are in Admin; press it again to go back. The menu and the page are exactly as wide as the header and sit straight on the background without cards. The page's title is where you are, such as All servers › Servers › Eggs, with the page's buttons on the same row. The Admin and Servers tabs above the menu are gone, and announcements stay in the bell.
- The Admin page has the panel's accent colour again, such as New in the bottom bar on a phone.
- The installed app on an iPhone keeps the bottom bar clear of the home bar after a click in a menu too. It no longer drops a little and loses its room at the bottom when another page opens.
- Add-ons can ask whether the panel runs in friends mode: `PanelSettings::friendsMode()` (ADDONS.md).
- Pages no longer open white. While Simplify starts, the overview, a server's page and the Admin page show their shapes as grey placeholders in your light or dark theme, and the real page takes their place.
- Pages load faster on a first visit and after every update: Simplify sends its styles and scripts gzipped (about 100 KB instead of 400 KB), because Pelican's web server compresses only HTML.
- Pelican's copyright line on the Admin page and the account pages sits at the very end of the page, under everything on it, on a phone too. It no longer stays halfway over the page where the first screen ended, and it keeps Simplify's thin style after a click in the menu.
- The setup and its last step are part of Settings: the Admin menu shows Settings as the current page and the breadcrumb reads Settings › Set up Simplify. Their addresses moved to /admin/simplify/setup and /admin/simplify/setup-done.
- The numbers on the server page show the last minute as bars next to the number instead of a faint line under the text. The bars run from 0 to what the server may use, take the colour of the load (green, orange from 60%, red from 85%, as on the overview cards) and glide between two measurements; the number changes once per measurement. The line under the number reads "35% of 400%". Each card has its icon and a short name (CPU, MEM, DISK, NET) in the top-right corner, and the cards are lower than before. Network shows download and upload each with their own number, download bars up and upload bars down.

## 2.0.0-beta.1

- A download of the repository holds the plugin only, without its screenshots, art, tests and scripts (2.3 MB instead of 9.3 MB).
- Simplify's settings are one row of tabs: Appearance, General, App, Website, Subdomains, Announcements, each add-on's own settings, Add-ons, Browse Hub and Pelican settings. Website, Subdomains, Announcements and the add-ons' pages show the same row over them. Pelican's settings open Pelican's own page in the Admin page, without frames. Appearance keeps the colour, the panel name and the logo; the name's style, the logo's height, what the top bar shows and the start page stay as they were saved and can be changed in Run setup again; the refresh time keeps its saved value. Server banners moved to General.
- One Filter on the overview instead of the rows of chips: Show (all, mine, others), Game, Node and, when you have server groups, Group, in one menu (a sheet on a phone). The chip names what is chosen.
- Phones: one bottom bar everywhere. Servers, New and Admin (the last two for who may use them); on a server a fourth tab, Sections, opens every section as one sheet with Console first, and on the Admin page Menu opens the admin pages. Customize is in the account menu on every screen, with the server groups inside it.
- The Admin page, for every admin. The shield opens Pelican's admin pages inside Simplify: Simplify's header on top, the admin pages in a menu on the left (or the servers per node, with node health at the bottom), and the page itself in a card next to it. The menu stays where it is while its links change the page. On a phone the bottom bar has Servers, Admin and Menu, and Menu opens the admin pages as a sheet. The old host mode, its switch in the account menu and the shield's dropdown are gone; old links to an admin page inside the overview open that page. Admin → Servers → Create still opens the Create server popup.
- The account pages of add-ons (such as Server Maker's Steam account) open on Pelican's account panel inside Simplify, without a frame. Customize is in the account menu on the overview.
- The legacy panel in the account menu shows Pelican's pages as Pelican draws them until you press Back to Simplify, on admin and account pages alike.
- Back to Simplify in the legacy panel is the first item of Pelican's sidebar. It never showed before, because Pelican's panels have no top bar.
- Nothing saves itself any more, on a phone too. A change in Simplify's settings or a form page shows "Changes made" with Discard and Save at the bottom, and leaving the page with changes (a link, the menu, going back, closing the tab) asks Save, Discard or Cancel first. Add-ons use the same with one attribute (`data-simplify-unsaved`, see ADDONS.md).
- On a phone, Pelican's own lists in the legacy panel (such as Nodes) look as Pelican draws them again. Simplify's short rows apply only inside Simplify.
- One place for each action. A server card's quick actions are Start, Stop and Restart; Force stop and Delete server are in the server page's ⋯ menu (and the main button still turns into Force stop while a server starts or stops); Rename is the pencil by the name. The floating status bar keeps Start, Stop and Restart. The legacy panel moved to the account menu, server groups open from Customize, and host health sits only in the top bar. The account menu is a list with words on every screen, without a second way to the notification settings that the bell already has.
- Add-ons can give a start page (add-on contract API 5): the server list and the overview open it when an add-on gives one, and Pelican's admin pages then keep Simplify's header on top. The groups of the admin menu are now built on the server.
- A suspended server's card no longer shows the tops of its CPU and RAM tiles above the note about the suspension.
- The overview and the server page load less: the code and styles for holidays load only on a day with one.
- Checks and screen audit take less time. A change to text only (docs, Markdown) skips the panel smoke; tests and pint still run. In the screen audit, dark and light are jobs of their own, side by side, so a run takes half as long; every job puts its result in a folder of its own on the one branch `audit-results` (`<branch>-<stand>`, the light job `…-<stand>-light`), instead of a branch per result; folders older than 14 days drop off. New inputs: `theme`, `parts` (the parts of a round: overview, server, admin, host, seasons, create, extras, notify, klant) and `scope` `changed`, which runs only the parts that the files changed since main can touch (`tests/audit/parts.py`; an unknown file counts as everything). The full audit still runs before every release.

## 1.5.5

- The line under a server's name (status, software, address) stays one line on narrower screens: a long software name is shortened with … instead of pushing the address to a second line, so every server's page keeps the same height. Between 961 and 1200 px the message of the day box is a little narrower to make room.
- Pelican's admin pages on a tablet or small window no longer scroll by the height of the menu button at the top.
- Add-ons can put their own pages in the account menu, such as Server Maker's Steam account. They open inside Simplify, in the content area (add-on contract API 4).
- Settings › Browse Hub: the themes, plugins and languages on the Pelican Hub, inside Simplify. Every item shows its maker first, and the makers are a filter of their own; Details shows the full description and the Hub's screenshots, and items you already have are marked Installed. Install on Hub opens the item on the Hub, which installs it on your panel through its own connection, so every download counts for its maker. The list is read from the Hub at most once an hour.
- Edit schedule: the buttons (delete, run now, export, save) sit in a bar over the page instead of on top of the Name field. On a phone the tasks are short rows with their edit and delete buttons in view, instead of a table that ran off the screen (old plugin issue #236).
- A suspended server's card tells its owner that the hosting company paused it, with the add-on's main link next to it (with Simplify-Hosting: Pay invoice). Admins see the card as before.
- Pinned announcements on the overview sit under the top bar again, as wide as the servers, as in 0.7.0, instead of across the full width above it.
- Pull down to reload works on the overview only, in the app and in a browser tab. In the settings, on other pages and when you swipe a sheet down to close it, nothing reloads any more: the browser's own pull to refresh is off.

## 1.5.4

- Switches (such as Capital letters in the settings and the notification switches) sit inside their row again; their knob no longer slides past the end of the track.
- Simplify's settings fill the window on the first page load as well, instead of a short card with empty space under it until something else on the page changed.
- Every notification option is in one place: the bell's window with all notifications, under Customize notifications. Next to this device, Messages and Updates it now has Pelican's notifications, a warning when Pelican's scheduler stopped, and your devices, each with when it was added, its last notification and a button to remove it. The separate App notifications page is gone; App notifications in the account menu opens the bell's window.
- Phones: one frame on every screen. The top bar has the panel's name, the bell and your account; the menu button is gone. One bar at the bottom: on the overview Customize (server groups and the overview's look), New and Admin tools; on a server its sections; in host mode one button with the page's name that opens every admin page. A server page goes back to the overview with the arrow before the name.
- Phones: every menu, the notifications and the account open as a sheet from the bottom over the full width, with large rows. A tap beside the sheet or a swipe down closes it.
- Phones: Simplify's settings save themselves after every change, and a toast at the bottom says so; the Save button shows only on Pelican's own tabs.
- Phones: Pelican's lists in the admin pages (Eggs, Servers, Nodes, Users) show short rows without checkboxes, column titles or the egg descriptions.
- A server without a picture of its own shows an icon for its kind of server (Python, Node.js, Discord bot, Java, Go, database, Steam game or another game) instead of the cube. The cube stays for anything else.

## 1.5.3

- Simplify's settings on a phone: the settings open at full length and the page scrolls, instead of a box of a few lines under the menu with empty space below it.
- A server that is still installing no longer shows Start or Restart, in the server card and in the status bar. They come back when the installation is done, with First start for a server that never ran.
- Console on a phone: the quick commands stay put. Every time Pelican redrew its console (on a status change or a reconnect) the row disappeared for a moment and came back.
- Host mode on a phone: a page's buttons, such as Health's refresh or the servers' search and Create, sit in a slim row above the page, instead of loose in the page under a wide empty space.
- Installed app: pull down from the top of any page to reload it, also in host mode. A round refresh mark follows your finger and turns; let go once it lights up.

## 1.5.2

- First start in the server card: a server that just installed, or whose Minecraft EULA is still open, says "Ready for its first start" next to its status, and the Start button reads First start (Restart waits until then). With the EULA open, a box before the button asks you to accept it yourself, with the EULA a click away; First start then writes eula.txt and starts. Start or Restart with the EULA open points to that box. The separate banner above the card is gone.
- Server menu: a click changes only the page next to the menu. The header, the server card and the menu stay where they are, also when you go to or from the console, instead of the whole server page loading again. On Pelican versions before 1.0.0-rc.1, whose console does not close its connection when Livewire changes the page, the menu loads the page as before.
- Simplify's admin pages (such as Website embed and Setup done) no longer scroll a little in a low window or on a phone; only their card scrolls.
- Server menu: after a click the server page no longer scrolls a little. The page's footer stays the thin row it is on the first load.
- Server menu: Simplify Toolkit's Automatic backups joins Backups & tasks as a tab between Backups and Schedules, instead of an item of its own.
- Create server opens Server Maker in a Simplify popup, from the overview, a server page and Admin → Servers alike, instead of on its own page or in Pelican's own form. Without Server Maker, Pelican's form opens in that popup. Ctrl/cmd-click still opens it in a new tab.
- Create server and host mode: the announcement bars no longer show a second time inside the popup or the frame, and Setup done no longer draws a second box inside the frame or shows Pelican's header there. A server that installs no longer shows Pelican's "not yet completed its installation" warning under Simplify's install card.
- Host mode: a page's buttons always move to the title bar, also when the page loaded before the title bar showed (Health's refresh button sometimes stayed in the page).
- Host mode and Admin tools: Other plugins folds open like the other groups, with each plugin's page by its own name, also when there is only one. Before, a single plugin page opened straight away under the name Other plugins.
- Scrollbars are thinner and take a light tint of the background in the dark theme and a soft grey in the light theme, instead of a dark stripe.
- Server page: the bottom of the server's ⋯ menu (Force stop, Delete server) can be clicked again where it hangs over the console. The console took the mouse there and showed a text cursor.
- Back from 0.7.0: an add-on's customer link with an icon, such as Simplify Hosting's Client area, is also in the account menu, before Support.
- For other plugins: `Looks::COLORS` lists the server colours of the ⋯ menu, and an admin page whose form has the class `s-fit` fits the window like Simplify's own.
- For other plugins: `Subdomains::gameAddress()` gives a server's join address, falling back to the shared game address with the port when the server has no working subdomain.

## 1.5.1

- Updating from 1.4.4 on a panel that came from PelicanSimplify 0.7.0 stopped with "Undefined array key logo_text" and left Simplify not installed. 0.7.0's "text beside the logo" now becomes Simplify's "Logo + text".

## 1.5.0

- Back from PelicanSimplify 0.7.0: an owner deletes their own server from its ⋯ menu after typing its name. It has to be stopped, or unable to run (Wings does not know it, its install failed, or it is suspended).
- Server pages: Pelican's "Open in Admin" is no longer in the server menu. It led to the legacy admin page; renaming, the look and deleting are in the server's ⋯ menu.
- Back from 0.7.0: the server's ⋯ menu shows the SFTP username and a link that opens the SFTP connection; the password is your panel password.
- Back from 0.7.0: friends mode (Settings → General). Everyone signed in sees every server's card; opening one still needs Pelican's access. A panel that had it on in 0.7.0 keeps it on.
- Back from 0.7.0: the setup asks who the panel is for (me and my friends, or a hosting company), which sets friends mode, and starts with a system check: PHP extensions, writable folders, cache, storage, the queue, Pelican's scheduler, https and the keys for app notifications. Only what fails is listed, with how to fix it.
- Back from 0.7.0: the notifications page warns when Pelican's scheduler is not running, and shows root admins the cron line to add.
- Back from 0.7.0: Pelican's own messages in the bell (such as a finished backup) reach your devices as app notifications too, with their own switch.
- Back from 0.7.0: the website embed can show one of your server groups.
- Back from 0.7.0: on Pelican's own pages a button in the top bar leads back to Simplify.
- Back from 0.7.0: an announcement can also be sent in game to the servers of your server groups.
- Back from 0.7.0: Customize → server pages: open with the bigger console, show the last console lines on the other server pages, and use the whole width.
- Back from 0.7.0: a server's background can be one of the standard scenes or an earlier upload, from the server's ⋯ menu. Settings → Appearance lists the uploads, and admins remove the ones no server uses.
- Back from 0.7.0: the panel name's font (with the Monocraft pixel font), weight, one colour or a fade between two, letter spacing, capitals, and the name beside the logo. A 0.7.0 panel keeps its style.
- The installation view from 0.7.0 is back on the server page. While Pelican installs a new or reinstalled server, a card under the server card shows the steps, the time so far and how long this game usually takes on this panel, with Notify me (a browser notification when it is done). When the installation finishes while the page is open, a banner above the card says the server is ready and offers Start server. After a failed installation the card says so and offers Try again.
- Back from 0.7.0: a Minecraft server that still needs its EULA says so in the same banner above the server card, with Accept and start. It writes eula.txt and starts the server, so the first start no longer stops on the EULA.
- A Minecraft server without an icon of its own (no server icon from the game, none set in Pelican, none on its egg) shows a grass block instead of the plain cube, on its card and on its page. An icon from the game, Pelican or the egg still comes first.
- The browser tab on a server's pages reads "<server name> - Simplify", on the console and on every tab under it.
- Server cards and the server page: when an icon does not load (for example an egg icon behind a wrong APP_URL, or a deleted file), the next one is used: the game's icon, then the icon set in Pelican, then the egg's icon, then the cube. Before, the spot stayed empty.
- Admin tools: every page opens inside Simplify, in the same frame and title bar as host mode, with a close button back to the overview. Before, outside host mode the links went to Pelican's own admin pages with Pelican's menus. From a server page the link goes through the overview. Ctrl/cmd-click still opens a page in a new tab.
- Create server: without Server Maker, the button on the overview opens Pelican's create form inside Simplify, in the same frame as Admin tools, instead of on Pelican's own admin page. With Server Maker it still opens Server Maker.
- Overview: the Host health card and the tab under it are one hover zone. Hovering either lights up both in the same colour, as both open the same list of nodes.
- Outside host mode, Simplify's admin pages no longer scroll as a whole page: Settings and Pelican's Health page keep their card as high as the window leaves it and scroll inside it (the settings menu stays in place), People & roles keeps its tabs and search on top and scrolls the list, and Website, Automatic subdomains and Announcements scroll their form under the page title.
- Notifications: "Mark read" on one notification works again. Before, it marked an unread notification unread, so nothing changed.
- Notifications: Archived is back, as in PelicanSimplify 0.7.0. "Clear read" in the bell and in the window with every notification moves the read ones there, and ✕ on a notification moves that one. Archived sits closed at the bottom of the window with every notification; "Mark as unread" brings a notification back. Nothing is deleted.
- Announcements: "Pin to everyone's notifications" is back, as in PelicanSimplify 0.7.0. With "Also put in everyone's notifications" on, an admin can pin the announcement: it stays under Pinned at the top of the bell and of the window with every notification until it ends, read or not, and Clear read leaves it there.
- Scrollbars stop a little short of the top and bottom and sit a little further from the edge, so they fit inside the rounded corners of menus, cards and windows.
- Overview: the scrollbar of the server list sits right next to the cards instead of at the far edge of the window.
- Overview, list view: the server icon sits in the middle of its row again instead of sticking out over the top edge. A hidden badge counted as a third one and pushed it up; in the card view it added empty room under the icon.
- Server menu: shorter. On top the everyday pages (Console, Files, Backups & tasks, the add-ons' pages such as Minecraft and Website); Pelican's rarely used pages (Users, Databases, Network, Startup, Settings, Mounts, Webhooks, Activity) fold under Advanced, with Delete server at the bottom. Backups and Schedules are one item, "Backups & tasks", with a tab for each on top of their pages. On a phone they are under More.

## 1.4.4

- A cache that the panel cannot write no longer breaks the overview, the notifications or host health. This happens when `php artisan` ran as root, so the cache files belong to root. The page works out the numbers again instead, and root admins get a notification with the command that hands the files back to the web server.
- A request that fails on the server shows a short message in the panel's own style, instead of Livewire's white "500 | Server Error" page over the whole panel.
- Admin tools: the groups are straight rows on the left, with how many pages each holds; the open group has a stripe in its colour and an arrow towards its pages, which show as tiles on the right. One group at a time, so the menu stays short. In the phone menu the tiles fold out under their group. It ends above the footer, so nothing is cut off on the overview. Every menu under the top bar (notifications, admin tools, customize, groups) is at most two thirds of the window high and scrolls inside when its content needs more.
- Settings → Appearance: under the logo, the app icon and the status bar icon a small picture shows where each one ends up: the top bar, a phone's home screen, and Android's status bar with a notification. It follows a new upload, the panel name and the logo height before you save.
- Settings → Pelican's own tabs (Mail, Backup, Misc and the others): the form sits straight in Simplify's card instead of in a second box, and Simplify's Save button (in the title bar in host mode) saves it. Pelican's separate save icon is gone.
- Host mode → People & roles: the People/Roles tabs, the search field and the add button are in the title bar, and the list sits straight on the page instead of in a second box.
- A button whose menu is open no longer shows its tooltip over the menu's arrow.
- Settings: Save sits in the head of the page next to "Run setup again", instead of a blue button under the card. Website → embed code: the copy buttons are Simplify's own buttons.
- Notifications: the button at the top of the bell menu opens a window with every notification (as in PelicanSimplify 0.5), with "Customize notifications" above the list: turn notifications on or off for this device, send a test, and choose messages and updates. It replaces the settings button that went to a separate page. 32 languages.
- Plugin updates: once an hour Simplify lets Pelican's own update check look for new plugin versions, and tells root admins once per version in the bell (and as an app notification when they get updates), with a button to Admin → Plugins. Pelican installs the update as before; Simplify downloads nothing. Until now Pelican only showed an update when someone opened Admin → Plugins. 32 languages.
- The bell no longer shows PelicanSimplify's old "Plugin updated" and "… is available" notifications, whose button led to a page that no longer exists.
- The small arrow above a top-bar menu points at the middle of its button.
- Host mode: every group in the menu on the left folds open and shut, and "Expand all" / "Collapse all" above it opens or closes them all. The group of the open page stays open. 32 languages.
- Host mode: a Pelican page's search field and buttons sit in the title bar again (as in PelicanSimplify 0.7.0), and its table or sections have no card of their own inside Simplify's box. Create is the named button, the others are icon buttons; selecting rows brings back the bulk actions.
- Host mode: the settings in the tree (General, Appearance, Add-ons and Pelican's tabs) save with the button in the title bar too, like Announcements, Automatic subdomains and Website, instead of a separate button under the form.
- Server page: the console is one box in one colour. Pelican's terminal painted a half see-through background of its own, which showed as a lighter box inside the card.
- Server page: no empty grey badge next to the server's picture when its egg has no icon.
- Server page: the server's icon set in Pelican shows in the console header too, as on its card on the overview. Before, the header skipped it and showed the egg's icon or the cube.
- Settings → General, Appearance and the other settings open the settings again on a panel taken over from PelicanSimplify. They sent you to the first-time setup every time, because that panel had settings but no finished setup.
- Admin tools → Pelican settings opens Pelican's tab inside Simplify's settings again, instead of Pelican's bare page.
- Pelican's Health page in Simplify's look: a summary line with when the checks last ran, then what needs attention with a button to where you fix it (Nodes for outdated Wings, the release notes for an outdated panel), then the checks that are fine as short rows with their own icon. Pelican's refresh button stays; in host mode it sits in the title bar. 32 languages.
- Overview: the Host health card folds open with a tab that sticks out under the middle of its bottom edge, instead of a small arrow in its title, so it is clearer that the card opens to show every node.
- Website embed: after saving, "One server" and "One game" pick the first on the list, so the code and the preview show straight away.

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
- Server page: the ⋯ menu and the colour menu open below the server card again. Before, the card cut them off, so only their top edge showed inside the card.
- App: a panel with its own app icon can be installed as an app again. Chrome only offers Install with a square PNG of the size the manifest names, so Simplify now serves the uploaded icon (also a JPEG or a non-square image) as a square PNG of 192 and 512 pixels.
- Overview list: when the quick power buttons slide in, the player count moves along smoothly with the rest of the row instead of jumping.
- App: the splash screen of the installed app is dark, like the panel, instead of white. Android picks this up when Chrome next updates the app, or on a new install.
- The browser tab icon is the Simplify logo at full size on a transparent background, instead of a small logo in a dark circle.
- Every menu under the top bar (admin tools, customize, groups, notifications) ends inside the window, also when announcements give the bar a second row.
- Tablets and narrow windows (up to 900 pixels): the top bar's buttons sit in the menu, as on a phone, so the panel name, the account button and the admin tools no longer fall off the screen or break up letter by letter.
- Server page: the five numbers fill their rows on a tablet (three over two) and sit two by two on a phone, so values and labels are no longer cut off. On a phone "× Pelican" stays on one line. A long panel name breaks between words, never inside one.
- Host mode: a server in a low frame folds its numbers into one slim row, so its box keeps a usable console instead of the frame scrolling. The traffic figure gets double width there, so it is not cut off.
- The admin tools list each add-on once, and "People & roles" has its own icon.
- Fixed a script error on server pages ("brandTextStyle is not a function").
- Known: in host mode, Pelican's own list pages (Eggs, Servers, Users and so on) still scroll as a whole page inside the frame, title included. This follows in 1.4.4.

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
