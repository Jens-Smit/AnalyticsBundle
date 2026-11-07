<?php

namespace Jenssmit\AnalyticsBundle\Tests\Fixtures\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jenssmit\AnalyticsBundle\JenssmitAnalyticsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimale Symfony-Anwendung, die das Bundle so einbindet, wie es ein
 * fremdes Projekt tun würde: FrameworkBundle, SecurityBundle,
 * DoctrineBundle mit auto_mapping und die Bundle-Konfiguration.
 */
final class AnalyticsTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new JenssmitAnalyticsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/jenssmit_analytics_test_'.getmypid().'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/jenssmit_analytics_test_'.getmypid().'/logs';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'test' => true,
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            'orm' => [
                'auto_mapping' => true,
                'enable_native_lazy_objects' => \PHP_VERSION_ID >= 80400,
            ],
        ]);

        $container->loadFromExtension('security', [
            'firewalls' => [
                'main' => ['security' => false],
            ],
        ]);

        $container->loadFromExtension('jenssmit_analytics', [
            'site_key' => 'test_site',
            'salt' => 'test_salt',
        ]);
    }
}
