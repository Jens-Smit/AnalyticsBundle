# AnalyticsBundle – Re-Audit und Release-Bewertung

**Repository:** `Jens-Smit/AnalyticsBundle`  
**Prüfdatum:** 07.11.2026  
**Branch:** `main`  
**Geprüfter Commit:** `8a9f2cbf5043e07b35b49a3295b796fd8a54e0d8`  
**Gesamtbewertung:** 9,0/10  
**Prüfart:** Statisches Code- und Architektur-Audit

## 1. Management Summary

AnalyticsBundle hat sich seit der vorherigen Prüfung deutlich weiterentwickelt. Der aktuelle Stand ist ein funktional umfangreiches Symfony-Bundle für datenschutzorientierte Webanalyse mit serverseitigem und clientseitigem Tracking, Custom Events, Traffic Attribution, mandantenbezogenen Reports sowie Export- und Erweiterungsmöglichkeiten.

Besonders positiv sind:

- HMAC-basierte tägliche IP-Pseudonymisierung
- zentrale UTC-Zeitsteuerung
- konfigurierbare Bot-Erkennung
- DNT- und GPC-Berücksichtigung
- mandantenbezogene Datenbankabfragen
- Request-Validierung und Rate-Limiting
- SQL-basierte Aggregationen
- konfigurierbare Aufbewahrung und Batch-Löschung

Die Architektur ist für ein Symfony-Bundle gut strukturiert. Vor Version 1.0 sollten jedoch die dokumentierten Konsistenz- und Betriebsprobleme behoben und die Integration in einer frischen Symfony-Anwendung validiert werden.

Die Bewertung ist eine qualitative Einschätzung auf Basis der geprüften Dateien. Sie ist weder ein Penetrationstest noch eine unabhängige Sicherheitszertifizierung.

## 2. Bewertungsübersicht

| Bereich | Bewertung | Einschätzung |
|---|---:|---|
| Architektur | 9,2/10 | Gute Trennung der Verantwortlichkeiten |
| Datenschutz | 9,2/10 | HMAC, UTC, DNT/GPC und Bot-Filter |
| Mandantentrennung | 9,5/10 | Site-scoped Repository-Zugriffe |
| Collect-Sicherheit | 9,0/10 | Origin-Prüfung, Request-Limits und Rate-Limiting |
| Datenbank | 9,0/10 | SQL-Aggregation und mehrere Datenbankplattformen |
| API | 8,8/10 | Einheitlicher Response-Vertrag |
| Erweiterbarkeit | 9,2/10 | Events, SDK und optionale Infrastruktur |
| Developer Experience | 8,5/10 | Gute Basis, weitere Integrationstests sinnvoll |

## 3. Geprüfter Funktions- und Architekturstand

### Tracking

Das Bundle unterstützt mehrere Tracking-Wege:

- Serverseitiges Tracking über den Symfony-Kernel-Lifecycle
- Clientseitiges Tracking über das JavaScript-SDK
- Manuelle Erfassung über explizite Recorder-Aufrufe
- Collect-Endpunkte für PageViews und Events

Der `page_view_mode` soll verhindern, dass Server-Tracking und Client-Beacon denselben Seitenaufruf doppelt erfassen.

### Datenschutz und Anonymisierung

Die IP-Pseudonymisierung verwendet HMAC-SHA256 mit einem geheimen Schlüssel und einem Tagesbezug. Dadurch werden keine rohen IP-Adressen dauerhaft gespeichert und die Pseudonyme sind nicht als einfache ungesalzene Hashes reproduzierbar.

Weitere Maßnahmen:

- Referrer werden auf den Hostnamen reduziert.
- Query- und Fragmentbestandteile vollständiger Referrer-URLs werden nicht gespeichert.
- User-Agent-Daten werden klassifiziert statt als vollständige Zeichenkette gespeichert.
- Bot-Erkennung kann konfiguriert werden.
- DNT und Global Privacy Control können berücksichtigt werden.
- Das Besucherkonzept ist als tagesbezogene Metrik dokumentiert.

Pseudonymisierung ist nicht gleich vollständige Anonymisierung. Aussagen wie „100 % anonym“ oder eine pauschale Garantie der DSGVO-Konformität sollten vermieden werden.

### Mandantentrennung

Die Repositories verlangen bei normalen Abfragen einen Site-Key. Globale Operationen sind über ausdrücklich benannte `*All`-Methoden erkennbar. Das reduziert das Risiko versehentlicher mandantenübergreifender Zugriffe.

Auch die Report-Cache-Schlüssel berücksichtigen den Site-Key und Report-Parameter.

### Reports und Datenbank

Statistikabfragen werden in SQL aggregiert. Die Anwendung lädt damit nicht erst vollständige PageView-Mengen in den PHP-Speicher, um anschließend in PHP zu gruppieren.

Unterstützte Datenbankplattformen:

- MySQL
- MariaDB
- PostgreSQL
- SQLite

Die Tagesgruppierung verwendet plattformspezifische SQL-Ausdrücke.

### Custom Events und Attribution

Die Event-Infrastruktur umfasst unter anderem:

- `AnalyticsEvent`
- `AnalyticsEventRecorder`
- `EventNameValidator`
- `EventValueNormalizer`
- `CollectEventController`
- `AnalyticsEventRepository`

Die Traffic Attribution klassifiziert Direct, Search, Social, Internal, Referral und Campaign. UTM-Daten werden in getrennten Feldern abgelegt.

### Export und optionale Infrastruktur

Das Bundle stellt Report- und Exportfunktionen bereit. Außerdem existieren Cache- und Messenger-Komponenten. Bei Messenger ist allerdings zwischen vorhandener Handler-Infrastruktur und einem tatsächlich durch den Recorder ausgelösten Dispatch zu unterscheiden.

## 4. Festgestellte Befunde

### A1 – Privacy-Konfiguration wird bei Custom Events nicht konsistent angewendet

**Priorität:** P1  
**Betroffene Komponente:** `AnalyticsEventRecorder`

Die Privacy- und Bot-Regeln werden beim PageView-Recording angewendet. Beim Custom-Event-Recording werden die entsprechenden Konfigurationsoptionen jedoch nicht in gleicher Weise berücksichtigt.

Betroffen sind insbesondere:

- `exclude_bots`
- `respect_dnt`
- `respect_gpc`

Damit kann eine Konfiguration, die bestimmte Signale für PageViews deaktiviert, bei Events weiterhin eine abweichende Wirkung haben.

**Empfohlene Umsetzung:**

1. Gemeinsame Privacy-Prüfung als eigenständigen Service bereitstellen.
2. `PageViewRecorder` und `AnalyticsEventRecorder` verwenden denselben Service.
3. Konfigurationsoptionen nur an einer zentralen Stelle auswerten.
4. Tests für alle Kombinationen der Optionen ergänzen.
5. Sicherstellen, dass bei Ablehnung weder PageView noch Event gespeichert wird.

**Abnahmekriterien:**

- Identisches Privacy-Verhalten für PageViews und Events.
- Jede Konfigurationsoption besitzt positive und negative Tests.
- Keine Speicherung bei aktivem DNT/GPC, sofern die jeweilige Option dies verlangt.
- Bot-Filter verhält sich bei beiden Datentypen konsistent.

### A2 – Messenger-Dispatch ist im Recording-Pfad nicht vollständig integriert

**Priorität:** P1  
**Betroffene Komponenten:** `PageViewSubscriber`, `PageViewRecorder`, `RecordPageViewMessage`, `RecordPageViewHandler`

Es existiert ein Messenger-Handler für normalisierte PageView-Nachrichten. Im geprüften Ablauf ruft der Subscriber jedoch den Recorder auf, der direkt über das Repository speichert.

Der vorhandene Handler ist damit nicht gleichbedeutend mit einem vollständig integrierten asynchronen Recording-Modus.

**Empfohlene Umsetzung:**

1. Einen klaren synchronen und asynchronen Recording-Pfad definieren.
2. Bei aktiviertem Messenger-Modus eine normalisierte Nachricht dispatchen.
3. Im synchronen Modus weiterhin direkt speichern.
4. Sicherstellen, dass Nachrichten keine rohen IP-Adressen, vollständigen User-Agent-Strings oder vollständigen Referrer-URLs enthalten.
5. Fehlerverhalten, Retries und mögliche Duplikate dokumentieren.
6. Tests für beide Betriebsmodi ergänzen.

**Architekturhinweis:** Messenger arbeitet typischerweise mit At-least-once-Zustellung. Ein Retry kann dieselbe Nachricht mehrfach verarbeiten. Wenn exakte Zähler benötigt werden, sollte eine Idempotenzstrategie über eine stabile Event-ID oder einen dedizierten Idempotency-Key vorgesehen werden.

**Abnahmekriterien:**

- Ohne Messenger-Abhängigkeit funktioniert das Bundle synchron.
- Mit aktiviertem Messenger wird tatsächlich dispatcht.
- Der Handler persistiert die Nachricht.
- Tests belegen beide Pfade.
- Retry- und Duplikatverhalten sind dokumentiert.

### A3 – JavaScript-SDK behandelt `sendBeacon()`-Fehler nicht korrekt

**Priorität:** P2  
**Betroffene Komponente:** `assets/analytics.js`

`navigator.sendBeacon()` kann `false` zurückgeben, wenn der Browser die Daten nicht zur Übertragung einreihen kann. Wenn das SDK danach trotzdem beendet wird, wird kein alternativer Versand versucht.

**Empfohlene Umsetzung:**

- Rückgabewert von `sendBeacon()` auswerten.
- Nur bei erfolgreicher Einreihung beenden.
- Andernfalls `fetch()` mit `keepalive: true` als Fallback verwenden.
- Fehler des Fallbacks kontrolliert behandeln.

Beispiel:

```javascript
if (
    global.navigator &&
    typeof global.navigator.sendBeacon === 'function' &&
    global.navigator.sendBeacon(url, body)
) {
    return;
}

if (typeof global.fetch === 'function') {
    global.fetch(url, {
        method: 'POST',
        keepalive: true,
        headers: { 'Content-Type': 'application/json' },
        body: body
    }).catch(function () {});
}
```

**Abnahmekriterien:**

- Erfolgreiches `sendBeacon()` führt nicht zu einem zusätzlichen Request.
- `sendBeacon() === false` löst den Fetch-Fallback aus.
- Fehlendes `sendBeacon()` löst den Fetch-Fallback aus.
- Fehlendes `fetch()` verursacht keinen JavaScript-Abbruch.

### A4 – Campaign-Allowlist wird zu breit auf UTM-Felder angewendet

**Priorität:** P2  
**Betroffene Komponente:** `CampaignParameterNormalizer`

Die geprüfte Implementierung verwendet dieselbe Allowlist für `utm_source`, `utm_medium` und `utm_campaign`.

Wenn die Konfiguration fachlich eine Quellen-Allowlist beschreibt, werden dadurch gültige Medium- und Campaign-Werte möglicherweise verworfen.

Beispiel:

```yaml
campaign_allowlist:
    - google
    - facebook
```

Eine Kampagne mit `utm_medium=organic` und `utm_campaign=summer-sale` darf nicht allein deshalb verworfen werden, weil diese Werte nicht in einer Source-Allowlist stehen.

**Empfohlene Umsetzung:**

- Allowlist nur auf `utm_source` anwenden oder
- drei explizit getrennte Allowlist-Konfigurationen anbieten.

Die gewählte Semantik muss in Konfiguration und Dokumentation eindeutig beschrieben werden.

**Abnahmekriterien:**

- `utm_source`, `utm_medium` und `utm_campaign` werden unabhängig validiert.
- Tests decken erlaubte und nicht erlaubte Werte ab.
- Bestehende Konfigurationen erhalten einen Migrationshinweis, falls sich das Verhalten ändert.

### A5 – CSV-Export ist nicht vollständig speicherschonend

**Priorität:** P2  
**Betroffene Komponente:** Export-Controller und `flattenForCsv()`

Die Verwendung einer `StreamedResponse` ist sinnvoll. Wenn die Daten jedoch vor dem Streaming vollständig in einem PHP-Array aufgebaut werden, ist die Implementierung nicht wirklich speicherschonend.

Bei kleinen Reports ist das akzeptabel. Bei großen Datenmengen kann der Speicherverbrauch unnötig steigen.

**Empfohlene Umsetzung:**

1. Datenbankabfrage oder Repository um eine paginierte beziehungsweise iterierbare Ausgabe ergänzen.
2. CSV-Zeilen unmittelbar in den Response-Stream schreiben.
3. Keine vollständige Ergebnisliste im Controller aufbauen.
4. Maximale Exportzeiträume oder Limits dokumentieren.
5. Große Exporte mit Integrationstests überprüfen.

**Abnahmekriterien:**

- Der Export verarbeitet Datensätze schrittweise.
- Der Speicherbedarf wächst nicht proportional zur gesamten Exportmenge.
- CSV-Escaping und Zeichensatz bleiben korrekt.
- Site-Isolation gilt auch für Exporte.

## 5. Priorisierter Implementierungsplan

### PR 1 – Einheitliche Privacy-Policy

**Priorität:** P1 · **Aufwand:** ca. 1–2 Tage

- Gemeinsamen Privacy-Service implementieren.
- Bot-, DNT- und GPC-Prüfung zentralisieren.
- PageViewRecorder und AnalyticsEventRecorder integrieren.
- Konfigurationskombinationen testen.
- Dokumentation aktualisieren.

### PR 2 – Messenger vollständig integrieren

**Priorität:** P1 · **Aufwand:** ca. 2–3 Tage

- Recording-Modus definieren.
- Message Dispatch im asynchronen Modus implementieren.
- Synchronen Standardbetrieb erhalten.
- Handler und Retry-Verhalten testen.
- Duplikatverhalten dokumentieren.
- Messenger-Setup dokumentieren.

### PR 3 – SDK-Fallback korrigieren

**Priorität:** P2 · **Aufwand:** ca. 0,5 Tage

- `sendBeacon()`-Rückgabewert prüfen.
- Fetch-Fallback ergänzen.
- Browser- und Fehlerfälle testen.

### PR 4 – UTM-Allowlist präzisieren

**Priorität:** P2 · **Aufwand:** ca. 0,5–1 Tag

- Konfigurationssemantik festlegen.
- Source-Allowlist von Medium und Campaign trennen.
- Normalizer und Tests anpassen.
- Migrationshinweis dokumentieren.

### PR 5 – CSV-Streaming verbessern

**Priorität:** P2 · **Aufwand:** ca. 1–2 Tage

- Iterierbare oder paginierte Repository-Abfrage ergänzen.
- CSV-Zeilen unmittelbar streamen.
- Speicherverhalten bei größeren Datenmengen testen.

### PR 6 – Release-Candidate-Validierung

**Priorität:** P1 · **Aufwand:** ca. 1–2 Tage

- Frische Symfony-Anwendung erstellen.
- Bundle ausschließlich über Composer einbinden.
- Konfiguration und Doctrine-Mapping prüfen.
- Migrationen ausführen.
- Tracking und Reports end-to-end testen.
- MySQL, PostgreSQL und SQLite prüfen, soweit in der CI vorgesehen.
- Aktuelle CI-Runs und statische Analyse verifizieren.
- Installations- und Upgrade-Dokumentation finalisieren.

## 6. Release-Gates für Version 1.0

- [ ] Privacy-Konfiguration gilt einheitlich für PageViews und Custom Events.
- [ ] Messenger-Modus dispatcht tatsächlich Nachrichten.
- [ ] Synchroner Betrieb funktioniert ohne Messenger.
- [ ] JavaScript-SDK besitzt einen zuverlässigen Fallback.
- [ ] UTM-Allowlist hat eine eindeutige Semantik.
- [ ] CSV-Export ist bei großen Datenmengen kontrolliert.
- [ ] Frische Symfony-Installation wurde erfolgreich getestet.
- [ ] Doctrine-Mapping und Migrationen funktionieren.
- [ ] Mandantentrennung ist für Reports, Events, Purge und Export getestet.
- [ ] CI ist für den Release-Commit erfolgreich.
- [ ] Composer-Metadaten, Versionierung und Changelog sind vollständig.
- [ ] README beschreibt Datenschutz präzise und verspricht keine absolute Anonymität.
- [ ] Security- und Support-Kontakt sind dokumentiert.

## 7. Produkt- und Wettbewerbspositionierung

Empfohlene Positionierung:

> **Privacy-first, self-hosted web analytics for Symfony. Server-side tracking, custom events and SQL-powered reports with strict site isolation.**

Differenzierungsmerkmale:

1. Symfony-native Integration statt eines externen Analytics-SaaS.
2. Serverseitiges Tracking ohne verpflichtendes JavaScript.
3. Optionales JavaScript-SDK für clientseitige Erfassung.
4. Custom Events und Traffic Attribution.
5. Mandantenbezogene Reports und Datenbankabfragen.
6. Datenschutzorientierte Voreinstellungen.
7. Keine verpflichtende externe Analytics-Infrastruktur.

Ein Dashboard sollte als separates optionales Paket entwickelt werden. Der Core sollte API-orientiert, schlank und unabhängig von einer bestimmten UI bleiben.

### Nicht vor Version 1.0 priorisieren

- Große Dashboard-Oberfläche im Core.
- Komplexe Benutzer- und Rollenverwaltung.
- Zusätzliche Datenbank-Backends ohne konkreten Bedarf.
- Umfangreiche Echtzeit-Infrastruktur.
- Weitere Event- und Reporttypen ohne klaren Nutzerbedarf.

## 8. Technische Prüfgrenzen

Diese Prüfung basiert auf dem zum Prüfzeitpunkt verfügbaren Repository-Stand und einer statischen Betrachtung ausgewählter Quellcode-, Konfigurations- und Dokumentationsdateien.

Nicht durchgeführt wurden:

- Laufende lokale Testausführung
- Vollständiger Integrationstest in einer realen Symfony-Anwendung
- Last- und Benchmarktests
- Penetrationstest
- Unabhängige Datenschutz- oder Rechtsprüfung
- Vollständige Prüfung sämtlicher Abhängigkeiten auf bekannte Schwachstellen

Für den letzten geprüften Commit konnten über die verfügbaren GitHub-Daten keine aktuellen erfolgreichen Workflow-Runs unabhängig bestätigt werden. Die vorhandene Workflow-Konfiguration ist daher nicht mit einem nachgewiesenen grünen CI-Lauf gleichzusetzen.

Die Bewertung 9,0/10 ist eine technische Gesamteinschätzung und keine Zertifizierung.

## 9. Abschließendes Urteil

AnalyticsBundle hat eine gute technische Reife erreicht. Die wesentlichen Architekturentscheidungen sind nachvollziehbar, die Datenzugriffe sind bewusst mandantenbezogen und die Datenschutzmechanismen sind deutlich ausgereifter als bei einem einfachen Tracking-Bundle.

Die verbleibenden Befunde betreffen überwiegend Konsistenz, Betriebsmodi und Skalierbarkeit. Es handelt sich nicht um eine Aufforderung, die Architektur neu aufzubauen.

**Empfohlene nächste Aktion:** PR 1 zur gemeinsamen Privacy-Policy umsetzen, anschließend Messenger-Dispatch und SDK-Fallback korrigieren. Danach sollte eine frische Symfony-Installation als Release-Candidate-Test dienen.

**Gesamtbewertung: 9,0/10 – gute Release-Candidate-Basis, mit klar abgegrenzten Korrekturen vor Version 1.0.**
