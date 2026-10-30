# Functielijst: 0.7.0 is de norm

Deze lijst is de norm voor wat Simplify (kern + add-ons) moet kunnen. Elke functie van PelicanSimplify 0.7.0
(snapshot.10) staat hier, met de repo die hem levert. Zie `CLAUDE.md` §1.

Regels:

- `aanwezig` mag alleen met een test in de kolom **test** (bestand of audit-sectie) die de functie echt gebruikt.
  Een functie zonder test is niet af.
- `bewust weg` en `anders` alleen met datum en akkoord van de eigenaar in de noot.
- Werk deze lijst bij in dezelfde PR als de functie. Een kernrelease met `ontbreekt` of `deels` zonder akkoord
  van de eigenaar gaat niet door.
- Nieuwe functies na 0.7.0 komen onderaan onder *Nieuw*, met dezelfde kolommen.


Bron 0.7.0: jerajerajerajera/PelicanSimplify origin/snapshot (0.7.0-snapshot.10). Nu: simplify v1.5.1 + origin/claude/1.5.2 (a62c07c6); Toolkit 1.2.2, Server Maker 1.3.3, Explain 1.0.5, Egg Updates 1.1.1, Hosting 1.0.2 (main). Stand 2026-10-30.

Methode: Code gelezen: Livewire-methodes van 0.7.0 Dashboard/admin-pagina's, instellingen (FeatureSettings, settings-lang), dashboard.js-secties en Engelse lang-sleutels; per functie gezocht in kern + add-ons (PHP, Blade, JS).

Status: ontbreekt / deels / in 1.5.2 / anders / bewust weg / aanwezig.

| id | gebied | functie | wie | status | 0.7.0-bron | nu | repo | noot | test |
|---|---|---|---|---|---|---|---|---|---|
| A01 | Rechten | Simplify-rechten per rol en voor Everyone (spelinstellingen, bestanden, spelers, back-ups, mods, activiteit, taken, resources, software, advanced, legacy, aanmaken, eggs/games installeren) plus limieten per rol | admin | ontbreekt | RoleAccess, settings.abilities, People "Simplify permissions" | People & roles kent alleen admin-rechten van Pelican; servers volgen Pelicans subuser-rechten; Server Maker heeft eigen rollen en allowance | simplify / simplify-servermaker | Grote wijziging; vraagt keuze |  |
| A02 | Rechten | Administrator managed access (accounts aanmaken, server delen en extra taken alleen door admins) | admin | ontbreekt | feature restrict_access | Nergens | simplify | A3 in ochtendkeuze niet gekozen |  |
| A03 | Rechten | Legacy-modus voor gebruikers (gewone gebruikers mogen Pelicans eigen client-panel openen) | admin | ontbreekt | feature user_legacy | Legacy-knop gaat naar het admin-panel en is er alleen voor wie dat mag openen | simplify |  |  |
| A04 | Integratie | Knoppen en widgets van andere plugins op de serverpagina en het overzicht | iedereen | ontbreekt | PluginActions, PluginWidgets, ExternalPlugins | Alleen menu-items van andere plugins; add-on-knoppen via Addon-contract | simplify | A4 in ochtendkeuze niet gekozen |  |
| A05 | Integratie | Botsende plugin herkennen (conflicts.json): Simplify zet het overlappende deel uit | admin | ontbreekt | ExternalPlugins, conflicts.json | Nergens | simplify | S2 in ochtendkeuze niet gekozen |  |
| K01 | Serverpagina | Alias: weergavenaam naast de echte servernaam, in Look-menu en servernaam-scherm | eigenaar | ontbreekt | Dashboard::saveAlias, ServerMetadata | Alleen Hernoemen (echte naam) | simplify | In ochtendkeuze 30-10 (K5) niet gekozen |  |
| K02 | Overzicht | Kaarttitel: alias of echte naam tonen (Customize overview) | iedereen | ontbreekt | customize card_title | Keuze ontbreekt in Customize; hangt aan K01 | simplify |  |  |
| K03 | Serverpagina | Resources van een bestaande server zelf aanpassen (CPU, RAM, opslag binnen de grenzen), sectie Performance | eigenaar | ontbreekt | Dashboard::saveResourceLimits, ManagedResources | Alleen bij aanmaken in Server Maker (Resources-stap) | simplify-servermaker of simplify-toolkit | R1 was gekozen; alleen het aanmaak-deel is gebouwd |  |
| K04 | Serverpagina | Software of modpack wisselen (Vanilla, Vanilla+, Modpack, versie, pack-release; herinstallatie met back-up) | eigenaar | ontbreekt | Dashboard::changeSoftware/packSearch/packVersions, Software.php | Nergens; was toegezegd als Server Maker 1.4.0 "Change software" (SM staat op 1.3.3) | simplify-servermaker | T2 was gekozen |  |
| K05 | Installatie | Installatie stoppen (Kill tijdens installeren) | eigenaar | ontbreekt | Dashboard::killInstallation, InstallProgress::kill | Installatiescherm heeft alleen Notify me en Try again | simplify |  |  |
| K07 | Installatie | Installatielog (laatste regels) bij een mislukte installatie | eigenaar | ontbreekt | installation_log, InstallProgress | Alleen titel en Try again | simplify |  |  |
| K08 | Console | Log kopiëren (Copy the log) | eigenaar | ontbreekt | copy_the_log | Console heeft quick commands, groter, nieuw venster; geen kopieerknop | simplify | Klein |  |
| K09 | Serverpagina | Achtergrond "Geen achtergrond" kiezen | eigenaar | ontbreekt | ServerArtwork no_background | Alleen automatisch, standaardbanners en uploads | simplify | Klein |  |
| M01 | Server Maker | Steam-banner en -icoon overnemen voor een nieuwe server (instelling Offer Steam banners and icons) | admin | ontbreekt | SteamArt, feature steam_art | Nergens | simplify-servermaker | M5 "Steam" was gekozen; alleen STEAM_-variabelen onder Advanced gebouwd |  |
| M02 | Server Maker | Steam-account koppelen en Steam Web API-sleutel voor servertokens (CS2, Unturned, Insurgency), profieltab Steam | gebruiker | ontbreekt | SteamProfileTab, SteamAccount | Nergens | simplify-servermaker |  |  |
| M03 | Server Maker | Automatische back-ups (interval, herstelpunten) instellen bij aanmaken | maker | ontbreekt | review.rows.backups, form.change_backup_schedule | Review toont alleen "Backups allowed" (Pelican-limiet) | simplify-servermaker | M4 was gekozen |  |
| M05 | Server Maker | Serverkleur kiezen bij aanmaken | maker | ontbreekt | review.rows.color | Nergens in de wizard; kan pas na aanmaken via Look | simplify-servermaker |  |  |
| M06 | Server Maker | Alias bij aanmaken | maker | ontbreekt | form.server_alias_optional | Nergens; hangt aan K01 | simplify-servermaker |  |  |
| M07 | Server Maker | Startresources per Minecraft-type (CPU, RAM, disk voor Vanilla, Plugins, Mods, Modpack, Bedrock, Proxy) | admin | ontbreekt | settings.sections.starting_resources, CreationDefaults | Presets volgen het spel; geen instelling per type | simplify-servermaker |  |  |
| N01 | Meldingen | "What's new": een paar regels uit de release notes bij een updatemelding (Pelican, Wings, Simplify, plugins) | root-admin | ontbreekt | ReleaseNotes, announcements.panel_updates | Updatemelding zonder regels uit de changelog | simplify |  |  |
| S01 | Instellingen | Modus "Alleen de add-ons" (Pelican houdt eigen dashboard en serverpagina's) | admin | ontbreekt | feature addons_only, experiences | Simplify vervangt altijd het overzicht | simplify |  |  |
| S02 | Instellingen | Schakelaar "Toon legacy-pagina's" (Legacy-sectie in Admin tools en hostmodus) | admin | ontbreekt | feature legacy_pages | Legacy-knop altijd zichtbaar voor admins | simplify |  |  |
| S03 | Instellingen | Altijd donkere console | admin | ontbreekt | feature console_dark | Console volgt het thema | simplify |  |  |
| S05 | Instellingen | Pagina's van andere plugins tonen of verbergen in het admin-menu | admin | ontbreekt | settings.sections.pages_from_other_plugins, external_shown | "Other plugins" altijd als groep zichtbaar (1.5.2) | simplify | Klein |  |
| S06 | Systeemcontrole | Uploadlimieten controleren (PHP, Livewire, NGINX) | admin | ontbreekt | checks.uploads, UploadLimits | Systeemcontroles: https, openssl, extensies, schrijfrechten, cache, opslag, queue | simplify |  |  |
| T01 | Plugins & mods | Geïnstalleerde plugin of mod bijwerken (Update / Update anyway) | eigenaar | ontbreekt | dashboard.js installPackage(update) | Toolkit Packages: installeren, aan/uit, verwijderen; geen bijwerken | simplify-toolkit (repo simplify-mods) |  |  |
| T02 | Plugins & mods | Zoeken filteren per site (All sites, Modrinth, Hangar, SpigotMC) | eigenaar | ontbreekt | dashboard.js packageSites | Toolkit zoekt altijd alle sites tegelijk | simplify-toolkit | Klein |  |
| T04 | Bestanden | Herstartmelding na opslaan van een bestand ("File changes saved, restart to load them") | eigenaar | ontbreekt | Dashboard::fileChanged | Pelicans bestandsbeheer meldt niets; herinnering komt alleen van Toolkit-pagina's | simplify-toolkit / simplify |  |  |
| W01 | Website | Per server extra website-gegevens (beschrijving, achtergrond, MOTD, icoon) via een website-plugin (simplify.web) | eigenaar | ontbreekt | Dashboard::saveWebsite, Extensions::web | Website-pagina heeft alleen "op de website" aan/uit en de code. Geen enkele huidige repo registreert simplify.web | simplify | Alleen relevant als die website-plugin terugkomt |  |
| W02 | Website | PelicanSimplify-web (server-directory 0.5.7) leest het subdomeinadres via de oude klasse Simplify\Panel\Services\Subdomains | admin | ontbreekt | PelicanSimplify-web DirectoryStore.php r.105-114 | Met kern 1.5 bestaat die klasse niet: de website toont geen subdomein-adres en valt terug op IP:poort | PelicanSimplify-web | Raakt deckzwabbers.nl |  |
| K06 | Installatie | Annuleren en server verwijderen tijdens of na een mislukte installatie | eigenaar | deels | Dashboard::deleteSetupServer | Na een mislukte installatie kan Delete via ⋯; tijdens het installeren niet, en niet vanuit het installatiescherm | simplify |  |  |
| M04 | Server Maker | MOTD, view distance, spawn protection, PvP, whitelist, allow flight bij aanmaken | maker | deels | maker.properties, form.motd/pvp/whitelist | Eerste instellingen hebben alleen max. spelers, spelmodus, moeilijkheid, icoon, EULA | simplify-servermaker |  |  |
| S04 | Instellingen | Verversingssnelheden serverpagina, herstartvoortgang en cache hostgezondheid | admin | deels | PerformanceSettings | Alleen "Overview refresh (seconds)" | simplify | H2 in ochtendkeuze niet gekozen |  |
| U01 | Overzicht | Serveroverzicht zo compact als 0.7.0 | iedereen | in 1.5.2 | dashboard.css 0.7.0 | Lijst is nu langer; vergelijking 0.7.0 vs 1.5.1 loopt (punt 8) | simplify | Nog te bouwen |  |
| U02 | Navigatie | Menu-items laden alleen het contentvak (geen hele pagina herladen) | iedereen | in 1.5.2 | 0.7.0 SPA-secties (dashboard.js) | Punt 7, nog te bouwen | simplify |  |  |
| U03 | Hostmodus | Statusblok in admin-sidebar met uitleg en labels | admin | in 1.5.2 | host health checks met namen | Punt 10: labels/uitleg of weg, nog te doen | simplify |  |  |
| U04 | Serverpagina | Eerste start: "ready to start" in de titelkaart, First start-knop, EULA-vinkje, geen Restart zolang nooit gestart | eigenaar | in 1.5.2 | Your server is ready / Accept and start | Gebouwd op claude/1.5.2 (2f5e0d1b) | simplify | Gebouwd, nog niet uitgebracht |  |
| U05 | Aanmaken | Create server opent overal in een Simplify-popup (ook Admin › Servers) | admin | in 1.5.2 | Simplify creator vs Legacy creator | Gebouwd op claude/1.5.2 (a62c07c6, c35457c4) | simplify | Gebouwd, nog niet uitgebracht |  |
| U06 | Serverpagina | Automatische back-ups als tab van Backups & tasks | eigenaar | in 1.5.2 | sectie Backups (recovery points & automatic backups) | Gebouwd op claude/1.5.2 (96470843) | simplify | Gebouwd, nog niet uitgebracht |  |
| M08 | Server Maker | Kiezen of de server na installatie automatisch start | maker | anders | review.rows.start | Altijd handmatig ("You start it when the installation is done") | simplify-servermaker |  |  |
| T03 | Spelinstellingen | Wijzigingen klaarzetten tot de server stopt, met Discard changes en Apply changes and restart | eigenaar | anders | PendingChanges, ApplyServerProperties | Toolkit schrijft server.properties direct en toont "Changes are waiting for a restart" (Restart now, plannen, wegklikken). Geen Discard | simplify-toolkit |  |  |
| X01 | Serverpagina | Eigen Simplify-werkruimtes voor Files, Backups, Scheduled tasks, Activity, Network, Advanced (database, toegang, runtime, image, startup) | eigenaar | anders | 13 secties in dashboard.js | Pelicans eigen pagina's in het Simplify-kader; zeldzame onder Advanced | simplify | Bewuste keuze 30-10 |  |
| X02 | Bestanden | Eigen bestandseditor (Ctrl+S, revisiecontrole, ander bestand openen zonder opslaan) | eigenaar | anders | dashboard.js file editor, fileRevision | Pelicans bestandsbeheer | simplify |  |  |
| X03 | Back-ups | Back-ups downloaden en terugzetten (met servernaam bevestigen) | eigenaar | anders | Dashboard::downloadBackup/restoreBackup | Pelicans Backups-pagina | simplify |  |  |
| X04 | Instellingen | Functieschakelaars (resources, spelers, mods, software, back-ups, subdomeinen, embed, People manager, hostgezondheid) | admin | anders | FeatureSettings | Per add-on installeren; kern-onderdelen Announcements, Embed, Subdomains aan/uit | simplify |  |  |
| X05 | Instellingen | Gedeeld uiterlijk en legacy-werkruimtes in Simplify-stijl | admin | anders | global_theme, legacy_theme | Simplify-kader om Pelican-pagina's; volgt thema-plugins (PanelTheme) | simplify |  |  |
| X06 | Setup | Setup-wizard (11 stappen: hoeveel Simplify, functies, add-ons, mensen & toegang, look, Server Maker, subdomeinen, website, panel type, hosting, klaar) | admin | anders | SimplifySettings steps | 5 stappen: Add-ons + systeemcontrole, Your panel, Look, Phone app, Set up add-ons (vragen van add-ons) | simplify |  |  |
| X07 | Hostmodus | Hostmodus automatisch tonen voor grote hosts | admin | anders | feature host_mode | Instelling "Open in host mode" in Hosting | simplify-hosting |  |  |
| X08 | Hosting | Support-link en knoptekst | admin | anders | settings.hosting.support | Support link in kern (General); "Open a ticket" in Explain | simplify / simplify-explain |  |  |
| B01 | Plugins | Pluginbeheer, automatische updates met rollback, kanalen stable/beta/snapshot | admin | bewust weg | ManagePlugins, PluginUpdater, channels | Pelicans Plugins-pagina + Hub; updatemelding in de bel (PluginUpdates) | n.v.t. | Hub-regel |  |
| P01 | Overzicht | Kaarten, kleine kaarten of lijst; kolommen; kaartbreedte; volle breedte | iedereen | aanwezig |  | Customize overview | simplify |  |  |
| P02 | Overzicht | Kaartinhoud: kaarticoon server/spel, MOTD, CPU- en RAM-grafiekje, snelle acties hover/altijd | iedereen | aanwezig |  | Customize overview | simplify |  |  |
| P03 | Overzicht | Groeperen per node, eigen servergroepen met kleur en volgorde, knop Server groups, sorteren (naam, aangemaakt, poort, eigen) | iedereen | aanwezig |  | groups-menu, dashboard.js | simplify |  |  |
| P04 | Overzicht | Filters op spel, node en eigenaar; zoeken in nodes | iedereen | aanwezig |  | overview.blade | simplify |  |  |
| P05 | Overzicht | Hostgezondheid (bovenbalk, boven, onder), per node, verversen | admin | aanwezig |  | HostHealth | simplify |  |  |
| P06 | Overzicht | Serverkleuren (één of per server), seizoensaccenten | iedereen | aanwezig |  | Customize + Appearance | simplify |  |  |
| P07 | Overzicht | Vriendenmodus (alle kaarten zichtbaar, "not assigned to you") | admin | aanwezig |  | General › friends_mode | simplify |  |  |
| P08 | Overzicht | Klantweergave (groet, Order a server, Upgrade this server, Client area) | klant | aanwezig |  | Hosting add-on | simplify-hosting |  |  |
| P09 | Hostmodus | Lijst per node met CPU, RAM, opslag, netwerk; admin-menu met secties; wisselen simpel/host | admin | aanwezig |  | host.blade, AdminMenu | simplify |  |  |
| P10 | Serverpagina | Power-knoppen, force stop, live cijfers, spelers online, software, adres kopiëren, SFTP-gegevens | eigenaar | aanwezig |  | ServerPage, server.blade | simplify |  |  |
| P11 | Serverpagina | Hernoemen, kleur, banner (standaard + eigen uploads), server verwijderen | eigenaar | aanwezig |  | ⋯-menu en Look | simplify |  |  |
| P12 | Console | Live console, quick commands, grotere console, in nieuw venster, logstrookje op andere pagina's, Explain this error | eigenaar | aanwezig |  | server.blade, Explain | simplify / simplify-explain |  |  |
| P13 | Installatie | Installatiescherm met stappen en tijd, Notify me when ready, Try again | eigenaar | aanwezig |  | server.blade ps-install | simplify |  |  |
| P14 | Spelinstellingen | Minecraft-pagina: MOTD-editor, servericoon (upload of modpack-icoon), spelmodus, moeilijkheid, max. spelers, view/simulation distance, spawn protection, PvP, hardcore, flight, whitelist, EULA | eigenaar | aanwezig |  | Toolkit Minecraft | simplify-toolkit |  |  |
| P15 | Spelers | Online spelers, whitelist, operators, bans; kick, ban, op | eigenaar | aanwezig |  | Toolkit Players | simplify-toolkit |  |  |
| P16 | Plugins & mods | Zoeken op Modrinth, Hangar, SpigotMC, CurseForge; populair; install anyway; aan/uit; verwijderen met back-up; eigen CurseForge-sleutel; uitgezet na start + Explain | eigenaar | aanwezig |  | Toolkit Packages | simplify-toolkit |  |  |
| P17 | Back-ups | Automatische back-ups (interval, herstelpunten, overslaan als niet gedraaid, standaard voor nieuwe servers) | eigenaar | aanwezig |  | Toolkit Backups | simplify-toolkit |  |  |
| P18 | Herstart | Herstartherinnering, Restart now, herstart over X minuten plannen | eigenaar | aanwezig |  | Toolkit RestartActions | simplify-toolkit |  |  |
| P19 | Meldingen | Bel: Mark read/unread, Mark all read, Clear read, Archived, vastgepind, meldingenpagina | iedereen | aanwezig |  | Bell, notifications.blade | simplify | Jeroen moet #90/#92 nog testen |  |
| P20 | Telefoon-app | App installeren, pushmeldingen aan/uit, test, soorten (aankondigingen, updates, Pelican), apparaten, app-icoon en statusbalkicoon, telefoonnavigatie | iedereen | aanwezig |  | WebApp, Push, tabbar | simplify |  |  |
| P21 | Aankondigingen | Balk, categorie, doelgroep, van/tot, knop, in de bel, vastpinnen, als app-melding, in-game per server/groep met herhaling, spelcommando's | admin | aanwezig |  | Announcements (kern) | simplify |  |  |
| P22 | Meldingen | Updatemeldingen Pelican, Wings en plugins voor root-admins | root-admin | aanwezig |  | UpdateNotices, PluginUpdates | simplify |  |  |
| P23 | Uiterlijk | Paneelnaam en logo (lettertype, gewicht, kleur, verloop, spatiëring, hoofdletters, × Pelican, logohoogte), accentkleur | admin | aanwezig |  | Appearance | simplify |  |  |
| P24 | Subdomeinen | Cloudflare-instellingen met test, poortbereik, per server subdomein kiezen | admin/eigenaar | aanwezig |  | Subdomains (kern) | simplify |  |  |
| P25 | Website | Embed: stijl, hoeken, accent, spelers, modpack, join-adres; code voor alle/één server/spel/groep; per server op de website | admin/eigenaar | aanwezig |  | Embed (kern), Website-pagina | simplify |  |  |
| P26 | People | Zoeken, toevoegen met uitnodiging, bewerken, wachtwoordlink, verwijderen, 2FA- en servers-kolom, rollen met admin-rechten | admin | aanwezig |  | People & roles | simplify | Simplify-rechten per rol: zie A01 |  |
| P27 | Explain | Console, panel log, Wings log; Claude, Gemini, GPT met model; wie mag; limieten; sleuteltest; noodpagina; ticketlink | admin | aanwezig |  | Explain 1.0.5 | simplify-explain |  |  |
| P28 | Egg Updates | Modi (vragen, auto, test eerst, uit), check, update, alles, testserver, servers verplaatsen, terugzetten, meldingen | admin | aanwezig |  | Egg Updates 1.1.1 | simplify-eggupdates |  |  |
| P29 | Server Maker | 170+ spellen, eggs uit de index installeren, Minecraft-types, versies, modpacks (Modrinth, CurseForge, FTB, Technic), willekeurige naam, node/poort, subdomein, resources met grenzen, icoon, EULA, Java, image, startup, variabelen, scripts overslaan, concept | maker | aanwezig |  | Server Maker 1.3.3 | simplify-servermaker |  |  |
| P30 | Server Maker | Zelf aanmaken: rollen, nodes, toegestane spellen, ontbrekende eggs, allowance (servers, RAM, disk, CPU, back-ups) | admin | aanwezig |  | MakerSettings | simplify-servermaker |  |  |
| P31 | Instellingen | Pelicans eigen instellingen als tabs in Simplify; startpagina; support-link; panel type | admin | aanwezig |  | Settings | simplify |  |  |
| P32 | Overig | 32 talen in kern en add-ons; noodpagina bij paneelfout; thema-plugins volgen | iedereen | aanwezig |  | lang/, Explain, PanelTheme | alle |  |  |

## Nieuw (na 0.7.0)

| id | gebied | functie | wie | status | bron | nu | repo | noot | test |
|---|---|---|---|---|---|---|---|---|---|
