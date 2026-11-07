<?php

namespace Jenssmit\AnalyticsBundle\DependencyInjection;

use Jenssmit\AnalyticsBundle\Command\InstallCommand;
use Jenssmit\AnalyticsBundle\Command\PurgeCommand;
use Jenssmit\AnalyticsBundle\Controller\CollectController;
use Jenssmit\AnalyticsBundle\Controller\StatsController;
use Jenssmit\AnalyticsBundle\EventSubscriber\PageViewSubscriber;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\CollectRateLimiter;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use Jenssmit\AnalyticsBundle\Service\SchemaProvider;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use Jenssmit\AnalyticsBundle\Service\UserAgentParser;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    // Basis-Services ohne Argumente.
    $services->set(IpAnonymizer::class);
    $services->set(PathNormalizer::class);
    $services->set(ReferrerNormalizer::class);
    $services->set(UserAgentParser::class);
    $services->set(SchemaProvider::class);
    $services->set(PageViewRepository::class);

    $services->set(PageViewRecorder::class)
        ->arg('$siteKey', '%jenssmit_analytics.site_key%')
        ->arg('$salt', '%jenssmit_analytics.salt%')
        ->arg('$excludedPaths', '%jenssmit_analytics.excluded_paths%')
        ->arg('$recordUserIdentifier', '%jenssmit_analytics.record_user_identifier%');

    $services->set(PageViewSubscriber::class)
        ->arg('$serverSideTracking', '%jenssmit_analytics.server_side_tracking%')
        ->arg('$trackOnlySuccessful', '%jenssmit_analytics.track_only_successful%');

    $services->set(StatsService::class)
        ->arg('$siteKey', '%jenssmit_analytics.site_key%');

    $services->set(CollectRateLimiter::class)
        ->arg('$cache', new Reference('cache.app'))
        ->arg('$lockFactory', new Reference('jenssmit_analytics.lock.factory'))
        ->arg('$limit', '%jenssmit_analytics.collect_rate_limit%');

    $services->set('jenssmit_analytics.lock.factory', LockFactory::class)
        ->arg('$store', new Reference('jenssmit_analytics.lock.store'));

    $services->set('jenssmit_analytics.lock.store', FlockStore::class);

    $services->set(CollectController::class)
        ->arg('$allowedOrigins', '%jenssmit_analytics.allowed_origins%')
        ->arg('$maxRequestSize', '%jenssmit_analytics.collect_max_request_size%')
        ->tag('controller.service_arguments');

    $services->set(StatsController::class)
        ->arg('$statsRole', '%jenssmit_analytics.stats_role%')
        ->tag('controller.service_arguments');

    $services->set(InstallCommand::class);
    $services->set(PurgeCommand::class)
        ->arg('$retentionDays', '%jenssmit_analytics.retention_days%')
        ->arg('$siteKey', '%jenssmit_analytics.site_key%');
};
