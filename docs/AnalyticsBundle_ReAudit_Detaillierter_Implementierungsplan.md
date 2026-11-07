# AnalyticsBundle -- erneute Prüfung und detaillierter Implementierungsplan

**Repository:** https://github.com/Jens-Smit/AnalyticsBundle\
**Prüfdatum:** 07.11.2026\
**Aktueller Main-Commit:** `9113c1876cbacfe02cec2e5d6176252fa3bdebbc`

## 1. Ergebnis der erneuten Prüfung

Der zuvor parallel laufende Hardening-PR **#2 ist inzwischen in Main
gemergt**. PR #3 ist noch offen und enthält laut Beschreibung
ausschließlich Changelog-Korrekturen.

  -------------------------------------------------------------------------------------------------------------------------
  PR                                                                      Status                  Ergebnis
  ----------------------------------------------------------------------- ----------------------- -------------------------
  [#1 CI-Fixes](https://github.com/Jens-Smit/AnalyticsBundle/pull/1)      Gemergt                 CS-Fixer und
                                                                                                  DBAL-4-Integrationstest

  [#2                                                                     Gemergt                 Visitor-Days, atomares
  Review-Aufgaben](https://github.com/Jens-Smit/AnalyticsBundle/pull/2)                           Rate-Limit, Content-Type,
                                                                                                  Doctrine-Auto-Mapping,
                                                                                                  Symfony-Testkernel,
                                                                                                  Paketierungs-CI

  [#3 Changelog](https://github.com/Jens-Smit/AnalyticsBundle/pull/3)     Offen                   Dokumentiert
                                                                                                  Bundle-Basisklassen- und
                                                                                                  Lazy-Proxy-Fixes
  -------------------------------------------------------------------------------------------------------------------------

**Wichtig:** Die bereits in PR #2 umgesetzten Aufgaben werden in der
Roadmap nicht erneut eingeplant.

### Aktueller technischer Stand

-   PHP `>=8.2`, Symfony-Abhängigkeiten derzeit `^7.0`
-   Doctrine ORM 3, DBAL 4, DoctrineBundle
-   Server-seitiges PageView-Tracking über `kernel.terminate`
-   Collect-Endpunkt mit Origin-Regeln, Body-Limit, Content-Type-Prüfung
    und Rate-Limit
-   Site-Key als Pflichtparameter der regulären Repository-Abfragen
-   SQL-basierte Reports
-   IPv4-/24- und IPv6-/64-Präfixbehandlung
-   Tagesbezogene Pseudonymisierung
-   Referrer- und Pfadnormalisierung
-   Retention und Batch-Purge
-   Browser-/OS-/Device-Klassifikation
-   Symfony-Testkernel und Composer-Fremdprojekt-Test
-   CI für PHP 8.2--8.4, PHPStan Level 6, CS-Fixer,
    Unit-/Functional-Tests, MySQL und PostgreSQL

**Neue Bewertung: 8,8/10** für den Entwicklungsstand eines jungen
Symfony-Bundles. Das ist eine statische Architektur- und Codebewertung,
keine Produktions- oder Sicherheitszertifizierung.

## 2. Konkrete Code-Befunde

### 2.1 Was gut gelöst ist

**Mandantentrennung:** `PageViewRepository` verlangt bei regulären
Statistik-, Zähl- und Löschoperationen einen `siteKey`. Globale Methoden
sind ausdrücklich als `*All` benannt. `StatsService` bekommt den
Site-Key per Dependency Injection.

**Datensparsame Erfassung:** `PageViewRecorder` normalisiert Pfade und
Referrer, klassifiziert User-Agents und speichert keine rohen
IP-Adressen oder vollständigen User-Agent-Strings. Login-Identifier sind
standardmäßig deaktiviert.

**Collect-Sicherheit:** Origin-Regeln, Content-Type, Request-Limit und
atomisches Rate-Limiting sind implementiert. Die README erklärt korrekt,
dass Origin kein Authentifizierungsmechanismus ist.

**SQL-Reports:** Die Datenbank übernimmt Zählungen und Gruppierungen.
Der StatsService hydriert nicht mehr die gesamte PageView-Tabelle.

**Integration:** Der Symfony-Testkernel prüft Doctrine-Auto-Mapping und
Persistenz. Die Paketierungs-CI installiert das Bundle in einem fremden
Composer-Projekt.

### 2.2 Neue Aufgaben aus der Codeprüfung

#### A. Hash auf HMAC umstellen

Aktuell in `IpAnonymizer::hash()`:

``` php
hash('sha256', $anonymizedIp.'|'.$day->format('Y-m-d').'|'.$salt);
```

Empfohlen:

``` php
hash_hmac(
    'sha256',
    $anonymizedIp.'|'.$day->format('Y-m-d'),
    $secret
);
```

Das Secret wird damit als kryptografischer Schlüssel verwendet und nicht
nur an die Eingabe angehängt.

**Migration:** Die Hashes ändern sich. Bestehende Hashes bleiben
historische Pseudonyme und können ohne Ausgangsdaten nicht neu berechnet
werden. Der Effekt auf Tagesmetriken am Umstellungstag muss dokumentiert
werden.

#### B. Bot-Erkennung tatsächlich als Filter verwenden

`UserAgentParser` erkennt Bots bereits und gibt `browser = Bot` sowie
`device = bot` zurück. `PageViewRecorder::record()` speichert diese
Aufrufe aber weiterhin.

**Folge:** Bots werden klassifiziert, aber nicht aus den PageView-Zahlen
ausgeschlossen.

Lösung: Bot-Entscheidung als eigenen Schritt im Recorder implementieren.
Nicht anhand des Browser-Strings `Bot` als versteckten Seiteneffekt
filtern.

#### C. UTC-Zeitstrategie

`PageViewRecorder` verwendet `new DateTimeImmutable()`, `StatsService`
`new DateTimeImmutable('today')`. Damit hängen Tagesgrenzen von der
Server-Zeitzone ab.

Lösung: UTC für gespeicherte Zeitpunkte und Hash-Tagesgrenzen. Eine
spätere lokale Report-Zeitzone muss explizit als Report-Parameter
behandelt werden.

#### D. Konfiguration validieren

`site_key` und `salt` sind erforderlich, aber nicht hinreichend auf
Länge und Format validiert. `PageView.site_key` ist maximal 32 Zeichen
lang.

Lösung: - Site-Key maximal 32 Zeichen - definiertes Format, z. B.
`[a-zA-Z0-9_-]+` - Secret nicht leer, Mindestlänge prüfen -
verständlicher Fehler bereits beim Container-Kompilieren

#### E. Pfad-Ausschlüsse präzisieren

`PageViewRecorder::isExcluded()` nutzt `str_starts_with()`. Ein
Ausschluss `/admin` kann dadurch auch `/administrator` ausschließen.

Lösung: Pfadsegmentgrenzen unterstützen. Exakter Pfad `/admin` und
Unterpfade `/admin/...` sollen ausgeschlossen werden, nicht beliebige
Wörter mit gleichem Präfix.

#### F. Doppelzählung bei zwei Erfassungswegen verhindern

Wenn `server_side_tracking=true` und zusätzlich ein Client-Beacon
PageViews sendet, kann eine Navigation doppelt gezählt werden.

Lösung: expliziter `page_view_mode` mit `server`, `client` oder
`manual`. Serverseitige Erfassung bleibt Standard. Keine dauerhafte
Besucherkennung zur Deduplizierung einführen.

#### G. Synchrones Flush als Skalierungsgrenze

`PageViewRepository::save()` führt pro PageView `persist()` und
`flush()` aus. Das ist für den Start einfach, kann bei hohem Traffic
aber Datenbanklatenz erzeugen.

Lösung: Erst messen. Sync bleibt Standard. Optionaler Messenger-Modus
erst nach einem Benchmark.

#### H. Bot-Regeln auf False Positives prüfen

Die vorhandene Regex ist breit und kann reguläre User-Agent-Strings
treffen.

Lösung: konfigurierbare Regeln und Ausnahmen, nachvollziehbare
Treffergründe in Tests, keine vollständigen User-Agent-Logs.

## 3. Wettbewerbsanalyse

### 3.1 Bahdan Privacy Analytics Bundle

Quellen: -
https://github.com/bahdanhal/symfony-privacy-analytics-bundle -
https://packagist.org/packages/bahdan/symfony-privacy-analytics-bundle

Packagist zeigte zum Prüfzeitpunkt Version 1.5.0, PHP `>=8.3`, Symfony
7/8 und 321 Installationen im angezeigten Snapshot.

**Stärken** - HMAC-SHA256 für tägliche Pseudonyme - DNT und Sec-GPC -
Bot-/Crawler-Filter mit konfigurierbaren Mustern -
Referrer-/Source-Kategorien - begrenzte UTM-Auswertung - Doctrine- und
JSONL-Storage - optionaler Messenger-Betrieb - PSR-6-/Symfony-Cache für
aggregierte Reports - keine verpflichtende
Dashboard-Frontend-Abhängigkeit

**Einschränkungen** - PHP 8.3 als Mindestversion - laut Composer Symfony
7/8, nicht Symfony 6.4 - JSONL ist nicht automatisch performanter als
SQL - kein gleichwertig dokumentierter expliziter Site-Key-Ansatz wie
bei AnalyticsBundle

**Übernehmen:** HMAC, DNT/GPC, konfigurierbaren Bot-Filter,
Source-Kategorien, optional Cache und Messenger. JSONL nicht ohne
messbaren Bedarf bauen.

### 3.2 Jackfumanchu Cookieless Analytics Bundle

Quellen: - https://github.com/jackfumanchu/cookieless-analytics-bundle -
https://packagist.org/packages/jackfumanchu/cookieless-analytics-bundle

Packagist zeigte v0.4.0, PHP `>=8.2`, Symfony 6.4/7.4/8 und 11
Installationen im Snapshot.

**Stärken** - Dashboard mit Overview, Top Pages, Events und Trends -
Custom Events - kleines JavaScript-Script mit `sendBeacon()` -
Twig-Integration - SQL-Aggregationen - Symfony 6.4, 7.4 und 8 als
Composer-Ziel - Browser-/Functional-Testansatz

**Im Quellcode geprüfte Einschränkungen** - `FingerprintGenerator`
verwendet `SHA-256(IP + User-Agent + Datum)` ohne erkennbares
HMAC-Secret. - `CollectController` prüft JSON und Pflichtfelder, aber
implementiert dort keine Origin-Allowlist, kein Request-Body-Limit und
kein Rate-Limiting in vergleichbarer Tiefe wie AnalyticsBundle.

**Übernehmen:** Events, optionales SDK, breitere Symfony-Kompatibilität
und Browser-Tests. Nicht das schwächere Fingerprinting und nicht die
einfachere Collect-Sicherung übernehmen.

### 3.3 Vergleichsmatrix

  -------------------------------------------------------------------------------
  Fähigkeit                 AnalyticsBundle   Bahdan            Jackfumanchu
  ------------------------- ----------------- ----------------- -----------------
  PHP 8.2                   Ja                Nein              Ja

  Symfony 6.4               Noch nicht        Nein              Ja
                            freigegeben                         

  Symfony 7.4               Aktuell \^7.0     Ja                Ja

  Symfony 8                 Noch nicht        Ja                Ja
                            freigegeben                         

  Server-seitige PageViews  Ja                Ja                Ja

  Collect Endpoint          Ja, mehrere       Serverseitiger    Ja, im geprüften
                            Schutzschichten   Schwerpunkt       Controller
                                                                einfacher

  Site-Key-Filter in SQL    Explizit          Nicht             Installation je
                                              gleichwertig      Symfony-App
                                              dokumentiert      

  HMAC-Pseudonym            Noch nein         Ja                Nein im geprüften
                                                                Generator

  DNT/GPC                   Nein              Ja                Nicht als
                                                                Kernfunktion
                                                                dokumentiert

  Bot-Filter                Klassifikation,   Ja                Roadmap
                            kein Ausschluss                     

  Source-/UTM-Attribution   Nein              Ja                Roadmap

  Custom Events             Nein              Nicht als         Ja
                                              Kernfunktion      
                                              dokumentiert      

  Dashboard                 Nein              Nein              Ja

  Messenger                 Nein              Optional          Nicht als
                                                                Kernfunktion
                                                                dokumentiert
  -------------------------------------------------------------------------------

Packagist-Installationszahlen sind Momentaufnahmen, keine verlässlichen
Zahlen aktiver oder produktiver Installationen. Wettbewerber wurden
nicht unter Last oder durch Penetrationstests geprüft. Bei Bahdan beruht
der Vergleich auf Paketbeschreibung und Composer-Metadaten. Bei
Jackfumanchu wurden zusätzlich `FingerprintGenerator`,
`CollectController`, `composer.json` und README eingesehen.

## 4. Klare Positionierung für AnalyticsBundle

### Positionierungssatz

**GitHub / Packagist:**

> Privacy-first, self-hosted web analytics for Symfony with server-side
> tracking, strict site isolation and SQL-powered reports.

**Deutsch:**

> Datensparsame, selbst gehostete Webanalyse direkt in Symfony.
> Serverseitige Erfassung, konsequente Site-Isolation und SQL-basierte
> Statistiken über eine API, die sich in bestehende Anwendungen
> integrieren lässt.

### Zielgruppe

-   Symfony- und PHP-Entwickler
-   Symfony-Agenturen
-   SaaS-Anbieter mit Symfony-Backend
-   Betreiber eigener Symfony-Anwendungen
-   Teams, die Analytics in vorhandene Admin-/Backoffice-Oberflächen
    integrieren

Nicht primär: Marketing-Teams, die sofort ein vollständiges Dashboard
erwarten, oder Unternehmen, die Session Replay, Heatmaps und User
Journeys benötigen.

### Drei Kernbotschaften

1.  **Privacy-first:** keine Cookies im Core, keine rohen IPs, keine
    vollständigen User-Agent-Strings, keine vollständigen Referrer-URLs,
    tagesbezogene Pseudonymisierung.
2.  **Symfony-native:** Composer, Dependency Injection, Doctrine, Kernel
    Events, geschützter Stats-Endpunkt.
3.  **Kontrolle:** selbst gehostet, eigene Datenhaltung, kein externer
    Analytics-Dienst und kein verpflichtendes Dashboard.

Nicht behaupten: „100 % anonym", „automatisch DSGVO-konform" oder „immer
ohne Consent-Banner erlaubt". Besser: „privacy-first", „datensparsame
Standardkonfiguration" und „für cookielose Analytics ausgelegt".

### Abgrenzung

-   Bahdan ist bei Privacy-Komfortfunktionen und optionalen Betriebsmodi
    weiter.
-   Jackfumanchu ist bei Events, SDK und Dashboard weiter.
-   AnalyticsBundle differenziert über explizite Site-Isolation, sichere
    Collect-API, SQL-Reports und eine schlanke Symfony-Core-API.

**Empfohlene GitHub Description:**

> Privacy-first, self-hosted web analytics for Symfony with server-side
> tracking, strict site isolation and SQL-powered reports.

**Topics:** `php`, `symfony`, `symfony-bundle`, `analytics`, `privacy`,
`cookieless`, `self-hosted`, `doctrine`, `web-analytics`

## 5. Detaillierter Implementierungsplan

Die PRs sind bewusst klein und reviewbar. Die Aufwände sind grobe
Schätzungen inklusive Tests und Dokumentation.

### PR 0 -- Bereits erledigt: Hardening

**Status:** Gemergt in PR #2.

Nicht erneut bearbeiten: - atomisches Rate-Limiting -
Content-Type-Validierung - Infrastruktur-Body-Limits dokumentieren -
Visitor-Days statt falscher periodenübergreifender Unique Visitors -
Doctrine-Auto-Mapping und Symfony-Testkernel -
Composer-Fremdprojekt-Installation - zusätzliche Edge-Case-Tests

### PR 1 -- HMAC, UTC und Konfigurationsvalidierung

**Priorität:** P0 · **Aufwand:** 1--2 Tage

**Dateien:** `IpAnonymizer.php`, `PageViewRecorder.php`,
`Configuration.php`, `JenssmitAnalyticsExtension.php`, Tests, README,
CHANGELOG.

Aufgaben: 1. `hash()` auf `hash_hmac('sha256', payload, secret)`
umstellen. 2. `salt` mittelfristig als `hash_secret` benennen. Falls
Rückwärtskompatibilität benötigt wird, `salt` als deprecated Alias
akzeptieren. 3. Secret nie loggen. Mindestlänge und nichtleeren Wert
prüfen. 4. Site-Key auf max. 32 Zeichen und definiertes Format
validieren. 5. UTC als interne Zeitzone festlegen. 6. Tages-Hash und
Zeitstempel über eine zentrale Clock/Zeitquelle erzeugen. 7. Tests für
Tageswechsel, Secret-Wechsel, Zeitzone und HMAC-Stabilität schreiben. 8.
Hashwechsel und Metrikeffekt in CHANGELOG dokumentieren.

**Konfigurationsziel:**

``` yaml
jenssmit_analytics:
    site_key: '%env(ANALYTICS_SITE_KEY)%'
    hash_secret: '%env(ANALYTICS_HASH_SECRET)%'
    timezone: 'UTC'
```

**Akzeptanz:** kein einfacher SHA-256-Hash mehr, reproduzierbare
HMAC-Tests, Containerfehler bei ungültigem Site-Key/Secret, CI PHP
8.2--8.4 grün.

### PR 2 -- Bot-Filter und Privacy-Signale

**Priorität:** P0 · **Aufwand:** 1--2 Tage · **Abhängigkeit:** PR 1

**Dateien:** `UserAgentParser.php`, `PageViewRecorder.php`,
`CollectController.php`, `Configuration.php`, neue BotDetector-Klasse
und Tests.

Aufgaben: 1. Bot-Erkennung von Browser-Klassifikation trennen. 2.
Explizites `isBot()` bzw. `BotDetectionResult` einführen. 3.
`exclude_bots: true` als Standard ergänzen. 4. Bot-Muster und Ausnahmen
konfigurierbar machen. 5. DNT `1` und Sec-GPC `1` respektieren. 6.
`respect_dnt` und `respect_gpc` standardmäßig `true`. 7. Server-Tracking
und Collect identisch behandeln. 8. False-Positive-Tests mit Chrome,
Firefox, Safari, Headless und leeren User-Agents ergänzen. 9. Keine
vollständigen User-Agent-Strings loggen.

**Konfiguration:**

``` yaml
jenssmit_analytics:
    exclude_bots: true
    respect_dnt: true
    respect_gpc: true
    bot_patterns: []
    bot_exceptions: []
```

**Akzeptanz:** Bots und Privacy-opt-out Requests werden nicht
gespeichert, normale Browser nicht versehentlich ausgeschlossen,
Optionen einzeln konfigurierbar.

### PR 3 -- Traffic Attribution: Source und UTM

**Priorität:** P1 · **Aufwand:** 2--3 Tage · **Abhängigkeit:** PR 1/2

**Neue Services:** `TrafficSourceClassifier`,
`CampaignParameterNormalizer`, optional
Suchmaschinen-/Social-Host-Registry.

**Datenmodell:** `source` plus optionale, validierte Kampagnenfelder.
Werte `direct`, `search`, `social`, `internal`, `referral`, `campaign`.

Aufgaben: 1. Referrer-Host weiter über `ReferrerNormalizer`
normalisieren. 2. Bekannte Suchmaschinen und Social-Hosts
klassifizieren. 3. eigene Hosts als `internal` erkennen. 4. keine
Referrer-URL und keine vollständigen Query-Strings speichern. 5.
UTM-Werte begrenzen und optional per Allowlist validieren. 6. `utm_term`
und `utm_content` im ersten Release nicht speichern. 7.
`sourcesForSite()` als SQL-Repository-Methode ergänzen. 8.
`GET /analytics/stats/sources` ergänzen. 9. alte Datensätze ohne Source
als `unknown` behandeln.

**Akzeptanz:** alle Abfragen site-scoped, SQL-Aggregation, Tests für
alle Kategorien, keine ungeprüften Query-Parameter in der DB.

### PR 4 -- Statistik-API-Vertrag und DTOs

**Priorität:** P1 · **Aufwand:** 1--2 Tage · **Abhängigkeit:** PR 3

Aufgaben: 1. Response-Formate durch DTOs oder klar typisierte
Response-Objekte stabilisieren. 2. Einheitliche Struktur für `site`,
`period`, `data`, `meta`. 3. `visits` als Besuchstage definieren,
`visitors` nur als Tagesmetrik. 4. UTC-Datumsgrenzen vereinheitlichen.
5. `days`, `limit`, `from`, `to` konsistent validieren. 6. maximale
Report-Periode konfigurierbar machen. 7. JSON-Fehlerantworten
vereinheitlichen. 8. Deprecation- und API-Versionierungsregeln
dokumentieren.

**Akzeptanz:** jeder Endpunkt mit dokumentiertem Schema, Rechte- und
Site-Isolation-Tests, keine falschen Unique-Visitor-Versprechen.

### PR 5 -- Custom Events Backend

**Priorität:** P1 · **Aufwand:** 3--5 Tage · **Abhängigkeit:** PR 1/4

Neue Entity `AnalyticsEvent`, Tabelle `jenssmit_analytics_event`.

Felder: - `id` - `site_key` - `event_name` (max. 80) - `event_category`
optional - `path` - `event_value` optional, nur nach Validierung -
`visitor_hash` optional, tagesbezogen - `occurred_at` in UTC

Neue Services: - `AnalyticsEventRecorder` - `EventNameValidator` -
`EventValueNormalizer`

Neue Route: `POST /analytics/collect/event`.

Aufgaben: 1. dieselben Schutzschichten wie beim PageView-Collect
anwenden. 2. Event-Namen streng validieren oder Allowlist nutzen. 3.
Event-Values standardmäßig deaktivieren oder pro Event erlauben. 4.
keine freie JSON-Payload und keine User-Properties im ersten Release. 5.
Site-Key in jedem Repository-Query erzwingen. 6. Retention/Purge für
Events in Batches ergänzen. 7. SQL-Reports für Event-Namen und
Tageswerte ergänzen. 8. MySQL, PostgreSQL und SQLite testen.

**Akzeptanz:** kein Event ohne gültigen Namen, keine
mandantenübergreifenden Abfragen, Retention und Purge funktionieren,
keine ungeprüften personenbezogenen Werte.

### PR 6 -- JavaScript SDK und Twig-Integration

**Priorität:** P1/P2 · **Aufwand:** 2--4 Tage · **Abhängigkeit:** PR 5

SDK: - `trackPageView()` - `trackEvent(name, value?)` - `setPage(path)`
für SPA-Navigation - `navigator.sendBeacon()` bevorzugt - `fetch()` als
Fallback - keine Cookies, kein LocalStorage, keine Session-ID - keine
automatischen Click-Listener im ersten Release

Twig-Integration optional, beispielsweise `jenssmit_analytics_script()`.

Wichtige Konfiguration:

``` yaml
jenssmit_analytics:
    page_view_mode: 'server' # server | client | manual
```

Damit werden doppelte PageViews durch Server-Tracking plus Beacon
verhindert.

Tests: SDK Payload-Normalisierung, Beacon, Fetch-Fallback,
SPA-Routenwechsel, DNT/GPC und keine Cookies/LocalStorage.

### PR 7 -- Symfony 6.4 / 7.4 / 8 Kompatibilität

**Priorität:** P1 · **Aufwand:** 2--4 Tage

Aktuell verhindern Runtime-Constraints `^7.0` die offizielle
Installation unter Symfony 6.4 und 8.

Zielmatrix:

  PHP       Symfony
  --------- ---------
  8.2       6.4 LTS
  8.2/8.3   7.4 LTS
  8.4       8.x

Aufgaben: 1. Runtime-Constraints aller Symfony-Abhängigkeiten prüfen. 2.
Kompatible Constraints wie `^6.4 || ^7.4 || ^8.0` setzen, wo APIs
kompatibel sind. 3. `extra.symfony.require` prüfen und ggf. passend
ändern. 4. DoctrineBundle `^2.13 || ^3.0` validieren. 5. Lock,
SecurityBundle und FrameworkBundle in echter Test-App prüfen. 6.
CI-Matrix nach PHP-/Symfony-Versionen aufbauen. 7. Composer-Constraints
nicht ohne Container-Kompilierung und Tests erweitern. 8. Flex Recipe
erst ergänzen, wenn Registrierung und Konfiguration stabil sind.

**Akzeptanz:** `composer require`, Container-Kompilierung,
Doctrine-Mapping, Routen und Functional Tests in jeder beworbenen
Version.

### PR 8 -- Report Cache

**Priorität:** P2 · **Aufwand:** 2--3 Tage · **Abhängigkeit:** PR 4

Aufgaben: 1. optionalen PSR-6-Cache injizieren. 2. TTL konfigurierbar
machen, Startwert 30--60 Sekunden. 3. Cache-Key enthält Site-Key,
Report, Zeitraum, Filter und API-Version. 4. nur aggregierte Ergebnisse
cachen. 5. Purge/Invalidierung berücksichtigen. 6. Cache-Hit/Miss
optional messbar machen. 7. Benchmark vor/nach Cache.

**Akzeptanz:** keine Site-übergreifenden Cache-Hits, korrekte
Parameter-Keys, Betrieb ohne Cache bleibt möglich.

### PR 9 -- Optionaler Messenger-Modus

**Priorität:** P2 · **Aufwand:** 3--5 Tage · **Abhängigkeit:** PR 5

Neue Komponenten: - `RecordPageViewMessage` -
`RecordAnalyticsEventMessage` - Message Handler für beide

Aufgaben: 1. Sync bleibt Standard. 2. Messenger bleibt optionale
Abhängigkeit (`suggest`). 3. Messages enthalten nur normalisierte Daten.
4. keine rohe IP und keine vollständigen User-Agent-Strings in Queues.
5. Retry-/Failure-Strategie dokumentieren. 6.
Idempotenz/Duplikatverhalten festlegen. 7. Test-Transport und
Integrationstests ergänzen.

**Akzeptanz:** Sync und Async liefern gleiche Semantik, Standardmodus
funktioniert ohne Messenger.

### PR 10 -- CSV-/JSON-Export

**Priorität:** P2 · **Aufwand:** 2--3 Tage · **Abhängigkeit:** PR 4/5

Aufgaben: 1. CSV für aggregierte Reports. 2. JSON-Export. 3. Site-Key
immer erzwingen. 4. Zeitraum und Zeilenlimit validieren. 5. nur
aggregierte Daten exportieren. 6. keine IP-Hashes, Login-Identifier oder
vollständigen User-Agents exportieren. 7. Streaming Response für große
CSVs. 8. Security-Regeln der Stats API übernehmen.

**Akzeptanz:** mandantenisoliert, korrekt escaped, große Exporte ohne
komplette PHP-In-Memory-Datenmenge.

### PR 11 -- Dashboard als separates Paket

**Priorität:** P3 · **Aufwand:** 5--8 Tage · **Abhängigkeit:** stabile
API und Events

Paketstruktur: - `jenssmit/analytics-bundle`: Core, Tracking, Storage,
Reports, API - `jenssmit/analytics-dashboard`: optionale
Twig-/Symfony-UI - `jenssmit/analytics-js`: nur falls SDK separat
versioniert werden soll

Dashboard MVP: - Zeitraum-Auswahl - PageViews und tägliche Pseudonyme -
Visitor-Days - Top Pages - Referrer und Sources - Browser/OS/Device -
Events - CSV-Export

Nicht im MVP: Session Replay, Heatmaps, dauerhafte Besucherprofile und
komplexe Funnels.

### PR 12 -- Release 1.0 und Developer Experience

**Priorität:** P1 · **Aufwand:** 2--3 Tage

Aufgaben: 1. README Quickstart-first überarbeiten. 2. minimale
Demo-Symfony-App veröffentlichen. 3. CI-, PHP-, Symfony-, Packagist- und
Lizenz-Badges. 4. Support-Matrix veröffentlichen. 5. `SECURITY.md` und
`CONTRIBUTING.md` prüfen. 6. Changelog und Upgrade-Pfade pflegen. 7.
SemVer-/Deprecation-Policy dokumentieren. 8. Packagist-Installation in
CI prüfen. 9. Release-Tag und GitHub Release erstellen. 10. keine 1.0,
solange API-/Schemaänderungen ohne Migrationshinweise offen sind.

## 6. Reihenfolge und Abhängigkeiten

  Reihenfolge        PR                               Priorität     Aufwand
  ------------------ ------------------------------ ----------- -----------
  Bereits erledigt   PR #2 Hardening                     Fertig          --
  Jetzt              PR #3 Changelog                    Niedrig    0,25 Tag
  1                  HMAC, UTC, Config Validation            P0   1--2 Tage
  2                  Bot-Filter, DNT/GPC                     P0   1--2 Tage
  3                  Source-/UTM-Attribution                 P1   2--3 Tage
  4                  API-Vertrag / DTOs                      P1   1--2 Tage
  5                  Custom Events Backend                   P1   3--5 Tage
  6                  JavaScript SDK                       P1/P2   2--4 Tage
  7                  Symfony 6.4/7.4/8                       P1   2--4 Tage
  8                  Cache                                   P2   2--3 Tage
  9                  Messenger                               P2   3--5 Tage
  10                 Export                                  P2   2--3 Tage
  11                 Dashboard-Paket                         P3   5--8 Tage
  12                 Release 1.0                             P1   2--3 Tage

PR 2 und PR 7 können nach PR 1 weitgehend parallel entwickelt werden. PR
3 baut auf der Privacy-/Bot-Logik auf. PR 4 stabilisiert die API, bevor
Events ergänzt werden. SDK, Cache, Messenger, Export und Dashboard bauen
anschließend auf stabilen Backend-Services auf.

## 7. Anforderungen, die für jeden PR gelten

### Mandantentrennung

-   jede neue Entity erhält `site_key`
-   jede Repository-Abfrage verlangt `siteKey`
-   keine optionalen Site-Filter
-   globale Operationen explizit als `*All`
-   Cache, Export und Queue berücksichtigen Site-Kontext
-   Tests mit mindestens zwei Sites und überlappenden Daten

### Datenschutz

-   keine rohen IP-Adressen
-   keine vollständigen User-Agent-Strings
-   keine vollständigen URLs oder Query-Strings
-   keine dauerhafte Besucher-ID
-   Event-Values nicht als unvalidierten Freitext speichern
-   keine personenbezogenen Daten in Logs, Queues oder Exporten
-   Hashes als pseudonyme Daten bezeichnen, nicht als anonym

### Datenbank

-   MySQL 8, MariaDB, PostgreSQL und SQLite berücksichtigen
-   SQL-Aggregationen statt Entity-Hydrierung
-   Indizes nach `site_key`, Zeit und Gruppierungsdimension planen
-   Schemaänderungen dokumentieren
-   Purge-Batches für neue Tabellen ergänzen

### CI und Tests

-   Unit-Tests für Normalizer, Classifier und Validatoren
-   Functional Tests über Symfony-Testkernel
-   Integrationstests für SQL und Site-Isolation
-   Negativtests für ungültige Inputs
-   PHPStan und CS-Fixer
-   alle unterstützten PHP-/Symfony-Versionen
-   MySQL, PostgreSQL und SQLite für DB-Features

## 8. Was nicht gebaut werden sollte

-   kein Session Replay
-   keine Heatmaps
-   keine dauerhaften Besucherprofile
-   kein immer umfangreicheres Browser-Fingerprinting
-   keine freie User-Properties-JSON-Payload
-   kein eigenes BI-System
-   kein JSONL-Backend ohne belegten Bedarf
-   kein Dashboard im Core
-   keine Aussage „garantiert DSGVO-konform" oder „100 % anonym"

## 9. README-Positionierung

Empfohlener Einstieg:

``` markdown
# AnalyticsBundle

Privacy-first, self-hosted web analytics for Symfony.

A lightweight analytics foundation with server-side tracking,
strict site isolation, privacy-minded defaults and SQL-powered
reports. Keep your analytics data in your own infrastructure and
integrate the API into the Symfony application you already use.

- No cookies by default
- No raw IP or full User-Agent storage
- Strict site-scoped reports
- SQL-powered aggregation
- Server-side tracking and secured collection endpoint
- Optional client-side tracking
```

DNT/GPC und Bot-Ausschluss erst dann als fertige Features bewerben, wenn
PR 2 umgesetzt und gemergt ist.

## 10. Release-Gates für 1.0

-   [ ] PR #3 abgeschlossen oder bewusst verworfen
-   [ ] HMAC-basierte Tagespseudonymisierung
-   [ ] UTC-Strategie für Zeitstempel und Tagesgrenzen
-   [ ] Site-Key-/Secret-Validierung
-   [ ] Bot-Filter und DNT/GPC mit Tests
-   [ ] Source-Attribution
-   [ ] stabiler API-Vertrag
-   [ ] Symfony-Kompatibilität in CI getestet
-   [ ] Composer-Fremdprojekt-Test grün
-   [ ] Migrationsstrategie dokumentiert
-   [ ] Quickstart in frischer Symfony-App getestet
-   [ ] Security- und Privacy-Dokumentation geprüft
-   [ ] keine offenen P0/P1-Fehler

Custom Events und SDK sind für eine erste 1.0 nicht zwingend, wenn du
bewusst ein PageView-Core-Bundle veröffentlichen willst. Dann müssen sie
klar als Roadmap und nicht als bereits vorhandene Funktion
gekennzeichnet sein.

## 11. Abschließende Empfehlung

Der aktuelle Main-Stand ist deutlich näher an einem ernstzunehmenden
Composer-Bundle als der Stand der ersten Prüfung. PR #2 hat echte
Integrations- und Betriebsprobleme behoben.

Der stärkste Wettbewerbsvorteil liegt in: - strikter Site-Isolation -
abgesichertem Collect-Endpunkt - datensparsamer Erfassung -
SQL-basierter Auswertung - Symfony-nativer Integration - API-first statt
Dashboard-Zwang

Bahdan ist bei Privacy-Komfortfunktionen und optionalen Betriebsmodi
weiter. Jackfumanchu ist bei Events, SDK und Dashboard weiter.
AnalyticsBundle sollte seine Kernqualität ausbauen und die
Zusatzfunktionen modular ergänzen.

**Die nächsten drei PRs sollten sein:**

1.  `feat: use HMAC for daily visitor pseudonyms and standardize UTC`
2.  `feat: add configurable bot exclusion and privacy signal handling`
3.  `feat: add privacy-safe traffic source attribution`

Erst danach würde ich Custom Events und SDK beginnen.

## 12. Prüfquellen und Grenzen

### Eigenes Repository

-   https://github.com/Jens-Smit/AnalyticsBundle
-   https://github.com/Jens-Smit/AnalyticsBundle/commit/9113c1876cbacfe02cec2e5d6176252fa3bdebbc
-   https://github.com/Jens-Smit/AnalyticsBundle/pull/2
-   https://github.com/Jens-Smit/AnalyticsBundle/pull/3

Geprüfte Dateien u. a.: `composer.json`, `README.md`, `CHANGELOG.md`,
`Configuration.php`, `JenssmitAnalyticsExtension.php`, `PageView.php`,
`PageViewRepository.php`, `PageViewRecorder.php`, `IpAnonymizer.php`,
`CollectRateLimiter.php`, `UserAgentParser.php`, `PathNormalizer.php`,
`ReferrerNormalizer.php`, `CollectController.php`,
`StatsController.php`, `PageViewSubscriber.php`, `config/services.php`,
`DoctrineMappingTest.php`, `AnalyticsTestKernel.php` und
`.github/workflows/tests.yml`.

### Wettbewerber

-   https://github.com/bahdanhal/symfony-privacy-analytics-bundle
-   https://packagist.org/packages/bahdan/symfony-privacy-analytics-bundle
-   https://github.com/jackfumanchu/cookieless-analytics-bundle
-   https://packagist.org/packages/jackfumanchu/cookieless-analytics-bundle

Die Prüfung ist ein statischer Repository-/Quellcode-Audit. Es wurden
keine eigenen Lasttests, Penetrationstests oder juristischen Prüfungen
durchgeführt. Packagist-Installationszahlen sind Momentaufnahmen und
kein Nachweis aktiver Produktionsinstallationen.
