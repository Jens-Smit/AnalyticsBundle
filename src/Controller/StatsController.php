<?php

namespace Jenssmit\AnalyticsBundle\Controller;

use Jenssmit\AnalyticsBundle\Service\StatsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsController]
#[Route('/stats')]
final class StatsController
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly string $statsRole,
    ) {
    }

    #[Route('/summary', name: 'jenssmit_analytics_stats_summary', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->summary($this->positiveIntParam($request, 'days', 30)));
    }

    #[Route('/timeseries', name: 'jenssmit_analytics_stats_timeseries', methods: ['GET'])]
    public function timeseries(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->timeseries($this->positiveIntParam($request, 'days', 30)));
    }

    #[Route('/top-pages', name: 'jenssmit_analytics_stats_top_pages', methods: ['GET'])]
    public function topPages(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->topPages(
            $this->positiveIntParam($request, 'limit', 10),
            $this->positiveIntParam($request, 'days', 30)
        ));
    }

    #[Route('/referrers', name: 'jenssmit_analytics_stats_referrers', methods: ['GET'])]
    public function referrers(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->referrers(
            $this->positiveIntParam($request, 'limit', 10),
            $this->positiveIntParam($request, 'days', 30)
        ));
    }

    #[Route('/browsers', name: 'jenssmit_analytics_stats_browsers', methods: ['GET'])]
    public function browsers(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->browsers(
            $this->positiveIntParam($request, 'limit', 10),
            $this->positiveIntParam($request, 'days', 30)
        ));
    }

    #[Route('/devices', name: 'jenssmit_analytics_stats_devices', methods: ['GET'])]
    public function devices(Request $request): JsonResponse
    {
        $this->assertAccess();

        return new JsonResponse($this->stats->devices(
            $this->positiveIntParam($request, 'limit', 10),
            $this->positiveIntParam($request, 'days', 30)
        ));
    }

    private function assertAccess(): void
    {
        if (false === $this->authorizationChecker->isGranted($this->statsRole)) {
            throw new AccessDeniedHttpException('Zugriff auf die Statistik-Endpunkte verweigert.');
        }
    }

    private function positiveIntParam(Request $request, string $name, int $default): int
    {
        $value = $request->query->get($name);
        if (!\is_string($value) || !ctype_digit($value)) {
            return $default;
        }

        return max(1, min((int) $value, 366));
    }
}
