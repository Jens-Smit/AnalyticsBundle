# AnalyticsBundle

Cookiefreies, datensparsames Web-Analytics für Symfony. Das Bundle erfasst Seitenaufrufe serverseitig oder über einen abgesicherten Collect-Endpunkt, trennt mehrere Websites strikt nach Mandanten und aggregiert die Statistik direkt in der Datenbank.

Wichtig: Dieses Bundle erfasst bewusst weniger Daten als klassische Analysewerkzeuge. Es speichert keine Cookies, keine IP-Adressen, keine User-Agent-Zeichenketten und keine vollständigen Referrer-URLs. Datenschutzrechtliche Bewertungen hängen dennoch vom konkreten Einsatz, der Konfiguration und den Verantwortlichkeiten des Betreibers ab.

## Funktionen

- Mandantentrennung: Jede Installation wertet genau einen `site_key` aus; Abfragen sind zwingend mandantenbezogen.
- Datensparsame Standardkonfiguration: keine Cookies, keine Login-Identifier, keine rohen IP-Adressen, keine User-Agent-Speicherung.
- IP-Kürzung: IPv4 auf /24-Netzpräfix, IPv6 auf /64-Netzpräfix, zusätzlich tagesbezogenes Pseudonym mit geheimem Salt.
- Erfassung wahlweise serverseitig über `kernel.terminate` oder über den Collect-Endpunkt (Beacon/fetch).
- Abgesicherter Collect-Endpunkt: Origin-Allowlist, Request-Größenlimit, Content-Type-Prüfung, Rate-Limiting, strikte Pfad- und Referrer-Validierung.
- SQL-Aggregation: Zählungen und Gruppierungen laufen in der Datenbank, nicht in PHP.
- Auswertungs-API mit Zusammenfassung, Zeitreihe, Top-Seiten, Referrern, Browsern und Geräten.
- Aufbewahrungsfrist mit Dry-Run-Purge und Batch-Löschung.
- Unterstützte Datenbanken: MySQL 8, MariaDB, PostgreSQL und SQLite.

## Anforderungen

- PHP 8.2 oder höher
- Symfony 7.x
- Doctrine ORM 3 mit DoctrineBundle
- SecurityBundle (für die rollenbasierte Statistik-API)

## Installation

```bash
composer require jenssmit/analytics-bundle
```

Falls das Paket nicht auf Packagist veröffentlicht ist, alternativ per VCS-Repository in der `composer.json` der Anwendung:

```bash
composer config repositories.analytics-bundle vcs https://github.com/Jens-Smit/AnalyticsBundle
composer require jenssmit/analytics-bundle:@dev
```

Bundle registrieren in `config/bundles.php`:

```php
return [
    // ...
    Jenssmit\AnalyticsBundle\JenssmitAnalyticsBundle::class => ['all' => true],
];
```

## Konfiguration

```yaml
# config/packages/jenssmit_analytics.yaml
jenssmit_analytics:
    # Datenpartition je Website/Mandant. Pflicht, aber kein
    # Authentifizierungsgeheimnis.
    site_key: '%env(ANALYTICS_SITE_KEY)%'

    # Geheimer Salt für das tagesbezogene IP-Pseudonym.
    # Aus Umgebungsvariable laden, niemals committen.
    salt: '%env(ANALYTICS_SALT)%'

    # Aufbewahrungsfrist in Tagen (Standard: 90).
    retention_days: 90

    # Login-Identifier speichern? Standard: false. Ein Identifier
    # kann personenbezogen sein (z. B. E-Mail-Adresse).
    record_user_identifier: false

    # Serverseitige Erfassung über kernel.terminate.
    server_side_tracking: true

    # Nur erfolgreiche Anfragen (2xx/3xx) erfassen.
    track_only_successful: true

    # Pfadpräfixe, die nicht erfasst werden.
    excluded_paths: ['/analytics', '/health', '/_profiler']

    # Explizit erlaubte Origins für den Collect-Endpunkt.
    # Leer bedeutet: nur gleiche Herkunft. '*' deaktiviert die
    # Origin-Prüfung und wird nicht empfohlen.
    allowed_origins: []

    # Maximale Collect-Anfragen je anonymisiertem IP-Präfix und Stunde.
    # 0 deaktiviert das Limit.
    collect_rate_limit: 360

    # Maximale Größe des Collect-Request-Bodies in Bytes.
    collect_max_request_size: 4096

    # Rolle, die die Statistik-Endpunkte aufrufen darf.
    stats_role: 'ROLE_ADMIN'

    # Routenpräfix aller Bundle-Routen.
    route_prefix: '/analytics'
```

Die zugehörigen Umgebungsvariablen:

```bash
ANALYTICS_SITE_KEY=restaurant_001
ANALYTICS_SALT=langes-zufaelliges-geheimnis
```

## Datenbank

Das Bundle bringt kein Doctrine-Migrationssystem mit, sondern einen idempotenten Installationsbefehl. Bestehende Tabellen und Daten bleiben unberührt; der Befehl löscht oder ändert niemals Spalten.

```bash
php bin/console analytics:install
```

Unterstützt werden MySQL/MariaDB, PostgreSQL und SQLite. Bei einer nicht unterstützten Plattform bricht der Befehl mit einer klaren Fehlermeldung ab, ohne etwas zu verändern.

Wer Doctrine-Migrations nutzt, kann die Tabelle alternativ über `doctrine:migrations:diff` in das eigene Migrationsystem übernehmen; dazu muss das Bundle-Mapping registriert sein (`doctrine.orm.auto_mapping: true` genügt in der Regel, da das Bundle sein Mapping unter `config/doctrine/` mitbringt).

## Erfassung

### Variante 1: Serverseitig (Standard)

Bei aktiviertem `server_side_tracking` erfasst das Bundle Hauptanfragen automatisch nach dem Senden der Antwort (`kernel.terminate`). Die Antwortzeit des Besuchers wird dadurch nicht beeinflusst. Erfasst werden nur sichere HTTP-Methoden und, sofern konfiguriert, nur erfolgreiche Statuscodes.

### Variante 2: Collect-Endpunkt

Für Client-seitige Erfassung (etwa für SPAs oder statische Seiten):

```javascript
navigator.sendBeacon(
    '/analytics/collect',
    JSON.stringify({ path: location.pathname, referrer: document.referrer })
);
```

Der Endpunkt akzeptiert `POST` mit `application/json` oder `text/plain` und antwortet mit `204 No Content`. Schutzschichten:

1. Origin-Prüfung: Unbekannte Origins werden mit `403` abgelehnt. Antworten tragen `Vary: Origin`; `Access-Control-Allow-Origin` wird nur für ausdrücklich erlaubte Origins gesetzt.
2. Request-Größenlimit (Standard 4096 Bytes, sonst `413`).
3. Content-Type-Prüfung (sonst `415`).
4. Rate-Limiting je anonymisiertem IP-Präfix und Stunde (Standard 360, sonst `429` mit `Retry-After`).
5. Strikte Validierung von Pfad und Referrer, sonst `400`.

Sicherheitshinweis: Der Origin-Header ist kein Authentifizierungsmechanismus. Nicht-browserbasierte Clients können jeden Headerwert setzen. CORS schützt Browser, nicht vor automatisiertem Missbrauch; dafür sind Rate-Limiting und Validierung zuständig.

### Trusted Proxies

Die Client-IP wird über `$request->getClientIp()` ermittelt. Damit hinter einem Reverse-Proxy oder Load Balancer die echte Client-IP verwendet wird, muss die Proxy-Kette in der Anwendung konfiguriert werden:

```yaml
# config/packages/framework.yaml
framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%' # z. B. '192.0.2.10,10.0.0.0/8'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto']
```

Ohne diese Konfiguration darf die Anwendung keine Forwarded-Header blind übernehmen, sonst könnte ein Angreifer die Rate-Limit-Buckets durch gefälschte Header leerräumen.

## Statistik-API

Alle Endpunkte erfordern die konfigurierte Rolle (`stats_role`, Standard `ROLE_ADMIN`) und liefern ausschließlich Daten des konfigurierten Mandanten.

| Endpunkt | Parameter | Inhalt |
| --- | --- | --- |
| `GET /analytics/stats/summary` | `days` | Aufrufe, Besucher, Aufrufe pro Tag |
| `GET /analytics/stats/timeseries` | `days` | Lückenlose Tagesreihe |
| `GET /analytics/stats/top-pages` | `limit`, `days` | Häufigste Pfade |
| `GET /analytics/stats/referrers` | `limit`, `days` | Referrer-Hosts, `Direkt` ohne Referrer |
| `GET /analytics/stats/browsers` | `limit`, `days` | Browser-Klassifikation |
| `GET /analytics/stats/devices` | `limit`, `days` | Gerät-Klassifikation |

Beispielantwort für `summary`:

```json
{
    "site": "restaurant_001",
    "period": { "from": "2026-10-09", "days": 30 },
    "views": 1234,
    "unique_visitors": 421,
    "views_per_day": 41.13
}
```

## Aufbewahrung und Löschung

```bash
# Dry-Run (Standard): zählt nur, was gelöscht würde.
php bin/console analytics:purge

# Tatsächlich löschen, Standardaufbewahrungsfrist.
php bin/console analytics:purge --force

# Andere Frist, nur dieser Mandant.
php bin/console analytics:purge --days=30 --force

# Ausdrücklich alle Mandanten (mehrere Installationen in einer Tabelle).
php bin/console analytics:purge --force --all-sites
```

Die Löschung erfolgt in Batches (1000 Datensätze pro Durchlauf), damit große Tabellen keine blockierenden Langzeit-Transaktionen erzeugen. Standardmäßig betrifft sie nur den konfigurierten Mandanten; `--all-sites` ist ein bewusster, expliziter Eingriff.

## Datenschutz und Grenzen der Anonymisierung

Was gespeichert wird und was nicht:

| Merkmal | Verhalten |
| --- | --- |
| Cookies | Werden nicht gesetzt. |
| IP-Adresse (roh) | Wird nicht gespeichert. |
| IP-Adresse (gekürzt) | IPv4 auf /24, IPv6 auf /64 gekürzt und nur zur Pseudonymisierung verwendet. |
| IP-Pseudonym | SHA-256 aus gekürztem Präfix, Kalendertag und Salt. Gültig für höchstens einen Tag. |
| User-Agent | Wird nur zur Klassifikation (Browser, System, Gerät) gelesen und nicht gespeichert. |
| Referrer | Nur der Hostname, ohne Query-Parameter und Fragmente. |
| Pfad | Ohne Query-Parameter und Fragmente (schützt vor Tokens in URLs). |
| Login-Identifier | Standardmäßig deaktiviert. |

Einordnung des IP-Hashes: Der Hash ist ein Pseudonym, keine anonyme Kennung. Er ermöglicht Näherungen für wiederkehrende Besucher innerhalb eines Tages. Mit Kenntnis des Salts und des Präfixes ließe er sich für begrenzte Zeiträume rechnen. Betreiber, die stärkere Anonymisierung benötigen, können die Besucherzählung nicht verwenden und sollten nur Aufrufzahlen auswerten.

Wird `record_user_identifier` aktiviert, können personenbezogene Daten (z. B. E-Mail-Adressen) in die Datenbank gelangen. Diese Funktion erfordert eine datenschutzrechtliche Prüfung durch den Betreiber; als bloßer Bezeichner ist ein Login-Identifier keine harmlose Kennung.

Das Bundle trifft keine Aussage wie „100 % DSGVO-konform“. Eine datenschutzrechtliche Bewertung hängt vom Einsatz, der Konfiguration und den Verantwortlichkeiten des Betreibers ab. Hinweise zur Verarbeitung sollte der Betreiber in seiner Datenschutzerklärung dokumentieren.

## Troubleshooting

- Keine Daten in der Statistik: Prüfen, ob die Tabelle existiert (`analytics:install`), ob der Pfad nicht in `excluded_paths` steht und ob `server_side_tracking` oder der Collect-Aufruf aktiv ist.
- Collect liefert `403`: Der Origin-Header des Browsers steht nicht in `allowed_origins` oder weicht von Schema und Host der Anwendung ab.
- Collect liefert `429`: Das Stundenlimit für das anonymisierte IP-Präfix ist erreicht; der `Retry-After`-Header nennt die Wartezeit. Limit bei Bedarf über `collect_rate_limit` anpassen.
- Besucherzahlen hinter Proxy falsch oder Rate-Limit zu streng: `trusted_proxies` konfigurieren, sonst fließen Proxy-IPs in Pseudonym und Zähler ein.
- Falsche Datenbank bei mehreren Verbindungen: Der Befehl nutzt die Standardverbindung; bei mehreren Verbindungen ist das Bundle-Mapping der gewünschten Verbindung zuzuordnen.
- Schema-Fehler nach Bundle-Update: Siehe Upgrade-Hinweise; das Bundle verändert bestehende Tabellen nicht automatisch.

## Upgrade-Hinweise

Von Versionen vor 1.0.0 (Entwicklungsstand):

1. Die Spalte `user_agent` wurde entfernt, der Referrer wird auf den Hostnamen reduziert, `record_user_identifier` ist standardmäßig deaktiviert und `retention_days` beträgt standardmäßig 90 Tage.
2. Da vor 1.0.0 kein stabiles Schema existierte, ist die einfachste Migration: Tabelle löschen und neu anlegen (`DROP TABLE jenssmit_page_view;` danach `php bin/console analytics:install`). Bestehende v1.0.0-Tabellen werden von Updates nicht angetastet.
3. Die Konfigurationsschlüssel `collect_rate_limit` und `collect_max_request_size` sind neu und müssen nicht gesetzt werden.

## Entwicklung

```bash
composer install
composer cs-check       # Code-Style
composer cs-fix         # Code-Style automatisch korrigieren
composer stan           # PHPStan (Level 6)
composer test           # Unit-Tests
ANALYTICS_DATABASE_URL='sqlite:///:memory:' vendor/bin/phpunit --testsuite integration
```

Die CI prüft Composer-Validierung, Code-Style, statische Analyse und Unit-Tests auf PHP 8.2 bis 8.4 sowie Integrationstests gegen MySQL 8.0 und PostgreSQL 16.

## Lizenz

MIT. Siehe [LICENSE](LICENSE).
