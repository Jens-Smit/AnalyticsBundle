<?php

namespace Jenssmit\AnalyticsBundle\Controller;

use Jenssmit\AnalyticsBundle\Service\CollectRequestGuard;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentlicher Erfassungsendpunkt für Seitenaufrufe.
 *
 * Die Schutzschichten (Origin, Request-Größe, Content-Type, atomares
 * Rate-Limiting) liegen im CollectRequestGuard und sind für alle
 * Collect-Endpunkte identisch. Danach folgt die strikte
 * Payload-Validierung. Fehlerantworten sind bewusst generisch.
 */
#[AsController]
#[Route('/collect', name: 'jenssmit_analytics_collect', methods: ['POST'])]
final class CollectController
{
    public function __construct(
        private readonly PageViewRecorder $recorder,
        private readonly CollectRequestGuard $guard,
        private readonly PathNormalizer $pathNormalizer,
        private readonly ReferrerNormalizer $referrerNormalizer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $guardResponse = $this->guard->guard($request);

        if (null !== $guardResponse) {
            return $guardResponse;
        }

        $payload = $this->extractPayload($request);

        if (null === $payload) {
            return $this->guard->withCorsHeaders(
                new Response(status: Response::HTTP_BAD_REQUEST),
                $request,
            );
        }

        $rawPath = $payload['path'] ?? null;

        if (!\is_string($rawPath)) {
            return $this->guard->withCorsHeaders(
                new Response(status: Response::HTTP_BAD_REQUEST),
                $request,
            );
        }

        $path = $this->pathNormalizer->normalize($rawPath);

        if (null === $path) {
            return $this->guard->withCorsHeaders(
                new Response(status: Response::HTTP_BAD_REQUEST),
                $request,
            );
        }

        $referrer = null;
        if (\is_string($payload['referrer'] ?? null) && '' !== $payload['referrer']) {
            $referrer = $this->referrerNormalizer->toHost($payload['referrer']);
        }

        $campaign = null;
        if (\is_array($payload['campaign'] ?? null)) {
            $campaign = $payload['campaign'];
        }

        $this->recorder->record($request, $path, $referrer, $campaign);

        return $this->guard->withCorsHeaders(new Response(status: Response::HTTP_NO_CONTENT), $request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPayload(Request $request): ?array
    {
        $content = $request->getContent();

        if ('' === $content) {
            return null;
        }

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
