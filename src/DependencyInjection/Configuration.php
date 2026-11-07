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
                    ->info('Projekt-Schlüssel, mit dem Aufrufe in gemeinsam genutzten Tabellen getrennt werden.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('salt')
                    ->info('Geheimnis für die tägliche IP-Anonymisierung. Mindestens 32 zufällige Zeichen, aus .env laden.')
                    ->isRequired()
                ->end()
                ->integerNode('retention_days')
                    ->info('Aufbewahrungsdauer der Rohdaten in Tagen, genutzt von analytics:purge.')
                    ->defaultValue(365)
                    ->min(1)
                ->end()
                ->booleanNode('server_side_tracking')
                    ->info('Zeichnet Seitenaufrufe auf, die dieses Symfony-Projekt selbst ausliefert (kernel.terminate).')
                    ->defaultTrue()
                ->end()
                ->booleanNode('track_only_successful')
                    ->info('Nur Aufrufe mit HTTP-Status unter 400 aufzeichnen.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('excluded_paths')
                    ->info('Pfadpräfixe, die nicht aufgezeichnet werden.')
                    ->defaultValue(['/_profiler', '/_wdt', '/.well-known'])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('allowed_origins')
                    ->info('Erlaubte Origins für den Collect-Endpunkt. Leer bedeutet same-origin. "*" erlaubt alle Origins.')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('stats_role')
                    ->info('Rolle, die die Statistik-Endpunkte abrufen darf.')
                    ->defaultValue('ROLE_ADMIN')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('route_prefix')
                    ->info('Präfix aller Bundle-Routen.')
                    ->defaultValue('/analytics')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('record_user_identifier')
                    ->info('Speichert den Identifier angemeldeter Nutzer (nicht aufrufbar außerhalb des Projekts).')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
