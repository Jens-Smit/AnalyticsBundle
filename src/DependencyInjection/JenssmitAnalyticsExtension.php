<?php

namespace Jenssmit\AnalyticsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class JenssmitAnalyticsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('jenssmit_analytics.site_key', $config['site_key']);
        $container->setParameter('jenssmit_analytics.salt', $config['salt']);
        $container->setParameter('jenssmit_analytics.retention_days', $config['retention_days']);
        $container->setParameter('jenssmit_analytics.record_user_identifier', $config['record_user_identifier']);
        $container->setParameter('jenssmit_analytics.server_side_tracking', $config['server_side_tracking']);
        $container->setParameter('jenssmit_analytics.track_only_successful', $config['track_only_successful']);
        $container->setParameter('jenssmit_analytics.excluded_paths', $config['excluded_paths']);
        $container->setParameter('jenssmit_analytics.allowed_origins', $config['allowed_origins']);
        $container->setParameter('jenssmit_analytics.collect_rate_limit', $config['collect_rate_limit']);
        $container->setParameter('jenssmit_analytics.collect_max_request_size', $config['collect_max_request_size']);
        $container->setParameter('jenssmit_analytics.stats_role', $config['stats_role']);
        $container->setParameter('jenssmit_analytics.route_prefix', $config['route_prefix']);
    }

    public function getAlias(): string
    {
        return 'jenssmit_analytics';
    }
}
