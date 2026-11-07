# Beitrag leisten

Danke für Ihr Interesse an diesem Projekt.

## Entwicklungssetup

```bash
git clone https://github.com/Jens-Smit/AnalyticsBundle.git
cd AnalyticsBundle
composer install
```

## Prüfungen vor jedem Beitrag

Alle Prüfungen müssen ohne Ausnahmen erfolgreich sein:

```bash
composer validate
composer cs-check    # PHP-CS-Fixer im Prüfmodus (composer cs-fix korrigiert)
composer stan         # PHPStan Level 6
composer test         # Unit-Tests
```

Integrationstests benötigen eine Datenbank und werden ohne `ANALYTICS_DATABASE_URL` übersprungen:

```bash
ANALYTICS_DATABASE_URL='mysql://root:root@127.0.0.1:3306/analytics_test?serverVersion=8.0' vendor/bin/phpunit --testsuite integration
ANALYTICS_DATABASE_URL='postgresql://postgres:postgres@127.0.0.1:5432/analytics_test?serverVersion=16' vendor/bin/phpunit --testsuite integration
ANALYTICS_DATABASE_URL='sqlite:///:memory:' vendor/bin/phpunit --testsuite integration
```

## Leitlinien

- Mandantentrennung ist verbindlich: Jede neue Abfrage auf Analytics-Daten benötigt einen Site-Filter; übergreifende Methoden sind ausdrücklich mit `All` zu benennen.
- Datensparsamkeit vor Komfort: Neue Felder müssen begründet werden; es werden keine User-Agent-Zeichenketten, IP-Adressen, vollständigen Referrer oder Query-Parameter gespeichert.
- Statistikberechnungen gehören in SQL, nicht in PHP; PHP lädt keine vollständigen PageView-Mengen.
- Tests gehören in den selben Pull Request wie die Änderung. Regressionstests, die einen entfernten Site-Filter fehlschlagen lassen, sind ausdrücklich erwünscht.
- Pull Requests sind klein und thematisch fokussiert, mit beschreibender Änderungsliste für CHANGELOG.md.
- Commit-Meldungen und Code-Kommentare in deutscher oder englischer Sprache, konsistent je Datei.
