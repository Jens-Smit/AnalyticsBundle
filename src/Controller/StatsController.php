<?php

namespace Jenssmit\AnalyticsBundle\Controller;

use Jenssmit\AnalyticsBundle\Service\StatsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Statistik- und Export-Endpunkte. Alle Endpunkte erfordern die
 * konfigurierte Rolle, liefern ausschließlich Daten des konfigurierten
 * Mandanten und nur aggregierte Werte: keine IP-Hashes, keine
 * Login-Identifier, keine vollständigen User-Agents.
 */
#[AsController]
#[Route('/stats')]
final class StatsController
{
    private const EXPORT_REPORTS = ['summary', 'timeseries', 'top-pages', 'referrers', 'browsers', 'devices', 'sources', 'events'];

    public function __construct(
        private readonly StatsService $stats,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $statsRole,
        private readonly int $maxReportDays,
    ) {
    }

    #[Route('/summary', name: 'jenssmit_analytics_stats_summary', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->summary($this->daysParam($request)));
    }

    #[Route('/timeseries', name: 'jenssmit_analytics_stats_timeseries', methods: ['GET'])]
    public function timeseries(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->timeseries($this->daysParam($request)));
    }

    #[Route('/top-pages', name: 'jenssmit_analytics_stats_top_pages', methods: ['GET'])]
    public function topPages(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->topPages(
            $this->limitParam($request),
            $this->daysParam($request)
        ));
    }

    #[Route('/referrers', name: 'jenssmit_analytics_stats_referrers', methods: ['GET'])]
    public function referrers(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->referrers(
            $this->limitParam($request),
            $this->daysParam($request)
        ));
    }

    #[Route('/browsers', name: 'jenssmit_analytics_stats_browsers', methods: ['GET'])]
    public function browsers(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->browsers(
            $this->limitParam($request),
            $this->daysParam($request)
        ));
    }

    #[Route('/devices', name: 'jenssmit_analytics_stats_devices', methods: ['GET'])]
    public function devices(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->devices(
            $this->limitParam($request),
            $this->daysParam($request)
        ));
    }

    #[Route('/sources', name: 'jenssmit_analytics_stats_sources', methods: ['GET'])]
    public function sources(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->sources(
            $this->limitParam($request),
            $this->daysParam($request)
        ));
    }

    #[Route('/events', name: 'jenssmit_analytics_stats_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->events(
            $this->limitParam($request),
            $this->daysParam($request)
        ));
    }

    /**
     * Export aggregierter Reports als CSV oder JSON. Es werden nur
     * aggregierte Daten ausgeliefert, mandantenbezogen und mit denselben
     * Rechten wie die Statistik-Endpunkte. CSV wird gestreamt, damit
     * große Exporte nicht vollständig im Speicher entstehen.
     */
    #[Route('/export', name: 'jenssmit_analytics_stats_export', methods: ['GET'])]
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->assertAccess();

        $report = $request->query->get('report');
        $format = $request->query->get('format', 'csv');

        if (!\in_array($report, self::EXPORT_REPORTS, true)) {
            throw new BadRequestHttpException('Unbekannter Report.');
        }

        if (!\in_array($format, ['csv', 'json'], true)) {
            throw new BadRequestHttpException('Erlaubte Formate: csv, json.');
        }

        $days = $this->daysParam($request);
        $limit = $this->limitParam($request);

        $data = match ($report) {
            'summary' => $this->stats->summary($days),
            'timeseries' => $this->stats->timeseries($days),
            'top-pages' => $this->stats->topPages($limit, $days),
            'referrers' => $this->stats->referrers($limit, $days),
            'browsers' => $this->stats->browsers($limit, $days),
            'devices' => $this->stats->devices($limit, $days),
            'sources' => $this->stats->sources($limit, $days),
            'events' => $this->stats->events($limit, $days),
        };

        if ('json' === $format) {
            return new JsonResponse($data);
        }

        $rows = $this->flattenForCsv($data);

        return new StreamedResponse(static function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if (false === $handle) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '\\');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="analytics-'.$report.'.csv"',
        ]);
    }

    /**
     * @return list<list<string>>
     */
    private function flattenForCsv(array $data): array
    {
        $rows = [];
        $inner = $data['data'] ?? [];

        if (\array_is_list($inner)) {
            foreach ($inner as $item) {
                if (\is_array($item)) {
                    $rows[] = array_map(
                        static fn ($value): string => \is_scalar($value) ? (string) $value : (string) json_encode($value),
                        $item,
                    );
                }
            }

            return $rows;
        }

        foreach ($inner as $key => $value) {
            $rows[] = [
                (string) $key,
                \is_scalar($value) ? (string) $value : (string) json_encode($value),
            ];
        }

        return $rows;
    }

    private function assertAccess(): void
    {
        if (false === $this->authorizationChecker->isGranted($this->statsRole)) {
            throw new AccessDeniedHttpException('Zugriff auf die Statistik-Endpunkte verweigert.');
        }
    }

    private function daysParam(Request $request): int
    {
        return $this->positiveIntParam($request, 'days', 30, $this->maxReportDays);
    }

    private function limitParam(Request $request): int
    {
        return $this->positiveIntParam($request, 'limit', 10, 100);
    }

    private function positiveIntParam(Request $request, string $name, int $default, int $max): int
    {
        $value = $request->query->get($name);

        if (!\is_string($value) || !ctype_digit($value)) {
            return $default;
        }

        return max(1, min((int) $value, $max));
    }
}
