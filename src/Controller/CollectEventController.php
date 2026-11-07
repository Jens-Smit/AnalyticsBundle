<?php

namespace Jenssmit\AnalyticsBundle\Controller;

use Jenssmit\AnalyticsBundle\Service\AnalyticsEventRecorder;
use Jenssmit\AnalyticsBundle\Service\CollectRequestGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentlicher Erfassungsendpunkt für Custom Events.
 *
 * Dieselben Schutzschichten wie der Seitenaufruf-Collect (Origin,
 * Request-Größe, Content-Type, atomares Rate-Limiting) über den
 * gemeinsamen CollectRequestGuard. Event-Namen werden streng
 * validiert, Werte nur bei aktivierter Option gespeichert; es gibt
 * keine freie JSON-Payload und keine User-Properties.
 */
#[AsController]
#[Route('/collect/event', name: 'jenssmit_analytics_collect_event', methods: ['POST'])]
final class CollectEventController
{
    public function __construct(
        private readonly AnalyticsEventRecorder $recorder,
        private readonly CollectRequestGuard $guard,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $guardResponse = $this->guard->guard($request);

        if (null !== $guardResponse) {
            return $guardResponse;
        }

        $content = $request->getContent();
        $payload = '' === $content ? [] : json_decode($content, true);

        if (!\is_array($payload)) {
            return $this->guard->withCorsHeaders(
                new Response(status: Response::HTTP_BAD_REQUEST),
                $request,
            );
        }

        if (!$this->recorder->record($request, $payload)) {
            return $this->guard->withCorsHeaders(
                new Response(status: Response::HTTP_BAD_REQUEST),
                $request,
            );
        }

        return $this->guard->withCorsHeaders(new Response(status: Response::HTTP_NO_CONTENT), $request);
    }
}
