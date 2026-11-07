<?php

namespace Jenssmit\AnalyticsBundle\Command;

use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Löscht abgelaufene Analytics-Datensätze.
 *
 * Standard: Dry-Run für genau den konfigurierten Mandanten, mit --force
 * wird tatsächlich gelöscht. Mit --all-sites wird der Löschvorgang
 * ausdrücklich auf alle Mandaten ausgeweitet.
 *
 * Die Löschung erfolgt mandantenbezogen in begrenzten Batches; Logs
 * enthalten keine personenbezogenen Daten, nur Anzahl und Zeitpunkt.
 */
#[AsCommand(
    name: 'analytics:purge',
    description: 'Löscht Analytics-Datensätze, die älter als die Aufbewahrungsfrist sind (Standard: Dry-Run).',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $repository,
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

        $before = (new \DateTimeImmutable())->modify(\sprintf('-%d days', $days));
        $scope = $all
            ? 'alle Mandanten'
            : \sprintf('Mandant "%s"', $this->siteKey);

        if (!$force) {
            $affected = $all
                ? $this->repository->countBeforeAll($before)
                : $this->repository->countBeforeForSite($this->siteKey, $before);

            $output->writeln(\sprintf(
                '<info>Dry-Run für %s: %d Datensätze sind älter als %s und würden gelöscht.</info>',
                $scope,
                $affected,
                $before->format('Y-m-d'),
            ));
            $output->writeln('<comment>Mit --force wird tatsächlich gelöscht.</comment>');

            return Command::SUCCESS;
        }

        $startTime = microtime(true);
        $deleted = $all
            ? $this->repository->deleteBeforeAll($before)
            : $this->repository->deleteBeforeForSite($this->siteKey, $before);
        $duration = round(microtime(true) - $startTime, 2);

        $output->writeln(\sprintf(
            '<info>%s: %d Datensätze bis %s gelöscht (Batch-Löschung, %.2f Sekunden).</info>',
            $scope,
            $deleted,
            $before->format('Y-m-d'),
            $duration,
        ));

        return Command::SUCCESS;
    }
}
