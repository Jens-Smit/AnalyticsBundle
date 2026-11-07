# AnalyticsBundle

**Privacy-first, self-hosted web analytics for Symfony.**

Datensparsame, selbst gehostete Webanalyse direkt in Symfony: serverseitige Erfassung, konsequente Site-Isolation und SQL-basierte Statistiken über eine API, die sich in bestehende Anwendungen integrieren lässt. Keine Cookies, keine rohen IP-Adressen, keine vollständigen User-Agents, keine externen Analytics-Dienste.

Das Bundle erfasst Seitenaufrufe serverseitig oder über einen abgesicherten Collect-Endpunkt, trennt mehrere Websites strikt nach Mandanten und aggregiert die Statistik direkt in der Datenbank.

Wichtig: Dieses Bundle erfasst bewusst weniger Daten als klassische Analysewerkzeuge. Es speichert keine Cookies, keine IP-Adressen, keine User-Agent-Zeichenketten und keine vollständigen Referrer-URLs. Datenschutzrechtliche Bewertungen hängen dennoch vom konkreten Einsatz, der Konfiguration und den Verantwortlichkeiten des Betreibers ab.

## Funktionen

- Mandantentrennung: Jede Installation wertet genau einen `site_key` aus; Abfragen sind zwingend mandantenbezogen.
- Datensparsame Standardkonfiguration: keine Cookies, keine Login-Identifier, keine rohen IP-Adressen, keine User-Agent-Speicherung.
- IP-Kürzung: IPv4 auf /24-Netzpräfix, IPv6 auf /64-Netzpräfix, zusätzlich tagesbezogenes Pseudonym über HMAC-SHA256 mit geheimem Hash-Secret.
- Erfassung wahlweise serverseitig über `kernel.terminate` oder über den Collect-Endpunkt (Beacon/fetch); `page_view_mode` verhindert Doppelzählung.
- Bot-Ausschluss mit konfigurierbaren Mustern und Ausnahmen; DNT und Sec-GPC werden standardmäßig respektiert.
- Traffic-Attribution: Quellen (`direct`, `search`, `social`, `internal`, `referral`, `campaign`) und normalisierte UTM-Parameter.
- Custom Events über einen gleich geschützten Endpunkt, mit strenger Namensvalidierung.
- Abgesicherter Collect-Endpunkt: Origin-Allowlist, Request-Größenlimit, Content-Type-Prüfung, atomares Rate-Limiting, strikte Pfad- und Referrer-Validierung.
- SQL-Aggregation: Zählungen und Gruppierungen laufen in der Datenbank, nicht in PHP.
- Einheitlicher API-Vertrag (`site`, `period`, `data`, `meta`), optionaler Report-Cache, CSV-/JSON-Export.
- Aufbewahrungsfrist mit Dry-Run-Purge und Batch-Löschung, für PageViews und Events.
- Schlankes JavaScript-SDK ohne Cookies, ohne LocalStorage, mit sendBeacon und fetch-Fallback.
- Optionaler Messenger-Betrieb über `symfony/messenger`; synchroner Modus bleibt Standard.
- Unterstützte Datenbanken: MySQL 8, MariaDB, PostgreSQL und SQLite.

## Anforderungen

- PHP 8.2 oder höher
- Symfony 6.4, 7.x oder 8.x
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

    # Geheimes Hash-Secret (HMAC-SHA256, mindestens 16 Zeichen) für das
    # tagesbezogene IP-Pseudonym. Aus Umgebungsvariable laden, niemals
    # committen und niemals loggen. Der alte Name "salt" ist ein
    # veralteter Alias.
    hash_secret: '%env(ANALYTICS_HASH_SECRET)%'

    # Aufbewahrungsfrist in Tagen (Standard: 90).
    retention_days: 90

    # Login-Identifier speichern? Standard: false. Ein Identifier
    # kann personenbezogen sein (z. B. E-Mail-Adresse).
    record_user_identifier: false

    # Erfassungsmodus: server (Standard, kernel.terminate), client
    # (JavaScript-SDK/Beacon) oder manual (explizite record()-Aufrufe).
    # Verhindert Doppelzählung, wenn Server-Tracking und Beacon
    # kombiniert werden. Der alte Name "server_side_tracking: false"
    # entspricht page_view_mode 'manual'.
    page_view_mode: 'server'

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

    # Bots und Crawler nicht erfassen (Standard: true).
    exclude_bots: true

    # DNT: 1 und Sec-GPC: 1 respektieren (Standard: true).
    respect_dnt: true
    respect_gpc: true

    # Zusätzliche Bot-Muster (Regex ohne Delimiter) und Ausnahmen für
    # False Positives. Ausnahmen werden zuerst geprüft.
    bot_patterns: []
    bot_exceptions: []

    # Optional: erlaubte utm_source-Werte. Leer bedeutet keine
    # Allowlist-Prüfung; Werte werden immer normalisiert und gekürzt.
    campaign_allowlist: []

    # Event-Werte speichern? Standard: false. Aktiviert bedeutet:
    # normalisierte, gekürzte Werte (max. 100 Zeichen).
    record_event_values: false

    # Optionaler PSR-6-Report-Cache in Sekunden. 0 deaktiviert den
    # Cache (Standard). Nach einem Purge wird die Generation erhöht.
    report_cache_ttl: 0

    # Maximaler Report-Zeitraum in Tagen (Standard: 366).
    max_report_days: 366
```

Die zugehörigen Umgebungsvariablen:

```bash
ANALYTICS_SITE_KEY=restaurant_001
ANALYTICS_HASH_SECRET=langes-zufaelliges-geheimnis-mindestens-16-zeichen
```

## Datenbank

Das Bundle bringt kein Doctrine-Migrationssystem mit, sondern einen idempotenten Installationsbefehl. Bestehende Tabellen und Daten bleiben unberührt; der Befehl löscht oder ändert niemals Spalten.

```bash
php bin/console analytics:install
```

Unterstützt werden MySQL/MariaDB, PostgreSQL und SQLite. Bei einer nicht unterstützten Plattform bricht der Befehl mit einer klaren Fehlermeldung ab, ohne etwas zu verändern.

Wer Doctrine-Migrations nutzt, kann die Tabelle alternativ über `doctrine:migrations:diff` in das eigene Migrationsystem übernehmen; dazu muss das Bundle-Mapping registriert sein (`doctrine.orm.auto_mapping: true` genügt, da das Mapping als Attribute an der Entity in `src/Entity/` liegt).

## Erfassung

### Variante 1: Serverseitig (Standard)

Im Standard-`page_view_mode: 'server'` erfasst das Bundle Hauptanfragen automatisch nach dem Senden der Antwort (`kernel.terminate`). Die Antwortzeit des Besuchers wird dadurch nicht beeinflusst. Erfasst werden nur sichere HTTP-Methoden und, sofern konfiguriert, nur erfolgreiche Statuscodes.

### Variante 2: Collect-Endpunkt

Für Client-seitige Erfassung (etwa für SPAs oder statische Seiten):

```javascript
navigator.sendBeacon(
    '/analytics/collect',
    JSON.stringify({ path: location.pathname, referrer: document.referrer })
);
```

Der Endpunkt akzeptiert `POST` mit `application/json` oder `text/plain` (Parameter wie `charset` sind erlaubt) und antwortet mit `204 No Content`. Anfragen ohne Content-Type werden abgelehnt. Schutzschichten:

1. Origin-Prüfung: Unbekannte Origins werden mit `403` abgelehnt. Antworten tragen `Vary: Origin`; `Access-Control-Allow-Origin` wird nur für ausdrücklich erlaubte Origins gesetzt.
2. Request-Größenlimit (Standard 4096 Bytes, sonst `413`).
3. Content-Type-Prüfung: nur `application/json` oder `text/plain`, fehlender oder unbekannter Content-Type wird abgelehnt (sonst `415`).
4. Rate-Limiting je anonymisiertem IP-Präfix und Stunde (Standard 360, sonst `429` mit `Retry-After`). Prüfung und Inkrement sind atomar: sie laufen unter einem exklusiven Lock, parallele Anfragen desselben IP-Präfixes können das Limit nicht überholen.
5. Strikte Validierung von Pfad und Referrer, sonst `400`.

Sicherheitshinweis: Der Origin-Header ist kein Authentifizierungsmechanismus. Nicht-browserbasierte Clients können jeden Headerwert setzen. CORS schützt Browser, nicht vor automatisiertem Missbrauch; dafür sind Rate-Limiting und Validierung zuständig.

#### Request-Größenlimit auf Infrastrukturebene

Das applikationsseitige Limit (`collect_max_request_size`) greift erst, wenn die Anfrage PHP vollständig erreicht hat. Ein Angreifer kann den Webserver trotzdem mit großen Bodys beschäftigen. Betreiber sollten deshalb zusätzlich ein Limit auf Infrastrukturebene setzen, zum Beispiel:

- nginx: `client_max_body_size 8k;` (Server- oder Location-Block des Collect-Endpunkts)
- Apache: `LimitRequestBody 8192`
- Reverse Proxies und Load Balancer (etwa Cloudflare, AWS ALB) bieten vergleichbare Grenzen

Das Infrastruktur-Limit sollte großzügiger als das applikationsseitige Limit gewählt werden, damit legitime Anfragen nicht an der Infrastruktur scheitern, während übermäßig große Bodys gar nicht erst die Anwendung erreichen.

#### Rate-Limiting in verteilten Setups

Zähler liegt im Anwendungscache (`cache.app`), die Sperre standardmäßig im Dateisystem des Hosts (`FlockStore`). In verteilten Setups (mehrere Webserver) müssen Cache und Lock-Store gemeinsam genutzt werden, sonst gilt das Limit je Host. Dazu die Services `jenssmit_analytics.lock.factory` beziehungsweise `jenssmit_analytics.lock.store` mit einem gemeinsamen Store (etwa Redis) dekorieren oder ersetzen.

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
| `GET /analytics/stats/summary` | `days` | Aufrufe, Besuche (Besuchstage), Aufrufe pro Tag |
| `GET /analytics/stats/timeseries` | `days` | Lückenlose Tagesreihe |
| `GET /analytics/stats/top-pages` | `limit`, `days` | Häufigste Pfade |
| `GET /analytics/stats/referrers` | `limit`, `days` | Referrer-Hosts, `Direkt` ohne Referrer |
| `GET /analytics/stats/browsers` | `limit`, `days` | Browser-Klassifikation |
| `GET /analytics/stats/devices` | `limit`, `days` | Gerät-Klassifikation |
| `GET /analytics/stats/sources` | `limit`, `days` | Traffic-Quellen (direct, search, social, internal, referral, campaign) |
| `GET /analytics/stats/events` | `limit`, `days` | Häufigste Custom Events |
| `GET /analytics/stats/export` | `report`, `format`, `days`, `limit` | CSV- oder JSON-Export aggregierter Reports |

Alle Antworten folgen dem einheitlichen Vertrag aus `site`, `period`, `data` und `meta`. `meta.timezone` ist immer `UTC`; `days` wird auf `max_report_days` begrenzt. Beispielantwort für `summary`:

```json
{
    "site": "restaurant_001",
    "period": { "from": "2026-10-09", "days": 30 },
    "data": {
        "views": 1234,
        "visits": 421,
        "views_per_day": 41.13
    },
    "meta": {
        "report": "summary",
        "timezone": "UTC",
        "visits_definition": "Besuchstage: Da das IP-Pseudonym tagesbezogen ist, zählt jeder Kalendertag mit Pseudonym als ein Besuch."
    }
}
```

Der Export liefert ausschließlich aggregierte Daten, mandantenbezogen und mit denselben Rechten wie die Statistik-Endpunkte; CSV wird als Stream ausgeliefert:

```
GET /analytics/stats/export?report=top-pages&format=csv&days=30&limit=100
```

### Report-Cache (optional)

Mit `report_cache_ttl > 0` werden aggregierte Ergebnisse pro Site, Report und Parameter im PSR-6-Cache (`cache.app`) vorgehalten. Der Cache-Schlüssel enthält eine Generationsnummer, die `analytics:purge --force` erhöht; veraltete Einträge laufen zusätzlich über ihre TTL ab. Betrieb ohne Cache bleibt die Standardeinstellung.

## Custom Events

Events werden über einen Endpunkt mit denselben Schutzschichten wie der Seitenaufruf-Collect erfasst:

```javascript
JenssmitAnalytics.trackEvent('newsletter_signup', 'summer-campaign', 'engagement');
```

```
POST /analytics/collect/event
Content-Type: application/json

{"name":"newsletter_signup","category":"engagement"}
```

Regeln: Namen und Kategorien sind streng validiert (`[a-zA-Z0-9_.-]`, max. 80 beziehungsweise 64 Zeichen), Werte werden nur bei aktiviertem `record_event_values` normalisiert gespeichert, es gibt keine freie JSON-Payload und keine User-Properties. Bots und DNT-/GPC-Opt-outs werden nicht erfasst. Die Tabelle `jenssmit_analytics_event` wird von `analytics:install` angelegt und von `analytics:purge` mit derselben Aufbewahrungsfrist bereinigt.

## JavaScript-SDK

`assets/analytics.js` ist ein schlankes, abhängigkeitsfreies SDK: `trackPageView()`, `trackEvent(name, value?, category?)` und `setPage(path)` für SPA-Navigation. Bevorzugt `navigator.sendBeacon()`, mit `fetch(keepalive)` als Fallback. Keine Cookies, kein LocalStorage, keine Session-ID, keine automatischen Click-Listener. DNT und Sec-GPC werden respektiert: Bei einem Opt-out-Signal sendet das SDK nichts.

```html
<script src="/assets/analytics.js"></script>
<script>
    JenssmitAnalytics.trackPageView();
</script>
```

Bei `page_view_mode: 'client'` sendet das SDK die Seitenaufrufe (mit `autoPageView: true` auch automatisch), während die serverseitige Erfassung deaktiviert ist; so wird Doppelzählung verhindert.

## Asynchrone Erfassung (optional)

Mit `symfony/messenger` kann die Speicherung asynchron erfolgen. Die Nachricht `RecordPageViewMessage` enthält ausschließlich normalisierte Daten (keine rohen IPs, keine User-Agents). Der Handler wird nur registriert, wenn Messenger installiert ist; das synchrone Recording bleibt Standard und liefert dieselbe Semantik. Bei Retries kann dieselbe Nachricht zweimal gespeichert werden (at-least-once); Duplikate beeinflussen nur Zählermetriken minimal.

## Support-Matrix

| PHP | Symfony |
| --- | --- |
| 8.2 | 6.4 LTS |
| 8.2 / 8.3 | 7.x |
| 8.4 | 8.x |

Die CI prüft PHP 8.2–8.4 gegen die höchsten Versionen und zusätzlich die niedrigsten kompatiblen Versionen (Symfony 6.4) im Kompatibilitäts-Job.

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
| IP-Pseudonym | HMAC-SHA256 aus gekürztem Präfix und Kalendertag (UTC), verschlüsselt mit dem geheimen Hash-Secret. Gültig für höchstens einen Tag. |
| User-Agent | Wird nur zur Klassifikation (Browser, System, Gerät) gelesen und nicht gespeichert. |
| Referrer | Nur der Hostname, ohne Query-Parameter und Fragmente. |
| Pfad | Ohne Query-Parameter und Fragmente (schützt vor Tokens in URLs). |
| Login-Identifier | Standardmäßig deaktiviert. |

Einordnung des IP-Hashes: Der Hash ist ein Pseudonym, keine anonyme Kennung. Er ist auf genau einen Kalendertag begrenzt und ermöglicht Näherungen für wiederkehrende Besucher innerhalb dieses Tages. Über mehrere Tage kann das Bundle deshalb keine eindeutigen Besucher, sondern nur Besuche (Besuchstage) zählen: Jeder Kalendertag mit einem Pseudonym zählt als ein Besuch. Ein dauerhaftes, geräteübergreifendes Unique-Visitor-Merkmal würde ein dauerhaftes Pseudonym erfordern und ist datenschutzrechtlich bewusst nicht umgesetzt. Mit Kenntnis des Hash-Secrets und des Präfixes ließe er sich für begrenzte Zeiträume rechnen. Betreiber, die stärkere Anonymisierung benötigen, können die Besucherzählung nicht verwenden und sollten nur Aufrufzahlen auswerten.

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
4. Das IP-Pseudonym verwendet jetzt HMAC-SHA256 mit `hash_secret` statt angehängtem Salt (`salt` ist ein veralteter Alias). Bestehende Hashes bleiben historische Pseudonyme und können nicht neu berechnet werden; am Umstellungstag können Besuchstage-Metriken dadurch einmalig verzerrt sein.
5. Neue Spalten `source`, `utm_source`, `utm_medium` und `utm_campaign` sowie die Tabelle `jenssmit_analytics_event` kommen hinzu. `analytics:install` verändert bestehende Tabellen nicht; für Bestandstabellen sind die Spalten per eigener Migration nachzuziehen, alte Datensätze erscheinen in Source-Reports als `unknown`.
6. Zeitstempel und Tagesgrenzen sind jetzt durchgehend UTC; bei Server-Zeitzonen außerhalb von UTC verschieben sich Tagesgrenzen in Reports entsprechend.
7. Die Statistik-API folgt dem einheitlichen Vertrag aus `site`, `period`, `data` und `meta`; die bisherigen flachen Antwortstrukturen (`views`, `visits` auf oberster Ebene, `total`/`items` auf oberster Ebene) entfallen. `unique_visitors` wurde bereits früher durch `visits` ersetzt.

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
