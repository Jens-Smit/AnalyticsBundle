<?php

namespace Jenssmit\AnalyticsBundle\Controller;

use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/collect', name: 'jenssmit_analytics_collect', methods: ['POST'])]
final class CollectController
{
    public function __construct(
        private readonly PageViewRecorder $recorder,
        private readonly array $allowedOrigins,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->isOriginAllowed($request)) {
            return new JsonResponse(['error' => 'origin not allowed'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->extractPayload($request);

        $path = $payload['path'] ?? null;
        if (!\is_string($path) || '' === $path || '/' !== $path[0]) {
            return new JsonResponse(['error' => 'invalid path'], Response::HTTP_BAD_REQUEST);
        }

        $referrer = $payload['referrer'] ?? null;
        $referrer = \is_string($referrer) && '' !== $referrer ? $referrer : null;

        $this->recorder->record($request, $path, $referrer);

        $response = new Response(status: Response::HTTP_NO_CONTENT);

        $origin = $request->headers->get('Origin');
        if (null !== $origin && $origin !== $request->getSchemeAndHttpHost()) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }

    private function isOriginAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        if (null === $origin || '' === $origin) {
            return true;
        }

        if (\in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        if ($origin === $request->getSchemeAndHttpHost()) {
            return true;
        }

        return \in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!\is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
