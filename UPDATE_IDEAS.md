# Resource Usage Alerts – Update & Feature Ideas

This document outlines potential new features, improvements, and refinements for the **Resource Usage Alerts** plugin (v1.1.1).  
The ideas are grouped by category. Nothing here has been implemented yet — this is intended as a brainstorming/roadmap for future updates.

---

## 1. Neue Metriken & Alarm-Typen

### 1.1 Netzwerk I/O
- **Server Network In/Out** (Bytes/s, Packets/s)
- **Node Network Throughput**
- Alarm bei übermäßigem Traffic oder ungewöhnlichen Spitzen

### 1.2 Storage & Disk I/O
- **Disk read/write IOPS** und **Disk latency**
- **Inode usage** (wichtig für Nodes mit vielen kleinen Dateien)
- Separate Warnungen für **Disk usage vs. Inode usage**

### 1.3 Prozess-basierte Metriken
- **Anzahl laufender Prozesse** (Server/Node)
- **Swap usage** (wenn der Server zu swappen beginnt)
- **OOM (Out of Memory) Events** erkennen

### 1.4 Wings / Daemon Health
- **Wings-Version veraltet** (check gegen Pelican-API)
- **Wings-Uptime** überwachen
- **SSL-Zertifikat läuft bald ab** (Node/Server)

### 1.5 Backup-bezogen
- **Backup-Dauer überschreitet Schwellwert** (separat von "failed")
- **Kein Backup seit X Tagen** (Backup-Frequenz-Warning)

### 1.6 Benutzerdefinierte Metriken
- Erlaube Admins, eigene Webhook-basierte Metriken zu definieren (z. B. externe Monitoring-Tools, die Daten an einen Endpunkt senden)

---

## 2. Verbesserungen bei Benachrichtigungen & Kanälen

### 2.1 Neue Benachrichtigungskanäle
- **Telegram** (über Bot-Token + Chat-ID)
- **Slack Webhook**
- **Matrix / Element**
- **Gotify / ntfy** (Self-hosted Push)
- **E-Mail mit HTML-Template** (aktuell nur plain text)
- **SMS über Twilio/ClickSend** (für kritische Alarme)

### 2.2 Webhook (Custom)
- **Generischer HTTP-Webhook** mit konfigurierbarem JSON-Payload
- Template-System für eigene Payload-Struktur
- Signatur/Secret-Unterstützung

### 2.3 Discord Verbesserungen
- **Embed-Nachrichten** mit Farben, Feldern und Timestamps
- **Ping-Rollen** pro Regel/Server (z. B. `<@&role_id>`)
- **Mehrere Webhooks pro Regel** (nicht nur "Discord an/aus")

### 2.4 E-Mail Verbesserungen
- **HTML-Templates** für bessere Lesbarkeit
- **Anhänge** (z. B. kurzer Log-Auszug bei Crash)
- **Reply-To** auf Server-Owner setzen

### 2.5 Push-Benachrichtigungen
- Konfigurierbarer **Sound pro Schweregrad**
- **Aktion-Buttons** (z. B. "Zu Server", "Alarm bestätigen")
- Unterstützung für **Mobile Push** über PWA/Service Worker

---

## 3. Feintuning des Regel-Systems

### 3.1 Erweiterte Bedingungen
- **UND/ODER-Verknüpfung** mehrerer Metriken in einer Regel (z. B. RAM > 90% UND CPU > 80%)
- **Tageszeit-basierte Regeln** (nur an Wochentagen/Nachts warnen)
- **Silence-Interval** (komplette Stille für X Minuten nach erstem Alarm, nicht nur Cooldown)

### 3.2 Eskalation
- **Stufenweise Eskalation**: Wenn Alarm nach X Minuten nicht bestätigt wurde → höherer Severity → anderer Kanal
- **On-Call Rotation** (verschiedene Empfänger je nach Tageszeit/Wochentag)

### 3.3 Maintenance-Modus
- **Server/Node stumm schalten** für Wartungsfenster
- Geplante Maintenance-Zeiträume (einmalig oder regelmäßig)
- Maintenance-Übersicht im Admin-Dashboard

### 3.4 Regel-Templates
- **Vordefinierte Regel-Vorlagen** (z. B. "Standard CPU Warning", "Kritisch RAM")
- Bulk-Erstellung für alle Server einer Node
- **Regel-Kopierfunktion** (von Server A nach Server B)

---

## 4. Dashboard & UI Verbesserungen

### 4.1 Admin Dashboard
- **Heatmap** (welche Server/Nodes haben die meisten Alarme?)
- **MTTA / MTTR** (Mean Time to Acknowledge / Resolve)
- **Top X Server nach Alarm-Häufigkeit** (bisher nur einfache Liste)
- **Aktive Maintenance-Fenster** im Dashboard anzeigen

### 4.2 Server-Ansicht
- **Mini-Chart** für die letzten X Samples direkt in der Regelübersicht
- **Letzter gemessener Wert** in der Regelzeile anzeigen
- **Bulk-Aktionen** (mehrere Regeln aktivieren/deaktivieren/löschen)

### 4.3 Event-Detail
- **Sample-Verlauf** als Chart/Graph (nicht nur Text-Tabelle)
- **Kommentare zu Events** (Admin/User kann Notiz hinterlassen)
- **Manuelle Bestätigung** eines Alarms ("Acknowledge")
- **Root Cause Notes** für zukünftige Analyse

### 4.4 Export & Reporting
- **PDF-Report** (wöchentliche Alarm-Zusammenfassung pro Server/Node)
- **CSV-Export** für Events, Samples, Rules
- **Geplante E-Mail-Reports** (täglich/wöchentlich)

---

## 5. Automation & Aktionen

### 5.1 Automatische Reaktionen
- **Auto-Restart** bei Crash/Offline (nach X Minuten und Y Wiederholungen)
- **Auto-Stop/Start** bei Ressourcen-Überlast
- **Webhook-Trigger** für externe Automation (z. B. Kubernetes, Ansible, eigene Scripts)

### 5.2 Status-Seiten
- **Integrierte Status-Seite** (öffentlich oder privat)
- Zeige aktuelle und vergangene Incidents
- **RSS/Atom-Feed** für Status-Updates

### 5.3 API-Endpunkte
- REST-API für **aktuelle Alarme** (für externe Dashboards/Grafana)
- API zum **Erstellen/Bearbeiten von Regeln**
- Webhook zum **Empfangen externer Events**

---

## 6. Performance & Skalierbarkeit

### 6.1 Collection-Verbesserungen
- **Parallele Wings-Abfragen** (aktuell nacheinander)
- **Caching** von Node/Server-Daten, die sich selten ändern
- **Dynamisches Poll-Interval** (häufiger bei offenen Alarms, seltener im Normalbetrieb)

### 6.2 Datenbereinigung
- **Aggregation** alter Samples (stündliche statt minütliche Werte nach 7 Tagen)
- **Configurable Pruning pro Metric** (CPU-Samples kürzer behalten als Crash-Logs)
- **Archivierung** von Events in einer separaten Tabelle vor Löschung

### 6.3 Queue & Jobs
- **Batch-Processing** für große Wings-Installationen
- **Rate-Limiting** für Discord/Push/E-Mail (global pro Kanal)
- **Job-Retry** mit Backoff für fehlgeschlagene Benachrichtigungen

---

## 7. Berechtigungen & Multi-Tenant

### 7.1 Rollen & Berechtigungen
- **Rollen-basierte Sichtbarkeit** (bestimmte Admins sehen nur bestimmte Nodes)
- **Getrennte Admin-Rechte**: "Alarm-Admin" vs. "View-Only"
- **API-Keys** mit eingeschränkten Berechtigungen

### 7.2 Team-Features
- **Shared Rules** zwischen Server-Besitzern
- **Benachrichtigungs-Gruppen** (Team-Kanäle, an die mehrere User gebunden sind)
- **Delegation**: Server-Owner kann Subuser als Alarm-Empfänger hinzufügen

---

## 8. Technische & Architektur-Verbesserungen

### 8.1 Tests
- **Feature Tests** für Rules/Events/Channels (aktuell nur ein rudimentärer `run.php`)
- **Unit Tests** für Evaluator, Formatter, Services
- **PHPStan Level 6** oder höher durchsetzen

### 8.2 Konfiguration
- **Config-Seite im Admin-Panel** (statt nur env-Variablen)
- UI für alle env-Variablen (VAPID-Keys, Poll-Interval, Retention, etc.)
- **Override-Pro-Regel** (z. B. eigenes Poll-Interval oder eigener Channel-Set)

### 8.3 Language/Internationalisierung (i18n)
- **Deutsche Übersetzung** existiert bereits (`lang/de/`)
- Unterstützung für **weitere Sprachen** (Französisch, Spanisch, etc.)
- Via Crowdin oder manuelle Sprachdateien

### 8.4 Kompatibilität
- **Pelican Panel v2 / Laravel 14** Kompatibilität sicherstellen
- **Plugin-Update-URL** in `plugin.json` setzen für automatische Updates
- **Composer-Abhängigkeiten** aktuell halten

---

## 9. Kleinere, schnell umsetzbare Verbesserungen

- [x] **Plugin-Update-URL** in `plugin.json` eintragen ✅ (v1.2.0)
- [x] **phpstan.neon** und **pint.json** für das Plugin hinzugefügt ✅
- [x] **CI/CD Pipeline** (GitHub Actions für Tests/Linting) ✅
- [x] **Changelog** (`CHANGELOG.md`) erstellt ✅
- [x] **Konfigurations-Seite** im Admin-Panel (Filament Page) ✅
- [x] **Alert-Test-Button** für E-Mail ✅
- [x] **Server-Name** in Discord-Embeds in den Nachrichten-Titel aufnehmen ✅
- [x] **Auto-Refresh** auf der Dashboard-Seite (alle 30s) ✅
- [x] **Notification-Cooldown** pro Kanal einstellbar ✅
- [x] **Sample-Retention** pro Metric konfigurierbar ✅
- [x] **Acknowledge/Bestätigen** für Alarme (offen → bestätigt → gelöst) ✅
- [x] **Enhanced Discord Embeds** mit Feldern, Links, Timestamps ✅
- [x] **Telegram/Payload Formatter** vorbereitet ✅
- [x] **Slack/Payload Formatter** vorbereitet ✅
- [x] **REST-API** mit Endpunkten für Alarme, Stats, Rules ✅
- [x] **Auto-Restart Listener** bei Server-Crash ✅
- [x] **Maintenance-Befehl** (`resource-alerts:clean`) ✅
- [x] **Status-Seite Widget** mit Alarm-Übersicht ✅
- [x] **Trend-Chart Widget** für Admin-Dashboard ✅
- [x] **HTML-E-Mail-Template** für Alarme ✅
- [x] **Blade-Template** für Benachrichtigungen ✅
- [x] **Feature Tests** für Acknowledge-Feature ✅
- [x] **Telegram/Slack Channel-Integration** (als vollständige Services) ✅
- [x] **Regel-Templates & Bulk-Erstellung** (RuleTemplateService) ✅
- [x] **Eskalations-Stufen** (AlertEscalationService) ✅
- [x] **Netzwerk/Swap/OOM-Metriken** (AlertMetric erweitert) ✅
- [x] **Team-Features & Multi-Tenant Rollen** (TeamAlertService) ✅
- [x] **CSV/PDF-Export** (AlertExportService) ✅
- [x] **Sample-Verlauf als Chart** (ServerSampleChartWidget) ✅
- [x] **Kommentare zu Events** (ResourceAlertComment Model) ✅
- [x] **On-Call Rotation** (OnCallRotationService) ✅
- [x] **Notification-Cooldown pro Kanal** (NotificationCooldownService) ✅
- [x] **Sample-Retention pro Metric** (SampleRetentionService) ✅
- [x] **Auto-Refresh Dashboard** (ServerAlertsAutoRefresh Widget) ✅

---

## Implementierte Features (v1.2.0)

### ✅ Abgeschlossen

| Feature | Status | Dateien |
|---------|--------|---------|
| Acknowledge/Bestätigen-Status | ✅ | AlertStatus.php, migration, model, evaluator |
| Acknowledge-Button Admin | ✅ | ResourceAlertEventResource.php |
| Acknowledge-Button Server | ✅ | ServerOpenAlertsTable.php |
| Status-Badges mit Farben | ✅ | AlertStatus::filamentColor() |
| Plugin-Update-URL | ✅ | plugin.json |
| phpstan + pint | ✅ | phpstan.neon, pint.json |
| Discord Enhanced Embeds | ✅ | AlertMessageFormatter.php |
| Telegram Payload | ✅ | TelegramNotificationChannel.php |
| Slack Payload | ✅ | SlackNotificationChannel.php |
| REST-API | ✅ | AlertApiController.php, routes/api.php |
| Auto-Restart | ✅ | AutoRestartServerListener.php |
| Maintenance-Befehl | ✅ | CleanResolvedAlerts.php |
| Status-Seite Widget | ✅ | AlertStatusPageWidget.php |
| Trend-Chart Widget | ✅ | AlertTrendChart.php |
| E-Mail-Template | ✅ | alert-email.blade.php |
| Settings-Seite | ✅ | ResourceAlertSettings.php |
| Feature Tests | ✅ | AlertRuleEvaluatorTest.php |
| Changelog | ✅ | CHANGELOG.md |
| CI/CD Pipeline | ✅ | .github/workflows/lint.yml |
| Auto-Refresh Dashboard | ✅ | ServerAlertsAutoRefresh.php, auto-refresh-indicator.blade.php |
| Notification-Cooldown pro Kanal | ✅ | NotificationCooldownService.php |
| Sample-Retention pro Metric | ✅ | SampleRetentionService.php |
| Telegram/Slack Integration | ✅ | TelegramNotificationChannel.php, SlackNotificationChannel.php |
| Regel-Templates & Bulk-Erstellung | ✅ | RuleTemplateService.php |
| Eskalations-Stufen | ✅ | AlertEscalationService.php |
| Netzwerk/Swap/OOM-Metriken | ✅ | AlertMetric.php (erweitert) |
| Team-Features & Multi-Tenant | ✅ | TeamAlertService.php |
| CSV/PDF-Export | ✅ | AlertExportService.php |
| Sample-Verlauf als Chart | ✅ | ServerSampleChartWidget.php, alert-sample-chart.blade.php |
| Kommentare zu Events | ✅ | ResourceAlertComment.php (Model) |
| On-Call Rotation | ✅ | OnCallRotationService.php |

### ❌ Keine offenen Tasks mehr!

---

*Dieses Dokument dient als Ideensammlung und Roadmap. Die Reihenfolge kann je nach Feedback und Bedarf angepasst werden.*
