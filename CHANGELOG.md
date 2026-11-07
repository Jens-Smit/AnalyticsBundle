# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert. Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/), die Versionierung an [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Geändert

- (Re-Audit 07.11.2026) Einheitliche Privacy-Policy: Die neue zentrale `PrivacyPolicy` wendet `exclude_bots`, `respect_dnt` und `respect_gpc` identisch für PageViews und Custom Events an; zuvor waren die Regeln bei Events fest aktiviert und ignorierten die Konfiguration. Bei Ablehnung wird weder ein PageView noch ein Event gespeichert.
- (Re-Audit 07.11.2026) Messenger-Dispatch vollständig integriert: Mit `async_recording: true` und installiertem `symfony/messenger` dispatcht der `PageViewRecorder` die normalisierte `RecordPageViewMessage` statt direkt zu speichern; der Handler persistiert sie. Ohne Messenger bleibt der synchrone Betrieb Standard (Fallback), At-least-once-Duplikatverhalten ist dokumentiert.
- (Re-Audit 07.11.2026) Campaign-Allowlist gilt ausschließlich für `utm_source`: `utm_medium` und `utm_campaign` werden unabhängig davon normalisiert und gekürzt. Migrationshinweis: Bestehende Allowlist-Konfigurationen, die versehentlich Medium-/Campaign-Werte gefiltert haben, speichern diese nun.
- (Re-Audit 07.11.2026) CSV-Export schreibt Zeilen als Generator direkt in den Response-Stream; die Exportmenge bleibt über `limit` (max. 100) und `days` (max. `max_report_days`) begrenzt und dokumentiert.
- (Re-Audit 07.11.2026) JavaScript-SDK wertet den Rückgabewert von `sendBeacon()` aus: Nur bei erfolgreicher Einreihung wird kein weiterer Request erzeugt; bei `false` oder fehlendem `sendBeacon()` läuft der `fetch(keepalive)`-Fallback.

- Tagespseudonym auf HMAC-SHA256 umgestellt: `hash_secret` wird als kryptografischer Schlüssel verwendet, nicht an die Eingabe angehängt. `salt` bleibt als veralteter Alias akzeptiert. Bestehende Hashes bleiben historische Pseudonyme; am Umstellungstag können Besuchstage-Metriken einmalig verzerrt sein.
- UTC als durchgehende interne Zeitstrategie: Zeitstempel, Tagesgrenzen des Hash-Tag und Report-Zeiträume laufen über eine zentrale `SystemClock` in UTC, unabhängig von der Server-Zeitzone.
- Konfiguration wird beim Container-Kompilieren validiert: `site_key` auf max. 32 Zeichen und `[a-zA-Z0-9_-]` begrenzt, `hash_secret` muss nicht leer und mindestens 16 Zeichen lang sein.
- Bot-Erkennung von der Browser-Klassifikation getrennt: `BotDetector` entscheidet mit konfigurierbaren Mustern (`bot_patterns`) und Ausnahmen (`bot_exceptions`); `exclude_bots` schließt Bots standardmäßig von der Erfassung aus.
- DNT (`DNT: 1`) und `Sec-GPC: 1` werden standardmäßig respektiert (`respect_dnt`, `respect_gpc`), für Server-Tracking und Collect identisch.
- Pfadausschlüsse gelten auf Segmentgrenzen: `/admin` schließt `/admin` und `/admin/...` aus, nicht mehr `/administrator`.
- `page_view_mode` (`server`, `client`, `manual`) ersetzt `server_side_tracking` (veralteter Alias) und verhindert Doppelzählung bei Kombination von Server-Tracking und Client-Beacon.
- Bot-Regeln gegen False Positives abgesichert: Ausnahmen zuerst geprüft, Tests mit Chrome, Firefox, Safari, Samsung Browser und Headless-Chrome.
- Statistik-API folgt dem einheitlichen Vertrag aus `site`, `period`, `data` und `meta` mit `meta.timezone: UTC`; `days` wird über `max_report_days` begrenzt.
- `analytics:purge` bereinigt PageViews und Events gemeinsam und erhöht nach `--force` die Report-Cache-Generation.

### Hinzugefügt

- (Re-Audit 07.11.2026) Release-Candidate-Validierung: Funktionaler End-to-End-Test über den kompilierten Container der Testanwendung (Erfassung über den Recorder, Reports über den StatsService, Privacy-Ablehnung), ergänzend zur Composer-Fremdprojekt-Installation in der Paketierungs-CI.
- (Re-Audit 07.11.2026) Unit-Tests für `PrivacyPolicy` (alle Options-Kombinationen), Messenger-Dispatch und Synchron-Fallback sowie Allowlist-Semantik je UTM-Feld.

- Traffic-Attribution: `TrafficSourceClassifier` (direct, search, social, internal, referral, campaign), `sourcesForSite()` als SQL-Repository-Methode, `GET /analytics/stats/sources`; Kampagnen-Parameter `utm_source`/`utm_medium`/`utm_campaign` werden normalisiert, gekürzt und optional per `campaign_allowlist` validiert (`utm_term`/`utm_content` werden nicht gespeichert). Neue Spalten `source`, `utm_source`, `utm_medium`, `utm_campaign`.
- Custom Events: Entity `AnalyticsEvent` (Tabelle `jenssmit_analytics_event`), `AnalyticsEventRecorder`, `EventNameValidator`, `EventValueNormalizer` (Werte nur mit `record_event_values`), Endpunkt `POST /analytics/collect/event` mit denselben Schutzschichten, `GET /analytics/stats/events`, Retention über `analytics:purge`.
- Gemeinsamer `CollectRequestGuard` für alle Collect-Endpunkte (Origin, Request-Größe, Content-Type, atomares Rate-Limiting).
- CSV-/JSON-Export aggregierter Reports über `GET /analytics/stats/export` mit Streaming-CSV, mandantenbezogen und rollengeschützt.
- Optionaler Report-Cache (PSR-6, `report_cache_ttl`) mit Site-, Report- und Parameter-spezifischen Schlüsseln und Generations-Invalidierung nach Purge.
- JavaScript-SDK `assets/analytics.js`: `trackPageView()`, `trackEvent()`, `setPage()`, sendBeacon mit fetch-Fallback, keine Cookies/LocalStorage, DNT/GPC-Respekt.
- Optionaler Messenger-Betrieb: `RecordPageViewMessage` und Handler, nur registriert bei installiertem `symfony/messenger` (Composer-Suggest); synchroner Modus bleibt Standard.
- Symfony-Kompatibilität auf 6.4 LTS, 7.x und 8.x erweitert (`^6.4 || ^7.0 || ^8.0`), inklusive CI-Kompatibilitätsjob mit niedrigsten Versionen (Symfony 6.4, PHP 8.2).
- Unit-Tests für BotDetector, TrafficSourceClassifier, CampaignParameterNormalizer, EventNameValidator/EventValueNormalizer, AnalyticsEventRecorder, CollectEventController, Report-Cache und Konfigurationsvalidierung; Integrationstest für Site-scoped Source-Gruppierung.

- Besucherzählung fachlich korrigiert: Das IP-Pseudonym ist tagesbezogen, daher ist eine Unique-Visitor-Zahl über mehrere Tage nicht bestimmbar. `summary` meldet deshalb `visits` (Besuche/Besuchstage) statt `unique_visitors`; die Repository-Methode heißt entsprechend `countVisitsForSite`. Die Tagesreihe bleibt unverändert (je Tag eindeutige Pseudonyme).
- Rate-Limiting ist atomar: Prüfung und Inkrement des Collect-Zählers laufen unter einem exklusiven Lock (`symfony/lock`, standardmäßig `FlockStore`), parallele Anfragen desselben IP-Präfixes können das Limit nicht überholen. Für verteilte Setups sind Cache und Lock-Store gemeinsam zu konfigurieren (siehe README).
- Content-Type-Prüfung vervollständigt: Anfragen ohne Content-Type werden künftig abgelehnt (`415`); erlaubt bleiben `application/json` und `text/plain` mit Parametern (etwa `charset`) in beliebiger Schreibweise.
- Doctrine-Mapping auf Attribute als alleinige Quelle konsolidiert: Die Spaltennamen im Attribut-Mapping entsprechen jetzt dem Schema (`site_key`, `operating_system` usw.), das redundante XML-Mapping unter `config/doctrine/` ist entfernt. `doctrine.orm.auto_mapping: true` genügt in fremden Projekten, weil DoctrineBundle das Mapping in `src/Entity/` findet.
- Bundle-Basisklasse korrigiert (`AbstractBundle` → `Bundle`): `AbstractBundle` nutzt die konventionelle `JenssmitAnalyticsExtension` nicht, weshalb Services und Konfiguration in echten Anwendungen nie geladen wurden. Die `PageView`-Entity ist nicht mehr `final`, damit Doctrine-Lazy-Proxies (ORM 3) erzeugt werden können.

- Mandantentrennung durchgängig umgesetzt: Alle Statistik-, Zähl- und Löschabfragen verlangen zwingend einen Site-Key. Übergreifende Zugriffe existieren nur noch als ausdrücklich benannte `*All`-Methoden (`countBeforeAll`, `deleteBeforeAll`) für den dokumentierten Betriebsmodell mit `analytics:purge --all-sites`.
- Statistikberechnung in SQL verlagert: `COUNT`, `COUNT(DISTINCT)` und Tages-Gruppierungen laufen jetzt in der Datenbank (plattformbewusst für MySQL/MariaDB, PostgreSQL und SQLite). Der `StatsService` lädt keine PageView-Objektmengen mehr in den Speicher.
- Datenschutz und Datensparsamkeit als Standard: `record_user_identifier` ist standardmäßig deaktiviert, `retention_days` beträgt standardmäßig 90 Tage, Referrer werden auf den Hostnamen reduziert, Pfade ohne Query-Parameter und Fragmente gespeichert.
- IP-Anonymisierung korrigiert: IPv4 wird auf das /24-Präfix gekürzt, IPv6 auf das /64-Präfix (zuvor wurde IPv6 nur unvollständig gekürzt). Der tagesbezogene Hash ist in Code und Dokumentation als Pseudonymisierung eingestuft, nicht als Anonymisierung.
- Löschvorgänge laufen mandantenbezogen in Batches (1000 Datensätze pro Durchlauf); `analytics:purge` bleibt standardmäßig ein Dry-Run und protokolliert keine personenbezogenen Daten.

- Symfony-Testanwendung (`tests/Fixtures/Kernel`) mit Funktionstests: Das Doctrine-Mapping wird über eine echte Anwendung mit DoctrineBundle und `auto_mapping` geprüft (Spaltennamen, Tabellenname, Persistenz gegen das generierte Schema).
- CI-Job „Paketierung“: strikte Composer-Validierung und Installation des Bundles aus einem fremden Projekt über ein Path-Repository, inklusive Autoload-Prüfung von Bundle- und Entity-Klassen.
- `.gitattributes` mit `export-ignore` für Test-, CI- und Entwicklungsdateien.
- Zusätzliche Randfalltests: Rate-Limiter (Limit-Erschöpfung, unabhängige IP-Präfixe, Fensterwechsel, Limit 0), Content-Type-Varianten (Charset, Groß-/Kleinschreibung, fehlender/leerer Header), JSON-Payloads, die keine Objekte sind.
- README: Abschnitte zum Request-Größenlimit auf Infrastrukturebene (nginx, Apache, Proxies) und zum Rate-Limiting in verteilten Setups.

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
