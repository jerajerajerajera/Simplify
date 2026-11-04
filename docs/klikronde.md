# Klikronde

> Een vaste ronde van tien minuten op een echt paneel. Hij geldt voor alle zes repo's en woont alleen in de kern.
> Hij is de bron voor drie dingen: wat een mens op het echte paneel doorklikt (P-31), wat de browser-smoke
> automatisch doet (`tests/panel/browser.cjs`) en welke screenshots er in `docs/screenshots/` horen (WR-15).

## Wanneer en door wie

- **Volledige ronde**: bij elke stable release en bij elke beta met een nieuw scherm.
- **Alleen de geraakte stappen**: bij een beta met alleen fixes. De bug in `docs/bugs.md` noemt het scherm; loop de
  stap van dat scherm hier.
- **Wie**: Jeroen of Khion, op het echte paneel (de Beelink), na de update. Threads kunnen het echte paneel niet
  bereiken; zij leveren de browser-smoke en de screenshots van het testpaneel.
- **Uitkomst**: één regel aan de release-thread via de regisseur: `Klikronde <versie> door <naam> op <datum>: ok`,
  of `fout bij stap <n>: <wat je zag>`. Een fout wordt een bug met repo-prefix (WR-8), nooit "ok met opmerking".

## Zo lees je de lijst

- **Klik**: wat je doet. **Zie**: wat er moet gebeuren; ziet het er anders uit, dan is de stap fout.
- **Screenshot**: de vaste naam in `docs/screenshots/`, als `<scherm>-<thema>-<desktop|phone>.png`, licht en
  donker. Alleen nieuwe screenshots als het scherm veranderd is.
- **Auto**: deze stap zit al in de browser-smoke (naam van de stap in `browser.cjs`). **Echt paneel**: dit kan alleen
  op het echte paneel (echte Wings, echte console, Cloudflare, push, Safari op een iPhone). Een stap die automatisch
  kan, krijgt een scenario in `browser.cjs`; dan krimpt het deel voor het echte paneel.

## Kern (15 stappen)

| # | Klik | Zie | Screenshot | Auto of echt paneel |
|---|---|---|---|---|
| 1 | Inloggen als root admin | Het overzicht met je servers; geen witte pagina tijdens het laden, wel grijze placeholders | `overview` | Auto: "overview shows the servers" |
| 2 | Overzicht: een filterchip, een servergroep, zoeken op naam | Alleen de lijst verandert; de pagina zelf scrolt niet | `overview` | Deels auto: "overview does not scroll"; audit `overview-filters` |
| 3 | Een draaiende server openen | Titelkaart met status, adres en power; de console loopt live mee | `server` | Echt paneel (echte Wings) |
| 4 | In het servermenu naar Files en terug naar Console | Alleen het contentvak laadt; de console staat er meteen weer, zonder herladen | `server-menu-files` | Auto: "a menu click loads only the content" |
| 5 | Restart op de serverpagina | Tijdens stoppen en starten wordt de hoofdknop een rode Force stop; daarna weer gewoon | `server-restarting` | Echt paneel |
| 6 | Create server op het overzicht | De Server Maker opent als Simplify-popup, nooit Pelicans formulier | `create-server` | Echt paneel (met Server Maker) |
| 7 | Settings: iets wijzigen, dan Save | Na de wijziging "Changes made" met Save; na Save "Settings saved" | `settings` | Auto: "Settings saves from the title bar" |
| 8 | Settings: iets wijzigen en naar een andere pagina gaan | Eerst de vraag Save of Discard; niets wordt stil opgeslagen of weggegooid | `settings-leave` | Deels auto: audit `unsaved` |
| 9 | Admin openen | Admin opent op Health; het menu staat in de vaste volgorde (Health, Servers, Nodes, People & roles, Integrations, Simplify settings, Pelican settings, dan Simplify add-ons en Pelican plugins) | `admin-health` | Auto: "Admin opens without a full page load" |
| 10 | Admin › Servers › een server bewerken, Save, terug | Pelicans pagina in de Simplify-schil; Back to Simplify brengt je terug | `admin-servers` | Echt paneel |
| 11 | De bel openen | De nieuwste melding staat bovenaan; gelezen gaat naar Archived; alle meldingsopties staan in het venster | `notifications` | Echt paneel (echte meldingen) |
| 12 | Een server met een subdomein: adres kopiëren en in het spel verbinden | Het adres is het subdomein; verbinden werkt | `server-subdomain` | Echt paneel (Cloudflare) |
| 13 | Telefoon (Safari, als app geïnstalleerd): overzicht, dan omlaag trekken | Onderin één balk met Servers, New, Admin en Sections; omlaag trekken ververst alleen hier | `phone-overview` | Deels auto: "phone shows the bottom bar"; Safari alleen op een echte iPhone |
| 14 | Telefoon: een server openen, Sections, het blad weer dichttrekken | Sections opent als blad van onderaf; dichttrekken ververst nooit de pagina | `phone-server-sections` | Deels auto: audit `pull-sheet`; Safari alleen op een echte iPhone |
| 15 | Telefoon: Admin in de onderste balk | Het admin-menu als blad; elke tik doet wat er staat; een melding komt als push op de telefoon | `phone-admin` | Echt paneel (push) |

**Afsluiten** (na de update, door wie de update deed): Admin › Health toont geen nieuwe waarschuwing, en de log heeft
geen nieuwe `ERROR` sinds de update:

```bash
# in de map van het paneel
sudo tail -n 200 storage/logs/laravel-$(date +%F).log | grep ERROR
```

Geen uitvoer is goed. Elke regel is een fout bij stap "Afsluiten".

## Per add-on (twee stappen)

Alleen als de add-on geïnstalleerd is. Elke pagina opent binnen Simplify (P-1), nooit als losse Pelican-pagina.

| Add-on | # | Klik | Zie | Screenshot |
|---|---|---|---|---|
| Server Maker | SM1 | Create server vanaf het overzicht, het servermenu en Admin › Servers | Elke keer dezelfde Simplify-popup | `create-server` |
| Server Maker | SM2 | Een Minecraft-server echt aanmaken | De server installeert, de installatiekaart loopt mee, daarna First start met de EULA | `server-first-start` |
| Toolkit | TK1 | Op een draaiende Minecraft-server: Plugins via het servermenu | Alleen het contentvak laadt; de console staat er bij terugkeer meteen | `toolkit-plugins` |
| Toolkit | TK2 | Minecraft-pagina: de MOTD wijzigen, Save | "Changes made", na Save de herstartmelding; terug naar de console zonder herladen | `toolkit-minecraft` |
| Egg Updates | EU1 | Admin › Simplify add-ons › Egg Updates, Check now | De lijst met updates; een nieuwe versie staat ook in de bel | `eggupdates` |
| Egg Updates | EU2 | Test first bij één egg | De testserver installeert en start, en is daarna weg (ook als de test faalt) | `eggupdates-test-first` |
| Explain | EX1 | Explain op een console met een echte fout | Oorzaak en stappen in de taal van het paneel | `explain-console` |
| Explain | EX2 | Settings › Add-ons › Explain, Test | "Works" of een duidelijke fout; de sleutel staat nergens in beeld | `explain-settings` |
| Hosting | HO1 | Als klant (geen admin): het overzicht en het ⋯-menu op een serverpagina | De begroeting met de links; Upgrade this server in ⋯ | `hosting-customer` |
| Hosting | HO2 | Als klant: het accountmenu | Client area als icoon | `account-menu` |

## Bijhouden

- Verandert een scherm, dan verandert de stap hier in dezelfde PR (documentatie-thread, of de thread die het scherm
  bouwt meldt het).
- Krijgt een stap een scenario in `browser.cjs`, dan zet de kern-thread hier de naam van die stap bij "Auto".
