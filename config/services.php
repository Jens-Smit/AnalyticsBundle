<?php

namespace Jenssmit\AnalyticsBundle\DependencyInjection;

use Jenssmit\AnalyticsBundle\Command\InstallCommand;
use Jenssmit\AnalyticsBundle\Command\PurgeCommand;
use Jenssmit\AnalyticsBundle\Controller\CollectController;
use Jenssmit\AnalyticsBundle\Controller\StatsController;
use Jenssmit\AnalyticsBundle\EventSubscriber\PageViewSubscriber;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use Jenssmit\AnalyticsBundle\Service\UserAgentParser;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(UserAgentParser::class);

    $services->set(PageViewRepository::class);

    $services->set(PageViewRecorder::class)
        ->arg('$siteKey', '%jenssmit_analytics.site_key%')
        ->arg('$salt', '%jenssmit_analytics.salt%')
        ->arg('$excludedPaths', '%jenssmit_analytics.excluded_paths%')
        ->arg('$recordUserIdentifier', '%jenssmit_analytics.record_user_identifier%');

    $services->set(PageViewSubscriber::class)
        ->arg('$serverSideTracking', '%jenssmit_analytics.server_side_tracking%')
        ->arg('$trackOnlySuccessful', '%jenssmit_analytics.track_only_successful%');

    $services->set(StatsService::class);

    $services->set(CollectController::class)
        ->arg('$allowedOrigins', '%jenssmit_analytics.allowed_origins%')
        ->tag('controller.service_arguments');

    $services->set(StatsController::class)
        ->arg('$statsRole', '%jenssmit_analytics.stats_role%')
        ->tag('controller.service_arguments');

    $services->set(InstallCommand::class);

    $services->set(PurgeCommand::class)
        ->arg('$retentionDays', '%jenssmit_analytics.retention_days%');
};
