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
                    ->info('Datenpartition je Website/Mandant. Kein Authentifizierungsgeheimnis.')
                ->end()
                ->scalarNode('salt')
                    ->isRequired()
                    ->info('Geheimer Salt für das tagesbezogene IP-Pseudonym. Aus Umgebungsvariable laden, nie committen.')
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
                ->booleanNode('server_side_tracking')
                    ->defaultTrue()
                    ->info('Erfasst Hauptanfragen serverseitig über kernel.terminate.')
                ->end()
                ->booleanNode('track_only_successful')
                    ->defaultTrue()
                    ->info('Erfasst nur Anfragen mit Statuscode 2xx/3xx.')
                ->end()
                ->arrayNode('excluded_paths')
                    ->defaultValue(['/analytics'])
                    ->scalarPrototype()->end()
                    ->info('Pfadpräfixe, die nicht erfasst werden.')
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
            ->end();

        return $treeBuilder;
    }
}
