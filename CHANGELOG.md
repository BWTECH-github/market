# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [1.0.1] - 2026-09-23

### Fixed

- **Sicherheit:** `downloadApp()` nahm einen lokalen Pfad aus dem Katalog an,
  ohne ihn einzugrenzen. Die Adresse eines App-Pakets steht in der
  Katalogantwort, stammt also von der Gegenstelle:

  - `file://...` wurde in **jedem** Betrieb angenommen, auch beim entfernten
    Marktplatz. Ein Katalogeintrag mit
    `file:///var/www/owncloud/config/config.php` liess damit eine beliebige
    lokale Datei in den App-Installer laufen, statt ein Paket zu liefern.
  - Im lokalen Katalogbetrieb wurde ein relativer Pfad ungeprueft an das
    Katalogverzeichnis gehaengt (`../../..` fuehrte heraus), ein absoluter
    Pfad sogar unveraendert uebernommen.

  Ein lokaler Pfad wird jetzt nur noch im lokalen Katalogbetrieb angenommen
  und muss nach dem Aufloesen **innerhalb** des Katalogverzeichnisses liegen;
  sonst wird abgewiesen. Ausserhalb dieses Betriebs sind nur `http://` und
  `https://` zulaessig - geprueft auf das vollstaendige Schema, nicht auf das
  Praefix `http` (das trifft auch `httpfoo://`).

## [1.0.0] - 2026-09-22

Redesign-Linie (owncloud.online 11.1). Für 11.0 gilt weiter der Zweig `main`.

### Fixed

- Nach Installieren, Aktualisieren oder Entfernen einer App baute der Markt die
  Seitenleiste neu auf und zerlegte dabei die des Redesigns: jede App stand
  doppelt, „Dateien“ erschien neben „Alle Dateien“, Start und Dateien verloren
  ihre Symbole. Jetzt wird nur die Gruppe „Menü“ nachgeführt; Start und
  Dateien bleiben unberührt, Theming bleibt hinten stehen.
- Meldungen des Marktes (auch „Installation fehlgeschlagen“) lagen auf
  schmalen Fenstern unter der Reiterleiste, Dialoge, Aufklapper und
  Hinweisblasen unter ihr und der Seitenleiste. Stapelhöhen an die Schale
  angepasst, Meldungen unten stehen über der Reiterleiste.
- App-Bundles: Ohne Bundles blieb die Seite völlig leer – der Leerhinweis hing
  an einer Bedingung, die für eine leere Liste nie zutraf. Jetzt mit Kopf und
  „Keine Bundles“.

### Changed

- Bildquellen des fremden Marktplatzes (marketplace-storage.owncloud.com,
  .owncloud.services) aus der Content-Security-Policy entfernt; Verweise auf
  fremde Repositories im Changelog als Text. Echte Umlaute in Kommentaren.
- Voraussetzung owncloud.online 11.1.

### Added

- `tests/visual/pruefe-market.js` (23 Prüfungen in 1440 und 400 px).

## [0.10.8] - 2026-08-13

### Changed

- README als Betriebsdokumentation neu geschrieben: Installation, Einstellungen,
  Kommandozeile und Fehlersuche; tote und fremde Verweise entfernt.

## [0.10.7] - 2026-08-13

### Fixed

- Beschriftung der Knöpfe bricht in der schmalen Aktionsspalte der App-Liste
  nicht mehr mitten im Wort um.

## [0.10.6] - 2026-08-13

### Changed

- Produktname, Beschreibung und übersetzte Zeichenketten nennen owncloud.online;
  Verweise auf Fehlerbereich, Repository und Dokumentation zeigen auf das eigene
  Repository. Screenshots aus fremden Repositories entfernt.

## [Unreleased]

### Added

- BW-Tech / owncloud.online fork.
- **Local-first marketplace catalog** at `marketplace/` (`apps.json`,
  `categories.json`, `bundles.json`); no remote backend required by default.
  See `marketplace/README.md` for the schema.
- Support for `file://` and catalog-relative download URLs in `HttpService`.
- Dark-mode UI via `prefers-color-scheme` and `[data-theme="dark"]` toggle.
- Refreshed tile design with custom CSS variables and smoother animations.
- **Live search** in the sidebar that filters apps by name, summary,
  description and category.
- Self-contained CI workflows that clone owncloud.online as core for
  integration smoke-checks (no longer depends on
  `owncloud/reusable-workflows`).
- Comprehensive `README.md` with installation, configuration, OCC reference,
  and troubleshooting matrix.

### Changed

- Minimum PHP version raised to **8.4**.
- Composer package renamed to `bwtech/market`.
- PHP code modernised: constructor property promotion, `readonly` properties,
  `#[\Override]` on interface / parent methods, typed properties,
  `match` instead of multi-`elseif`, `??` short-circuits, arrow functions
  where they improve readability.
- `info.xml` rebranded for owncloud.online (website, bugs, repository,
  author, description); `<php min-version>` bumped to `8.4`.
- Default `appstoreurl` is now `local` (bundled catalog) instead of
  `https://marketplace.owncloud.com`.
- Content-security policy in `PageController` now permits images from
  `owncloud.online`, `*.bw.tech`, `raw.githubusercontent.com`, and
  `github.com` to support the local-catalog use case.

### Removed

- Dependency on the `owncloud/reusable-workflows` GitHub Actions repository.

## [0.9.0] - 2024-06-04

- Upstream #1292 - fix: use webpack 5
- 23 security reports fixed
- Dependencies updated


## [0.8.0] - 2023-08-11

### Changed

- Upstream #1233 - Always return an int from Symfony Command execute method
- Upstream #1230 - Update suggested use for major update
- Minimum core version 10.11, minimum php version 7.4
- Dependencies updated

## [0.7.0] - 2022-07-05

### Changed

- Upstream-Kern #39387 - Update guzzle major version to 7
- This version requires ownCloud 10.11.0 or above

## [0.6.3] - 2022-02-16

### Fixed

- replace marketplace storage urls in csp - Upstream #913


## [0.6.2] - 2021-12-29

### Fixed

- Fix issues when the market API key is an empty string - Upstream #870

### Changed

- Change way adding nav entry to allow l10n - Upstream #864
- drop PHP 7.2 support - Upstream #773

## [0.6.1] - 2021-06-18

### Fixed

- Use relative url instead of absoulute url - Upstream #650

## [0.6.0] - 2020-07-10

### Removed

- Drop login button - Upstream #573
- Drop start trial functionality - Upstream #572

### Changed

- Set owncloud min-version to 10.5
- Only enable enterprise_key for versions < 10.5.0
- Bump libraries

### Security

- [Snyk] Security upgrade easygettext from 2.7.0 to 2.8.0 - Upstream #533
- [Security] Bump lodash from 4.17.11 to 4.17.15 - Upstream #518
- [Security] Bump https-proxy-agent from 2.2.1 to 2.2.4 - Upstream #505
- [Security] Bump mixin-deep from 1.3.1 to 1.3.2 - Upstream #497
- [Security] Bump jquery from 3.4.1 to 3.5.0 - Upstream #556
- [Synk] Security upgrade snyk from 1.269.0 to 1.290.1 - Upstream #527
- [Synk] Security upgrade node-sass from 4.12.0 to 4.13.1 - Upstream #523
- [Synk] Security upgrade sass-loader from 6.0.6 to 6.0.7 - Upstream #524

## [0.5.1] - 2020-07-28

### Added

- Notification about new trial - Upstream #586

## [0.5.0] - 2019-06-24

### Changed
- Provided information on how/where to retrieve API keys for the market app Upstream #468
- Library updates Upstream #465 Upstream #466 Upstream #467 Upstream #474 Upstream #476 Upstream #478 Upstream #482

## [0.4.0] - 2019-03-14

### Added

- Login directly from market-app to auto-install api-key - Upstream #443

### Changed

- Bump npm-watch from 0.1.9 to 0.5.0 - Upstream #429
- Bump vue-router from 2.7.0 to 3.0.2 - Upstream #431
- Bump vue-gettext from 2.0.23 to 2.1.2 - Upstream #440
- Bump vue and vue-template-compiler - Upstream #428
- Bump uikit from 3.0.0-beta.34 to 3.0.0-rc.26 - Upstream #441
- Bump uglify-js from 3.1.3 to 3.4.9 - Upstream #394
- Library updates to resolve vulnerabilities - Upstream #377 Upstream #445

### Fixed

- Hide api key in xhr-responses if key is configured in config.php - Upstream #454
- Hide 'Edit/Add API Key' button if not changeable - Upstream #185
- Fix "Logged In" is shown despite being logged out - Upstream #450
- Fix typo "Logged In" - Upstream #448
- Prevent uninstalling market app from within market app - Upstream #145

## [0.3.0] - 2018-12-20

### Changed

- Set max version to 10.1 because core platform is switching to Semver
- Admins can now choose between minor or major update of an app - Upstream #391
- Bugfix/update dependencies breaking - Upstream #407 Upstream #408
- Bump jakub-onderka/php-console-highlighter from 0.3.2 to 0.4 - Upstream #392
- Bump constantinople from 3.0.2 to 3.1.2 - Upstream #412

### Fixed

- Fix "--all" switch for occ command - Upstream #388
- Set max node version - Upstream #381

## [0.2.5] - 2018-07-25

### Fixed

- Rebuild top-left navigation if app was (de)installed - Upstream #359
- Check license of latest marketplace release - Upstream #362

## [0.2.4] - 2018-04-17

### Fixed
- Automatic cache invalidation when starting a enterprise trail Upstream #282
- Prevent update notifications for uninstalled apps Upstream #285

## [0.2.3] - 2017-11-14
### Fixed

- Show more detailed update information - Upstream #159
- Darken card box-shadow - Upstream #136
- Handle cluster setups better - Upstream #125 Upstream #184

## [0.2.2] - 2017-09-15
### Added

- Added market:uninstall command - Upstream #125
- Added background job to notify admins about app updates - Upstream #108

### Changed

- `occ market:list` will return a alphabetical sorted list - Upstream #122
- `occ market` commands will return non-zero exit codes on failure - Upstream #143
- Provide more detailed information when marketplace could not be reached - Upstream #141


### Fixed

- Better handling for cluster setups - Upstream #125
- Only show enterprise trail button when no license key is set - Upstream #142
- Top right menu will no longer be condensed - Upstream #149
- Only show links to publisher pages that are active - Upstream #157

## [0.2.1] - 2017-07-06

## Added

- Ability to start an enterprise trail from within owncloud - Upstream #107

## [0.2.0] - 2017-06-30

### Added

- Checking if internet connection is disabled for owncloud - Upstream #91
- Ability to download bundles - Upstream #89

### Changed

- If apps are not downloadable, a link to marketplace is provided - Upstream #93

### Fixed

- Translations have been updated - Upstream #75
- Erroneous sorting of releases - Upstream #90

## [0.1.0] - 2017-06-23

### Fixed

- Skip migrations when reinstalling missing code - Upstream #76
- Reset overwritten core css styles - Upstream #73

