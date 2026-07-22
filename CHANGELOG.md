# Changelog

All notable changes to Resource Usage Alerts are documented in this file.

The changelog is maintained under the current release version. New changes are added to the latest version section until the project owner explicitly requests a version bump.

This project is source-available, not open source. See [`LICENSE`](./LICENSE) for usage rights.

## [1.3.5] - Unreleased

### Added

- Added plugin update feed metadata through `plugin.json` and `update.json`.

### Changed

- Changed the plugin version to `1.3.5`.

### Fixed

- Fixed BlueIT announcements using Pelican's top-right toast instead of the centered image popup, and suppressed legacy duplicate toasts.
- Fixed BlueIT announcement popups being hidden behind the server console or missing from the general server overview.
- Fixed BlueIT announcement close buttons not immediately hiding and persisting dismissal of the popup.
- Fixed BlueIT announcement rendering breaking Alpine and Livewire navigation with `_x_teleportBack` errors by mounting the listener directly at Filament's body hook.
- Fixed deleted or no-longer-applicable BlueIT announcements remaining visible after the remote announcement was removed.

## [1.2.1] - 2026-06-10

### Added

- Added source-available license file.
- Added license section to the README.
- Added German and English language files for plugin-owned UI strings.
- Added locale fallback logic: German is used when the active locale starts with `de`; every other locale falls back to English.
- Added translated labels for metrics, navigation, settings, notification channels, rule forms, actions, and common plugin messages where the text is controlled by this plugin.

### Changed

- Marked the plugin as source-available, not open source.
- Clarified that redistribution, rebranding, public forks, modified public releases, and resale are not allowed without permission.
- Updated README with language behavior and license/usage rights.
- Kept the plugin language handling isolated to Resource Usage Alerts and did not change the global Pelican locale.

### Fixed

- Fixed compatibility problems caused by outdated Pelican/Filament references.
- Fixed the settings page to avoid references to non-existent Panel models/classes.
- Fixed notification channel handling so Telegram and Slack are visible/configurable where supported.
- Fixed plugin pages so missing configuration or missing tables are handled more safely instead of causing avoidable 500 errors.

## [1.2.0] - 2026-06-09

### Added

- Acknowledge-Feature (offen → bestätigt → gelöst) mit Button in Admin + Server UI.
- Status-Badges mit Farben (rot/gelb/grün).
- Neue Metriken: Network I/O, Swap, Process Count, Inode, Disk IOPS, Backup Duration/Stale, OOM Events, SSL Cert Expiry, Wings Version.
- Telegram & Slack Notification-Channel Services.
- Enhanced Discord Embeds mit Feldern, Links, Timestamps.
- REST-API für Alarme, Stats, Rules (`/api/alerts/`).
- Auto-Restart Listener bei Server-Crash.
- Regel-Templates (12 Vorlagen) + Bulk-Erstellung + Rule-Duplizierung.
- Eskalations-Stufen (Severity-Erhöhung nach X Minuten).
- On-Call Rotation (zeitbasiert).
- CSV-Export für Events und Samples.
- Sample-Verlauf als Chart (Server-Seite).
- Auto-Refresh Dashboard (30s Intervall).
- Notification-Cooldown pro Kanal (statt nur pro Rule).
- Sample-Retention pro Metric konfigurierbar.
- Admin-Panel Settings-Seite (Filament Page).
- Status-Seite Widget mit Alarm-Übersicht.
- Trend-Chart Widget (14 Tage).
- HTML-E-Mail-Template für Alarme.
- Changelog, phpstan.neon, pint.json.
- Plugin-Update-URL für automatische Updates.
- CI/CD Pipeline (GitHub Actions: PHPStan, Pint, Tests).
- Feature Tests für Acknowledge-Feature.
- `resource-alerts:clean` Befehl mit `--days` Option.

### Changed

- Admin Events Tabelle: Status-Spalte mit Farb-Badges.
- Server Open Alerts: Zeigt auch acknowledged-Alarme mit Aktionen.
- AlertRuleEvaluator: Behandelt open + acknowledged als aktiv.
- Plugin-Version von 1.1.1 auf 1.2.0.

## [1.1.1] - 2026-06-08

- Erste stable Version.
- Server/Node CPU, RAM, Disk Überwachung.
- Server Offline/Crashed, Backup Failed.
- Discord, E-Mail, Push Benachrichtigungen.
- Admin-Dashboard + Server-Seite.
