<?php

namespace Jenssmit\AnalyticsBundle\Command;

use Jenssmit\AnalyticsBundle\Repository\AnalyticsEventRepository;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\StatsService;
use Jenssmit\AnalyticsBundle\Service\SystemClock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Löscht abgelaufene Analytics-Datensätze (PageViews und Events).
 *
 * Standard: Dry-Run für genau den konfigurierten Mandanten, mit --force
 * wird tatsächlich gelöscht. Mit --all-sites wird der Löschvorgang
 * ausdrücklich auf alle Mandanten ausgeweitet.
 *
 * Die Löschung erfolgt mandantenbezogen in begrenzten Batches; Logs
 * enthalten keine personenbezogenen Daten, nur Anzahl und Zeitpunkt.
 * Nach dem Löschen wird die Report-Generation erhöht, damit der
 * optionale Report-Cache keine veralteten Aggregate ausliefert.
 */
#[AsCommand(
    name: 'analytics:purge',
    description: 'Löscht Analytics-Datensätze, die älter als die Aufbewahrungsfrist sind (Standard: Dry-Run).',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $repository,
        private readonly AnalyticsEventRepository $eventRepository,
        private readonly StatsService $statsService,
        private readonly SystemClock $clock,
        private readonly int $retentionDays,
        private readonly string $siteKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Aufbewahrungsfrist in Tagen.', (string) $this->retentionDays)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Löscht tatsächlich, statt nur zu zählen.')
            ->addOption('all-sites', null, InputOption::VALUE_NONE, 'Betrifft alle Mandanten statt nur den konfigurierten Site-Key.')
            ->setHelp(
                'Beispiele:'."\n"
                .'  php bin/console analytics:purge'."\n"
                .'  php bin/console analytics:purge --days=30 --force'."\n"
                .'  php bin/console analytics:purge --force --all-sites'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = max(1, (int) $input->getOption('days'));
        $all = (bool) $input->getOption('all-sites');
        $force = (bool) $input->getOption('force');

        $before = $this->clock->now()->modify(\sprintf('-%d days', $days));
        $scope = $all
            ? 'alle Mandanten'
            : \sprintf('Mandant "%s"', $this->siteKey);

        if (!$force) {
            $pageViews = $all
                ? $this->repository->countBeforeAll($before)
                : $this->repository->countBeforeForSite($this->siteKey, $before);
            $events = $all
                ? $this->eventRepository->countBeforeAll($before)
                : $this->eventRepository->countBeforeForSite($this->siteKey, $before);

            $output->writeln(\sprintf(
                '<info>Dry-Run für %s: %d PageViews und %d Events sind älter als %s und würden gelöscht.</info>',
                $scope,
                $pageViews,
                $events,
                $before->format('Y-m-d'),
            ));
            $output->writeln('<comment>Mit --force wird tatsächlich gelöscht.</comment>');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $deletedPageViews = $all
            ? $this->repository->deleteBeforeAll($before)
            : $this->repository->deleteBeforeForSite($this->siteKey, $before);
        $deletedEvents = $all
            ? $this->eventRepository->deleteBeforeAll($before)
            : $this->eventRepository->deleteBeforeForSite($this->siteKey, $before);
        $duration = round(microtime(true) - $startTime, 2);

        $this->statsService->invalidateReportCache();

        $output->writeln(\sprintf(
            '<info>%s: %d PageViews und %d Events bis %s gelöscht (Batch-Löschung, %.2f Sekunden).</info>',
            $scope,
            $deletedPageViews,
            $deletedEvents,
            $before->format('Y-m-d'),
            $duration,
        ));

        return Command::SUCCESS;
    }
}
