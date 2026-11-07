<?php

namespace Jenssmit\AnalyticsBundle\Command;

use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'analytics:purge',
    description: 'Löscht Seitenaufrufe, die älter als die Aufbewahrungsdauer sind.'
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $repository,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Aufbewahrungsdauer in Tagen (überschreibt die Bundle-Konfiguration)',
                (string) $this->retentionDays
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Löscht tatsächlich, statt nur anzuzeigen, was gelöscht würde.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = max(1, (int) $input->getOption('days'));
        $before = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        if (!$input->getOption('force')) {
            $affected = $this->repository->countBefore($before);
            $output->writeln(sprintf(
                '<info>Derzeitige Aufbewahrungsdauer: %d Tage. %d Datensätze wären älter als %s und würden gelöscht. Mit --force ausführen.</info>',
                $days,
                $affected,
                $before->format('Y-m-d')
            ));

            return Command::SUCCESS;
        }

        $deleted = $this->repository->deleteBefore($before);
        $output->writeln(sprintf(
            '<info>%d Datensätze gelöscht (älter als %s).</info>',
            $deleted,
            $before->format('Y-m-d')
        ));

        return Command::SUCCESS;
    }
}
