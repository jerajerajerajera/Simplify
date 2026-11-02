# Bugregister Simplify (kern)

> Elke gefikste bug staat hier, met de test die hem bewaakt (WR-11). Een terugkerende bug is een regressie: zoek
> eerst uit waarom de test hem niet ving. Een rij weghalen mag niet, de test weghalen ook niet. Nummers (B-n) geeft
> alleen de regisseur; Startvulling 2-11 (V35): de
> bugs die twee keer of vaker terugkwamen, uit CHANGELOG en projectnotities. "Nog geen test" is een open punt voor
> de repo-thread.

| Nr | Datum | Wat zag de gebruiker | Oorzaak | Fix (versie) | Regressietest | Teruggekomen | Regel |
|---|---|---|---|---|---|---|---|
| B9 | 1.3.5–1.5.4 | De pagina scrolt als geheel (serverpagina, hostmodus, Settings, Health, Website, Setup done, instellingen op telefoon, eerste paginalading) | Elk nieuw of gewijzigd scherm kreeg zijn hoogte niet vast; alleen het contentvak mag scrollen | 1.3.5, 1.3.10, 1.3.11, 1.5.0, 1.5.2, 1.5.3, 1.5.4 | audit `scroll-*`, `settings-first-load`, `settings-phone`, `setup-done-host`, `schedule-edit` | 6× | P-7 |
| B10 | 1.4.3, 1.5.2 | Menu's onder de bovenbalk of het ⋯-menu deels niet klikbaar of afgesneden (kaart sneed het af, console pakte de muis) | Laag (z-index) of overflow van de ouder | 1.4.3, 1.5.2 | audit `layers-*` (klik op het midden van elk item) | 1× | P-15 |
| B11 | 1.4.4, 1.5.1 | Na een update vanaf PelicanSimplify 0.7.0: Settings stuurde steeds naar de setup (1.4.4), of de update stopte met "Undefined array key logo_text" (1.5.1) | Updatepad niet getest met de vorm van echte 0.7.0-instellingen | 1.4.4, 1.5.1 | `checks.yml` upgradetest (1.3.11 + PelicanSimplify-bestanden → 1.4.4 → nieuw; "Install Simplify over its own tables") | 1× | P-28 |
| B12 | 1.4.3, 1.5.3 | Powerknoppen kloppen niet met de staat: Stop/Restart grijs op elke server (1.4.3); Start/Restart zichtbaar tijdens installatie (1.5.3) | Knoppen lazen een waarde die niet (meer) gezet werd | 1.4.3, 1.5.3 | audit `install-buttons`, `first-installed` | 1× | P-16 |
| B13 | 1.5.2 | Een klik in het servermenu laadt de hele serverpagina opnieuw (console, websocket) | Menu zonder `wire:navigate`/`@persist` | 1.5.2 | audit `nav-*` (merkteken op `window`) | 0× (wel in 0.7.0-tijd) | P-11 |

## Patronen

Bugs met dezelfde oorzaak, en de regel of check die het hele patroon voorkomt.
- **Pagina scrolt** (B9: kern 6×, Toolkit, Server Maker, Explain, Hosting): P-7. Elke instellingspagina van een add-on
  heeft `s-fit` en een smoke-stap die dat controleert; elk kernscherm een audit `scroll-*`.
- **Updatepad** (B11, kern 1.4.4 en 1.5.1): P-28. Upgradetest met echte 0.7.0-instellingen in `checks.yml`.
- **Testpaneel lijkt niet op het echte paneel** (B15 Egg Updates 1.1.3/1.1.4, kern-audit): P-31. Smoke en audit bootsen
  het echte paneel na (volle nodes, echte eggs, 20+ servers, nep-plugin).
