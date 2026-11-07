# Sicherheitsrichtlinien

## Unterstützte Versionen

| Version | Status |
| --- | --- |
| 1.0.x | wird unterstützt |
| vor 1.0.0 | wird nicht unterstützt |

## Melden einer Sicherheitslücke

Bitte melden Sie Sicherheitslücken vertraulich über GitHub Security Advisories des Repositorys (Registerkarte „Security", „Report a vulnerability") oder direkt an den Repository-Inhaber. Nutzen Sie keine öffentlichen Issues für Schwachstellen. Eine Reaktion ist innerhalb weniger Tage zu erwarten; nach Bestätigung werden Fixes nach Möglichkeit als Patch-Release veröffentlicht.

## Sicherheitsmodell

- Origin-Prüfung am Collect-Endpunkt ist ein Browserschutz, keine Authentifizierung. Nicht-browserbasierte Clients können jeden Headerwert setzen; dagegen wirken Rate-Limiting, Eingabegrenzen und Validierung.
- Rate-Limiting und IP-Pseudonymisierung setzen voraus, dass `trusted_proxies` korrekt konfiguriert sind, wenn die Anwendung hinter Reverse-Proxies läuft. Ohne diese Konfiguration können gefälschte Forwarded-Header Zähler manipulieren.
- Der Salt (`ANALYTICS_SALT`) muss geheim bleiben und aus einer Umgebungsvariable stammen. Mit Kenntnis des Salts und eines IP-Präfixes ließen sich Tages-Pseudonyme für begrenzte Zeiträume nachbilden.
- Fehlerantworten des Collect-Endpunkts sind generisch und geben keine internen Details preis.
- Es werden keine Cookies gesetzt, keine rohen IP-Adressen, keine User-Agent-Zeichenketten und keine vollständigen Referrer-URLs gespeichert.
- Die Statistik-API ist rollenbasiert (`stats_role`) und strikt mandantenbezogen; Daten anderer Site-Keys sind technisch nicht erreichbar.
