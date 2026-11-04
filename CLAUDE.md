<!-- GEMEENSCHAPPELIJK DEEL: in alle 6 Simplify-repo's gelijk. De regels staan in docs/regels.md, niet hier (V40). -->
# Werkwijze Simplify

Simplify is één product: de kern `simplify` plus de add-ons `simplify-servermaker`, `-toolkit`, `-eggupdates`,
`-explain` en `-hosting`. De splitsing is alleen verpakking: elke add-on volgt dezelfde UI en dezelfde regels.
Schrijf met de eigenaar in het Nederlands; code, README, CHANGELOG, commits en PR's in het Engels.

## Lees eerst

De regels staan niet in dit bestand. Lees bij de start, in deze volgorde:

1. `docs/regels.md`: werkregels (WR) en productregels (P). De kernrepo is de bron; elke add-on heeft een identieke kopie.
2. `docs/besluiten.md`: zoek hier eerst voor je zelf iets kiest (kopie van `plannen/besluiten.md` in project-files).
3. `docs/bugs.md`: bekende bugs en hun tests. Raak je die code, draai die tests.
4. `docs/prompts/startbrieven.md`: het algemene deel en de brief van je rol.

Bij verschil tussen dit bestand, de chat en `docs/regels.md` geldt `docs/regels.md`.

## Testen

- `bash tests/run.sh` (draait ook `tests/hub-check.py` en `tests/lang.php`), `pint --test` en in CI `checks.yml`
  met panel-smoke op elke Pelican-versie in de matrix. Wat "klaar" is, staat in P-31.
- Geen `composer install` of `yarn install` in de cloudcontainer; het echte paneel draait op GitHub Actions.

## Deze repo: de kern (`simplify`)

- Kern = overzicht, serverpagina, instellingen + wizard, hostmodus, host health, mensen/rollen, thema/branding/logo,
  PWA + push, meldingen/Announcements, Embed, Subdomains, 32 talen, en de plekken voor add-ons.
- **`docs/REFERENCE-070.md` woont hier** en geldt voor alle 6 repo's. Werk hem bij in dezelfde PR als de functie.
  Bron van 0.7.0: repo PelicanSimplify, tag/branch van 0.7.0-snapshot.10
  (`resources/views/dashboard.blade.php`, `resources/css/dashboard.css`, `resources/js/dashboard.js`).
- Add-on-contract: `ADDONS.md`, `Addon::API`. Een nieuwe `Addon`-methode krijgt altijd een default
  (`tests/addons.php` bewaakt dat) en een regel in ADDONS.md. Een nieuwere add-on moet op een oudere kern blijven werken.
- **Stijlaudit** (`.github/workflows/audit.yml`; de scripts staan op de branch `audit-tools`, niet in de plugin; audit.yml zet ze in `tests/audit`, input `tools` kiest een andere branch): verplicht bij elke UI-wijziging en vóór elke release.
  Inputs: `only` (standen), `section` (bijv. `compare`, `extras`, `seasons`), `legacy` (0.7.0 als vergelijking),
  `toolkit`/`servermaker` (add-on-branches). Verder `theme`, `parts` en `scope=changed` (alleen de delen die de gewijzigde bestanden raken; vóór een release de volledige audit).
  Resultaat op de ene branch `audit-results`, map `<branch>-<stand>` (licht: `-<stand>-light`; ophalen: `git fetch --depth 1 origin audit-results` en `git show FETCH_HEAD:<map>/style.md`); `style.md` moet leeg zijn. Mappen ouder dan 14 dagen vallen er vanzelf af.
  Een uitkomst is altijd ok of fout tegen een regel uit §4, nooit alleen informatie.
- **Upgradetest** in `checks.yml`: oude kern + volle instellingen → nieuwe versie (de test vanaf 0.7.0-bestanden
  draait alleen nog één keer vóór de 2.0-release naar de Hub, V37). Elke nieuwe
  migratie draait daar mee. Oude instellingen (alle velden ingevuld) horen in de test, niet alleen lege.
- Testpaneel van de audit moet op het echte paneel lijken: 20+ servers, lange namen, een nep-plugin met een eigen
  admin-pagina (voor "Other plugins"), een server die nooit gestart is, een server die installeert.
- Releasestappen kern: (1) functielijst bijgewerkt, (2) CI groen, (3) upgradetest groen, (4) stijlaudit groen incl.
  `section=compare` en `section=extras`, (5) screenshots van elk gewijzigd scherm, (6) ja van de eigenaar, (7) versie in
  `plugin.json` + CHANGELOG-sectie; `release.yml` maakt de GitHub-release met de Hub-zip.
