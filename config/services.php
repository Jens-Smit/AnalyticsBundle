<?php

namespace Jenssmit\AnalyticsBundle\DependencyInjection;

use Jenssmit\AnalyticsBundle\Command\InstallCommand;
use Jenssmit\AnalyticsBundle\Command\PurgeCommand;
use Jenssmit\AnalyticsBundle\Controller\CollectController;
use Jenssmit\AnalyticsBundle\Controller\CollectEventController;
use Jenssmit\AnalyticsBundle\Controller\StatsController;
use Jenssmit\AnalyticsBundle\EventSubscriber\PageViewSubscriber;
use Jenssmit\AnalyticsBundle\MessageHandler\RecordPageViewHandler;
use Jenssmit\AnalyticsBundle\Repository\AnalyticsEventRepository;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\AnalyticsEventRecorder;
use Jenssmit\AnalyticsBundle\Service\BotDetector;
use Jenssmit\AnalyticsBundle\Service\CollectRateLimiter;
use Jenssmit\AnalyticsBundle\Service\CollectRequestGuard;
use Jenssmit\AnalyticsBundle\Service\CampaignParameterNormalizer;
use Jenssmit\AnalyticsBundle\Service\EventNameValidator;
use Jenssmit\AnalyticsBundle\Service\EventValueNormalizer;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use Jenssmit\AnalyticsBundle\Service\SchemaProvider;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use Jenssmit\AnalyticsBundle\Service\SystemClock;
use Jenssmit\AnalyticsBundle\Service\TrafficSourceClassifier;
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
    $services->set(SystemClock::class);
    $services->set(EventNameValidator::class);
    $services->set(EventValueNormalizer::class);
    $services->set(PageViewRepository::class);
    $services->set(AnalyticsEventRepository::class);

    $services->set(BotDetector::class)
        ->arg('$additionalPatterns', '%jenssmit_analytics.bot_patterns%')
        ->arg('$exceptions', '%jenssmit_analytics.bot_exceptions%');

    $services->set(CampaignParameterNormalizer::class)
        ->arg('$allowlist', '%jenssmit_analytics.campaign_allowlist%');

    $services->set(TrafficSourceClassifier::class);

    $services->set(PageViewRecorder::class)
        ->arg('$siteKey', '%jenssmit_analytics.site_key%')
        ->arg('$hashSecret', '%jenssmit_analytics.hash_secret%')
        ->arg('$excludedPaths', '%jenssmit_analytics.excluded_paths%')
        ->arg('$recordUserIdentifier', '%jenssmit_analytics.record_user_identifier%')
        ->arg('$excludeBots', '%jenssmit_analytics.exclude_bots%')
        ->arg('$respectDnt', '%jenssmit_analytics.respect_dnt%')
        ->arg('$respectGpc', '%jenssmit_analytics.respect_gpc%');

    $services->set(AnalyticsEventRecorder::class)
        ->arg('$siteKey', '%jenssmit_analytics.site_key%')
        ->arg('$hashSecret', '%jenssmit_analytics.hash_secret%')
        ->arg('$recordEventValues', '%jenssmit_analytics.record_event_values%');

    $services->set(PageViewSubscriber::class)
        ->arg('$pageViewMode', '%jenssmit_analytics.page_view_mode%')
        ->arg('$trackOnlySuccessful', '%jenssmit_analytics.track_only_successful%');

    $services->set(StatsService::class)
        ->arg('$siteKey', '%jenssmit_analytics.site_key%')
        ->arg('$cache', new Reference('cache.app'))
        ->arg('$cacheTtl', '%jenssmit_analytics.report_cache_ttl%');

    $services->set(CollectRateLimiter::class)
        ->arg('$cache', new Reference('cache.app'))
        ->arg('$lockFactory', new Reference('jenssmit_analytics.lock.factory'))
        ->arg('$limit', '%jenssmit_analytics.collect_rate_limit%');

    $services->set('jenssmit_analytics.lock.factory', LockFactory::class)
        ->arg('$store', new Reference('jenssmit_analytics.lock.store'));

    $services->set('jenssmit_analytics.lock.store', FlockStore::class);

    $services->set(CollectRequestGuard::class)
        ->arg('$allowedOrigins', '%jenssmit_analytics.allowed_origins%')
        ->arg('$maxRequestSize', '%jenssmit_analytics.collect_max_request_size%');

    $services->set(CollectController::class)
        ->tag('controller.service_arguments');

    $services->set(CollectEventController::class)
        ->tag('controller.service_arguments');

    $services->set(StatsController::class)
        ->arg('$statsRole', '%jenssmit_analytics.stats_role%')
        ->arg('$maxReportDays', '%jenssmit_analytics.max_report_days%')
        ->tag('controller.service_arguments');

    $services->set(InstallCommand::class);

    $services->set(PurgeCommand::class)
        ->arg('$retentionDays', '%jenssmit_analytics.retention_days%')
        ->arg('$siteKey', '%jenssmit_analytics.site_key%');

    if (\class_exists(\Symfony\Component\Messenger\MessageBusInterface::class)) {
        $services->set(RecordPageViewHandler::class)
            ->tag('messenger.message_handler');
    }
};
