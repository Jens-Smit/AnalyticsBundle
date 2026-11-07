<?php

namespace Jenssmit\AnalyticsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('jenssmit_analytics');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('site_key')
                    ->isRequired()
                    ->validate()
                        ->ifTrue(static fn ($v): bool => !\is_string($v) || 1 !== preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $v))
                        ->thenInvalid('Der Site-Key darf maximal 32 Zeichen lang sein und nur die Zeichen a-z, A-Z, 0-9, "_" und "-" enthalten.')
                    ->end()
                    ->info('Datenpartition je Website/Mandant. Kein Authentifizierungsgeheimnis.')
                ->end()
                ->scalarNode('hash_secret')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn ($v): bool => null !== $v && (!\is_string($v) || \strlen($v) < 16))
                        ->thenInvalid('Das Hash-Secret muss eine nicht leere Zeichenkette mit mindestens 16 Zeichen sein. Aus Umgebungsvariable laden, nie committen und nie loggen.')
                    ->end()
                    ->info('Geheimer Schlüssel (HMAC-SHA256) für das tagesbezogene IP-Pseudonym. Aus Umgebungsvariable laden, nie committen.')
                ->end()
                ->scalarNode('salt')
                    ->defaultNull()
                    ->setDeprecated('jenssmit/analytics-bundle', '1.0', 'Die Option "%node%" ist veraltet; verwende "hash_secret".')
                    ->info('Veralteter Alias für hash_secret.')
                ->end()
                ->integerNode('retention_days')
                    ->defaultValue(90)
                    ->min(1)
                    ->info('Aufbewahrungsfrist für PageViews; der Purge-Befehl nutzt sie als Standard.')
                ->end()
                ->booleanNode('record_user_identifier')
                    ->defaultFalse()
                    ->info('Speichert optional einen Login-Identifier. Standardmäßig deaktiviert, da personenbezogen.')
                ->end()
                ->booleanNode('async_recording')
                    ->defaultFalse()
                    ->info('Dispatcht PageViews als Messenger-Nachricht statt direkt zu speichern. Benötigt symfony/messenger; ohne Messenger läuft die Erfassung synchron.')
                ->end()
                ->booleanNode('record_event_values')
                    ->defaultFalse()
                    ->info('Speichert optional normalisierte Event-Werte. Standardmäßig deaktiviert; ungeprüfte Freitext-Werte werden nicht gespeichert.')
                ->end()
                ->booleanNode('server_side_tracking')
                    ->defaultTrue()
                    ->setDeprecated('jenssmit/analytics-bundle', '1.0', 'Die Option "%node%" ist veraltet; verwende "page_view_mode".')
                    ->info('Veralteter Alias: false entspricht page_view_mode "manual".')
                ->end()
                ->booleanNode('track_only_successful')
                    ->defaultTrue()
                    ->info('Erfasst nur Anfragen mit Statuscode 2xx/3xx.')
                ->end()
                ->enumNode('page_view_mode')
                    ->defaultValue('server')
                    ->values(['server', 'client', 'manual'])
                    ->info('server: Erfassung über kernel.terminate. client: Erfassung über das JavaScript-SDK/Beacon. manual: nur explizite record()-Aufrufe. Verhindert Doppelzählung.')
                ->end()
                ->arrayNode('excluded_paths')
                    ->defaultValue(['/analytics'])
                    ->scalarPrototype()->end()
                    ->info('Pfadpräfixe, die nicht erfasst werden. Ausschlüsse gelten auf Segmentgrenzen: "/admin" schließt "/admin" und "/admin/...", nicht aber "/administrator" aus.')
                ->end()
                ->arrayNode('allowed_origins')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Explizit erlaubte Origins für den Collect-Endpunkt. "*" deaktiviert die Origin-Prüfung (nicht empfohlen).')
                ->end()
                ->integerNode('collect_rate_limit')
                    ->defaultValue(360)
                    ->min(0)
                    ->info('Maximale Collect-Anfragen je anonymisiertem IP-Präfix und Stunde. 0 deaktiviert das Limit.')
                ->end()
                ->integerNode('collect_max_request_size')
                    ->defaultValue(4096)
                    ->min(256)
                    ->info('Maximale Größe des Collect-Request-Bodies in Bytes.')
                ->end()
                ->scalarNode('stats_role')
                    ->defaultValue('ROLE_ADMIN')
                    ->info('Rolle, die die Statistik-Endpunkte aufrufen darf.')
                ->end()
                ->scalarNode('route_prefix')
                    ->defaultValue('/analytics')
                    ->info('Präfix aller Bundle-Routen.')
                ->end()
                ->booleanNode('exclude_bots')
                    ->defaultTrue()
                    ->info('Bots und Crawler werden nicht erfasst. Die Entscheidung liegt im BotDetector, getrennt von der Browser-Klassifikation.')
                ->end()
                ->booleanNode('respect_dnt')
                    ->defaultTrue()
                    ->info('Anfragen mit DNT: 1 werden nicht erfasst.')
                ->end()
                ->booleanNode('respect_gpc')
                    ->defaultTrue()
                    ->info('Anfragen mit Sec-GPC: 1 werden nicht erfasst.')
                ->end()
                ->arrayNode('bot_patterns')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Zusätzliche Bot-Muster (Regex ohne Delimiter, case-insensitive). Erweitern die eingebauten Muster.')
                ->end()
                ->arrayNode('bot_exceptions')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Regex-Muster ohne Delimiter, die eine Bot-Erkennung aufheben (für False Positives). Werden zuerst geprüft.')
                ->end()
                ->arrayNode('campaign_allowlist')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->info('Optional: erlaubte utm_source-Werte. Leer bedeutet keine Allowlist-Prüfung, Werte werden trotzdem normalisiert und gekürzt.')
                ->end()
                ->integerNode('report_cache_ttl')
                    ->defaultValue(0)
                    ->min(0)
                    ->info('TTL in Sekunden für den optionalen Report-Cache (PSR-6). 0 deaktiviert den Cache.')
                ->end()
                ->integerNode('max_report_days')
                    ->defaultValue(366)
                    ->min(1)
                    ->info('Maximaler Report-Zeitraum in Tagen.')
                ->end()
            ->end();

        $rootNode
            ->validate()
                ->always(static function (array $config): array {
                    if (null === $config['hash_secret'] && null !== $config['salt']) {
                        $config['hash_secret'] = $config['salt'];
                    }

                    if (!$config['server_side_tracking'] && 'server' === $config['page_view_mode']) {
                        $config['page_view_mode'] = 'manual';
                    }

                    return $config;
                })
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => null === $config['hash_secret'])
                ->thenInvalid('Entweder "hash_secret" oder der veraltete Alias "salt" muss gesetzt sein (mindestens 16 Zeichen).')
            ->end();

        return $treeBuilder;
    }
}
