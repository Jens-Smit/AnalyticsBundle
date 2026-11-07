# Jenssmit AnalyticsBundle

Wiederverwendbares Symfony-Bundle für eine cookiefreie, DSGVO-orientierte Seitenaufruf-Statistik. Das Bundle zeichnet Seitenaufrufe entweder serverseitig oder über einen öffentlichen Collect-Endpunkt auf und stellt eine JSON-Auswertungs-API bereit, die direkt ein Dashboard speist.

## Funktionsumfang

- Cookiefreie Erfassung: keine Cookies, kein LocalStorage, keine geräteübergreifende Wiedererkennung.
- Zwei Erfassungswege, kombinierbar:
  - serverseitig über das `kernel.terminate`-Ereignis (keine Verzögerung für den Besucher, weil erst nach der Antwort geschrieben wird),
  - über den Endpunkt `POST /analytics/collect` für Projekte mit getrenntem Frontend (zum Beispiel SPA oder statisches Frontend mit Symfony-API).
- Anonymisierte IP-Hashtags (gekürzte IP plus Tagesdatum plus projektbezogenem Salt), damit Besuche pro Tag näherungsweise gezählt werden können, ohne personenbezogene IP-Adressen zu speichern.
- Serverseitige Auswertung von Browser, Betriebssystem und Gerätetyp anhand des User-Agents.
- JSON-Statistik-API mit Zeitreihen, Zusammenfassung, Top-Seiten, Referrern, Browsern und Geräten.
- Konsolenbefehle für die Installation des Schemas und die Aufbewahrungsbereinigung.
- Ein Projekt-Schlüssel (`site_key`) erlaubt mehreren Projekten die Nutzung einer gemeinsamen Tabelle.

## Voraussetzungen

- PHP 8.2 oder höher
- Symfony 7 mit FrameworkBundle, SecurityBundle und Doctrine ORM (DoctrineBundle, DBAL 4)
- MySQL, MariaDB oder PostgreSQL

## Installation

### Schritt 1: Composer-Repository hinterlegen

Das Bundle ist nicht auf Packagist veröffentlicht. Fügen Sie in der `composer.json` des Projekts Ihr VCS-Repository hinzu:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:Jens-Smit/AnalyticsBundle.git"
        }
    ]
}
```

### Schritt 2: Bundle installieren

```bash
composer require jenssmit/analytics-bundle
```

Symfony Flex aktiviert das Bundle dank des Pakettyps `symfony-bundle` automatisch in `config/bundles.php`. Ohne Flex ergänzen Sie den Eintrag manuell:

```php
// config/bundles.php
return [
    // ...
    Jenssmit\AnalyticsBundle\JenssmitAnalyticsBundle::class => ['all' => true],
];
```

### Schritt 3: Salt in der Umgebung hinterlegen

Erzeugen Sie ein zufälliges Geheimnis und tragen Sie es in `.env` ein:

```bash
php -r "echo 'ANALYTICS_SALT='.bin2hex(random_bytes(32)).PHP_EOL;" >> .env
```

### Schritt 4: Konfiguration anlegen

```yaml
# config/packages/jenssmit_analytics.yaml
jenssmit_analytics:
    site_key: blog
    salt: '%env(ANALYTICS_SALT)%'
    retention_days: 365
    server_side_tracking: false   # true, wenn dieses Projekt selbst Seiten ausliefert
    excluded_paths:
        - '/_profiler'
        - '/_wdt'
        - '/.well-known'
        - '/api/health'
    allowed_origins: []            # Origins für das getrennte Frontend, z. B. ['https://www.jenssmit.de']
    stats_role: ROLE_ADMIN
    route_prefix: '/analytics'
    record_user_identifier: true
```

### Schritt 5: Routen importieren

```yaml
# config/routes/jenssmit_analytics.yaml
jenssmit_analytics:
    resource: '@JenssmitAnalyticsBundle/config/routes.php'
```

### Schritt 6: Datenbankschema anlegen

```bash
php bin/console analytics:install
```

Der Befehl legt die Tabelle `jenssmit_page_view` an, sofern sie nicht existiert (MySQL/MariaDB und PostgreSQL). Alternativ können Sie das Doctrine-Mapping über `doctrine:migrations:diff` in Ihre eigene Migrationshistorie überführen. Voraussetzung für die automatische Mapping-Erkennung ist `doctrine.orm.auto_mapping: true`; ohne Auto-Mapping hinterlegen Sie das Mapping manuell:

```yaml
doctrine:
    orm:
        mappings:
            Jenssmit\AnalyticsBundle:
                type: xml
                is_bundle: true
```

### Schritt 7: Sicherheit konfigurieren

Die Statistik-Endpunkte prüfen intern die konfigurierte Rolle (`stats_role`). Stellen Sie zusätzlich sicher, dass die Route in Ihrer Firewall erreichbar ist und angemeldete Admin-Nutzer rollenbasiert durchgelassen werden. Beispiel:

```yaml
# config/packages/security.yaml (Auszug)
security:
    access_control:
        - { path: ^/analytics/stats, roles: ROLE_ADMIN }
        - { path: ^/analytics/collect, roles: PUBLIC_ACCESS }
```

Der Collect-Endpunkt muss öffentlich erreichbar sein, da das Frontend ihn ohne Anmeldung aufruft. Missbrauch wird über die Origin-Prüfung (`allowed_origins`) und die Pfadvalidierung begrenzt.

## Einbindung im Frontend

Für Projekte mit getrenntem Frontend (zum Beispiel das Blog-Frontend von jenssmit.de mit Symfony-API) sendet das Frontend beim Seitenaufruf eine kleine JSON-Nachricht an den Collect-Endpunkt:

```html
<script>
    (function () {
        function track() {
            var payload = JSON.stringify({
                path: window.location.pathname,
                referrer: document.referrer || null
            });

            if (navigator.sendBeacon) {
                navigator.sendBeacon('/analytics/collect', new Blob([payload], { type: 'text/plain' }));
                return;
            }

            fetch('/analytics/collect', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(function () {});
        }

        track();
        window.addEventListener('popstate', track);
    })();
</script>
```

Hinweise:

- `sendBeacon` versendet als `text/plain` und löst deshalb keine CORS-Preflight-Anfrage aus; der Endpunkt akzeptiert den Inhalt unabhängig vom Content-Type.
- Bei Single-Page-Anwendungen ergänzen Sie einen Aufruf von `track()` nach jedem Router-Wechsel, zum Beispiel über einen `popstate`- oder History-API-Wrapper.
- Bei Cross-Origin-Einbindung tragen Sie die Frontend-Adresse in `allowed_origins` ein; der Endpunkt setzt dann den passenden `Access-Control-Allow-Origin`-Header.

Serverseitig ausgelieferte Seiten benötigen keinen Frontend-Code, solange `server_side_tracking: true` gesetzt ist. In diesem Fall zeichnet der `kernel.terminate`-Subscriber sichere, erfolgreiche Haupt-Requests auf.

## Konfigurationsreferenz

| Option | Typ | Standard | Bedeutung |
| --- | --- | --- | --- |
| `site_key` | string | `default` | Projektschlüssel zur Trennung mehrerer Projekte in einer Tabelle |
| `salt` | string | erforderlich | Geheimnis für die IP-Hashtags; aus `%env(ANALYTICS_SALT)%` laden |
| `retention_days` | int | `365` | Aufbewahrungsdauer für `analytics:purge` |
| `server_side_tracking` | bool | `true` | Zeichnet direkt ausgelieferte Seitenaufrufe auf |
| `track_only_successful` | bool | `true` | Nur Aufrufe mit Status unter 400 |
| `excluded_paths` | Array | `['/_profiler', '/_wdt', '/.well-known']` | Präfixe, die nicht erfasst werden |
| `allowed_origins` | Array | `[]` | Erlaubte Origins für den Collect-Endpunkt; `*` erlaubt alle |
| `stats_role` | string | `ROLE_ADMIN` | Rolle für die Statistik-API |
| `route_prefix` | string | `/analytics` | Präfix aller Bundle-Routen |
| `record_user_identifier` | bool | `true` | Speichert den Identifier angemeldeter Nutzer mit |

## Auswertungs-API

Alle Endpunkte antworten mit JSON und erfordern die konfigurierte Rolle. Die Parameter `days` und `limit` sind optional.

| Endpunkt | Parameter | Inhalt |
| --- | --- | --- |
| `GET /analytics/stats/summary` | `days` (Standard 30) | Aufrufe, näherungsweise eindeutige Besucher, Tagesschnitt |
| `GET /analytics/stats/timeseries` | `days` (Standard 30) | Aufrufe und Besucher je Kalendertag, aufsteigend sortiert |
| `GET /analytics/stats/top-pages` | `days`, `limit` | Häufigste Pfade mit Anteil |
| `GET /analytics/stats/referrers` | `days`, `limit` | Referrer-Hosts, Direktaufrufe erscheinen als `Direkt` |
| `GET /analytics/stats/browsers` | `days`, `limit` | Browserverteilung |
| `GET /analytics/stats/devices` | `days`, `limit` | Geräteverteilung (desktop, mobile, tablet, bot) |

Beispielantwort von `/analytics/stats/timeseries?days=7`:

```json
{
    "days": 7,
    "series": [
        { "date": "2026-11-01", "views": 42, "visitors": 31 },
        { "date": "2026-11-02", "views": 58, "visitors": 40 }
    ]
}
```

Die Zeitreihenstruktur ist direkt für Diagrammbibliotheken wie Chart.js verwendbar.

## Konsolenbefehle

```bash
php bin/console analytics:install         # Tabelle anlegen (idempotent)
php bin/console analytics:purge           # Trockenlauf: zeigt, was gelöscht würde
php bin/console analytics:purge --force   # Löscht Datensätze älter als retention_days
php bin/console analytics:purge --days 90 --force
```

Für die regelmäßige Bereinigung bietet sich ein Cron-Job an, zum Beispiel täglich:

```cron
15 3 * * * cd /var/www/jenssmit && php bin/console analytics:purge --force --env=prod
```

## Datenschutz

- Es werden keine Cookies und kein LocalStorage verwendet; ein Einwilligungsbanner ist deshalb in aller Regel nicht erforderlich.
- IP-Adressen werden nie im Klartext gespeichert. Gespeichert wird ein SHA-256-Hash aus der gekürzten IP, dem Kalendertag und dem projektbezogenen Salt. Der Hash wechselt täglich und lässt sich nicht in eine Adresse zurückrechnen.
- Angemeldete Nutzer werden nur als Identifier (zum Beispiel E-Mail-Adresse des Accounts) erfasst und lassen sich über `record_user_identifier: false` ganz abschalten.
- Die Erfassung sollte gleichwohl in Ihrer Datenschutzerklärung genannt werden.

## Architektur und Grenzen

Der Datenfluss ist bewusst schlicht gehalten: `PageViewSubscriber` (serverseitig) oder `CollectController` (Frontend-Beacon) rufen den `PageViewRecorder` auf, der prüft, anonymisiert und persistiert. Die Auswertung (`StatsService`) liest die Rohdaten des Zeitraums und aggregiert sie in PHP. Das ist vollständig datenbankunabhängig und für Blog- und mittelgroße Projekte ausreichend. Bei sehr hohen Zugriffszahlen (mehrere Millionen Aufrufe pro Monat) sollten Sie die Aggregation später in SQL-Gruppierungen mit plattformspezifischen Datumsfunktionen nachziehen; die Indexstruktur der Tabelle ist darauf vorbereitet.

Alle Auswertungen verwenden die Zeitzoneneinstellung des Servers.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Die Tests laufen ohne Datenbank; die Test-Infrastruktur in der Pipeline führt sie gegen PHP 8.2 bis 8.4 aus.

## Lizenz

MIT. Siehe [LICENSE](LICENSE).
