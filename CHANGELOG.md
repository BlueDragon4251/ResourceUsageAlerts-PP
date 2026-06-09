# Changelog

## [1.2.0] - 2026-06-09

### Added
- Acknowledge-Feature (offen → bestätigt → gelöst) mit Button in Admin + Server UI
- Status-Badges mit Farben (rot/gelb/grün)
- Neue Metriken: Network I/O, Swap, Process Count, Inode, Disk IOPS, Backup Duration/Stale, OOM Events, SSL Cert Expiry, Wings Version
- Telegram & Slack Notification-Channel Services
- Enhanced Discord Embeds mit Feldern, Links, Timestamps
- REST-API für Alarme, Stats, Rules (`/api/alerts/`)
- Auto-Restart Listener bei Server-Crash
- Regel-Templates (12 Vorlagen) + Bulk-Erstellung + Rule-Duplizierung
- Eskalations-Stufen (Severity-Erhöhung nach X Minuten)
- On-Call Rotation (zeitbasiert)
- CSV-Export für Events und Samples
- Sample-Verlauf als Chart (Server-Seite)
- Auto-Refresh Dashboard (30s Intervall)
- Notification-Cooldown pro Kanal (statt nur pro Rule)
- Sample-Retention pro Metric konfigurierbar
- Admin-Panel Settings-Seite (Filament Page)
- Status-Seite Widget mit Alarm-Übersicht
- Trend-Chart Widget (14 Tage)
- HTML-E-Mail-Template für Alarme
- Changelog, phpstan.neon, pint.json
- Plugin-Update-URL für automatische Updates
- CI/CD Pipeline (GitHub Actions: PHPStan, Pint, Tests)
- Feature Tests für Acknowledge-Feature
- `resource-alerts:clean` Befehl mit `--days` Option

### Changed
- Admin Events Tabelle: Status-Spalte mit Farb-Badges
- Server Open Alerts: Zeigt auch acknowledged-Alarme mit Aktionen
- AlertRuleEvaluator: Behandelt open + acknowledged als aktiv
- Plugin-Version von 1.1.1 auf 1.2.0

## [1.1.1] - 2026-06-08

- Erste stable Version
- Server/Node CPU, RAM, Disk Überwachung
- Server Offline/Crashed, Backup Failed
- Discord, E-Mail, Push Benachrichtigungen
- Admin-Dashboard + Server-Seite