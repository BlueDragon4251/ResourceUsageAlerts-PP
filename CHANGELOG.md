# Changelog

All notable changes to Resource Usage Alerts are documented in this file.

Released versions are immutable. Every new change is documented under a new version section.

This project is source-available, not open source. See [`LICENSE`](./LICENSE) for usage rights.

## [1.3.6] - 2026-08-18

### Added

- Added signed custom webhooks with HMAC timestamp/nonce headers, optional previous-secret signatures during key rotation, configurable domain allowlisting, and private/reserved network blocking.
- Added notification-channel audit records that store actor, server, action, channel type, and changed field names without storing channel secrets.
- Added persistent incident comments and an incident timeline to the admin event detail page.
- Added a dashboard scheduler/queue health widget and a `resource-alerts:doctor` command for migrations, freshness, queue, push, and encrypted-secret checks.
- Added configurable stale-metric detection and dashboard visibility for stale samples.
- Added per-target backoff, chunked unique collection/evaluation, manual run summaries, and delivery-attempt tracking.
- Added maintenance windows, compound AND/OR conditions, rolling anomaly detection, escalation channels/severity, notification groups, and on-call rotation.
- Added network, disk I/O, inode, backup age/duration, swap, OOM, process, SSL, Wings, queue-health, and authenticated custom metrics.
- Added ntfy, Gotify, Matrix, payload templates, per-channel cooldowns, push sound/actions, and bounded automatic crash restart policies.
- Added MTTA/MTTR, alert heatmap, per-rule SVG sample previews, incident before/trigger/recovery context, filtered CSV/JSON exports, and weekly reports.
- Added token-protected status pages with incident history and Atom feed.
- Added rule dry-run, clone/severity bulk actions, UI/CLI rule import/export, owner dashboard, and first-install checklist.
- Added translation QA, payload-redaction coverage, Larastan/PHPStan/Pint configuration, and expanded focused tests.

### Changed

- Limited browser push subscriptions per user and automatically pruned older subscriptions beyond the configured limit.
- Changed panel notifications to render in each recipient's locale with English fallback for non-German locales.
- Changed advanced admin resources to require root-administrator access.

### Fixed

- Fixed a panel-wide HTTP 500 on Pelican `1.0.0-beta36` and newer by implementing the new plugin settings data contract while preserving compatibility with earlier supported betas.
- Fixed push endpoints being transferable between users when the same endpoint hash was submitted by another account.
- Fixed notification channels created for one server being reused for alerts from other servers owned by the same user.
- Fixed outdated samples triggering new alerts or resolving existing incidents after collection stopped.
- Fixed the team alert service querying a notification-channel table that has never existed instead of the encrypted resource alert channel table.
- Removed unused placeholder channel/cooldown services that queried the same non-existent table and were never connected to the active notification pipeline.
- Fixed plugin Artisan commands not being registered by the service provider.
- Fixed release and CI workflows ignoring relevant `developement`, changelog, update-feed, test, README, and workflow-only changes.
- Fixed the release workflow checking for `1.3.5` while creating `v1.3.5`, omitting `update.json` from release packages, and using CI commands that required a non-existent plugin `composer.json`/PHPUnit setup.
- Fixed fixed-English alert titles, bodies, payload labels, status labels, weekdays, and advanced resource labels.
- Fixed automatic restart locks not being released reliably after failed daemon calls.

## [1.3.5] - 2026-07-22

### Added

- Added plugin update feed metadata through `plugin.json` and `update.json`.
- Added signed and localized BlueIT announcements with Pelican inbox delivery, centered image popups, CTA buttons, permissions, and plugin-version targeting.

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
