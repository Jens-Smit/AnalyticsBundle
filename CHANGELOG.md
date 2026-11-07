# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert. Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/), die Versionierung an [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Geändert

- Mandantentrennung durchgängig umgesetzt: Alle Statistik-, Zähl- und Löschabfragen verlangen zwingend einen Site-Key. Übergreifende Zugriffe existieren nur noch als ausdrücklich benannte `*All`-Methoden (`countBeforeAll`, `deleteBeforeAll`) für den dokumentierten Betriebsmodell mit `analytics:purge --all-sites`.
- Statistikberechnung in SQL verlagert: `COUNT`, `COUNT(DISTINCT)` und Tages-Gruppierungen laufen jetzt in der Datenbank (plattformbewusst für MySQL/MariaDB, PostgreSQL und SQLite). Der `StatsService` lädt keine PageView-Objektmengen mehr in den Speicher.
- Datenschutz und Datensparsamkeit als Standard: `record_user_identifier` ist standardmäßig deaktiviert, `retention_days` beträgt standardmäßig 90 Tage, Referrer werden auf den Hostnamen reduziert, Pfade ohne Query-Parameter und Fragmente gespeichert.
- IP-Anonymisierung korrigiert: IPv4 wird auf das /24-Präfix gekürzt, IPv6 auf das /64-Präfix (zuvor wurde IPv6 nur unvollständig gekürzt). Der tagesbezogene Hash ist in Code und Dokumentation als Pseudonymisierung eingestuft, nicht als Anonymisierung.
- Löschvorgänge laufen mandantenbezogen in Batches (1000 Datensätze pro Durchlauf); `analytics:purge` bleibt standardmäßig ein Dry-Run und protokolliert keine personenbezogenen Daten.

### Hinzugefügt

- Collect-Endpunkt abgesichert: Request-Größenlimit (`collect_max_request_size`, Standard 4096 Bytes), Content-Type-Prüfung, generische Fehlerantworten ohne interne Details, `Vary: Origin` und `Access-Control-Allow-Origin` nur für explizit erlaubte Origins.
- Rate-Limiting für den Collect-Endpunkt (`collect_rate_limit`, Standard 360 Anfragen je anonymisiertem IP-Präfix und Stunde, 0 deaktiviert das Limit), inklusive `Retry-After`.
- Neue Services: `IpAnonymizer`, `PathNormalizer`, `ReferrerNormalizer`, `CollectRateLimiter`, `SchemaProvider`.
- SQLite-Unterstützung für Schemaerzeugung und Statistikabfragen (unter anderem für lokale Tests).
- Unit-Tests für Datenschutz- und Validierungsfunktionen, Integrationstests für Mandantentrennung, SQL-Aggregation, Batch-Löschung und Purge gegen MySQL 8.0, PostgreSQL 16 und SQLite.
- CI prüft Composer-Validierung, PHP-CS-Fixer, PHPStan (Level 6) und Unit-Tests auf PHP 8.2 bis 8.4 sowie Integrationstests gegen MySQL und PostgreSQL.
- Dokumentation: README mit Trusted-Proxies-Hinweisen, Troubleshooting und Upgrade-Hinweisen, außerdem SECURITY.md und CONTRIBUTING.md.

### Entfernt

- Spalte `user_agent`: User-Agents werden ausschließlich zur Klassifikation gelesen und nicht mehr gespeichert. Da vor 1.0.0 kein stabiles Schema existierte, wird für bestehende Entwicklungsinstallationen ein Neuaufbau der Tabelle empfohlen (siehe README, Upgrade-Hinweise).

## [0.1.0] - Entwicklungsstand

- Erstes Grundgerüst: Entity, Repository, serverseitiges Tracking, Collect- und Stats-Endpunkte, Install- und Purge-Befehl.
