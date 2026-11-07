<?php

namespace Jenssmit\AnalyticsBundle\Controller;

use Jenssmit\AnalyticsBundle\Service\CollectRateLimiter;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentlicher Erfassungsendpunkt.
 *
 * Schutzschichten in fester Reihenfolge:
 * 1. Origin-Prüfung (nur für Browser-Clients relevant, keine
 *    Authentifizierung),
 * 2. Request-Größenlimit,
 * 3. Content-Type-Prüfung,
 * 4. Rate-Limiting je anonymisiertem IP-Präfix,
 * 5. strikte Payload-Validierung.
 *
 * Fehlerantworten sind bewusst generisch und verraten keine
 * internen Details.
 */
#[AsController]
#[Route('/collect', name: 'jenssmit_analytics_collect', methods: ['POST'])]
final class CollectController
{
    private const ALLOWED_CONTENT_TYPES = ['application/json', 'text/plain'];

    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        private readonly PageViewRecorder $recorder,
        private readonly CollectRateLimiter $rateLimiter,
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly PathNormalizer $pathNormalizer,
        private readonly ReferrerNormalizer $referrerNormalizer,
        private readonly array $allowedOrigins,
        private readonly int $maxRequestSize,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->isOriginAllowed($request)) {
            return $this->error('origin not allowed', $request, Response::HTTP_FORBIDDEN);
        }

        $contentLength = $request->headers->get('Content-Length');
        if ((null !== $contentLength && '' !== $contentLength && (int) $contentLength > $this->maxRequestSize)
            || \strlen($request->getContent()) > $this->maxRequestSize) {
            return $this->error('payload too large', $request, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (!$this->isContentTypeAllowed($request)) {
            return $this->error('unsupported content type', $request, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        if (!$this->rateLimiter->allow($this->ipAnonymizer->anonymize($request->getClientIp()), new \DateTimeImmutable())) {
            $response = $this->error('too many requests', $request, Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $this->secondsUntilNextWindow(new \DateTimeImmutable()));

            return $response;
        }

        $payload = $this->extractPayload($request);
        if (null === $payload) {
            return $this->error('invalid payload', $request, Response::HTTP_BAD_REQUEST);
        }

        $rawPath = $payload['path'] ?? null;
        if (!\is_string($rawPath)) {
            return $this->error('invalid path', $request, Response::HTTP_BAD_REQUEST);
        }

        $path = $this->pathNormalizer->normalize($rawPath);
        if (null === $path) {
            return $this->error('invalid path', $request, Response::HTTP_BAD_REQUEST);
        }

        $referrer = null;
        if (\is_string($payload['referrer'] ?? null) && '' !== $payload['referrer']) {
            $referrer = $this->referrerNormalizer->toHost($payload['referrer']);
        }

        $this->recorder->record($request, $path, $referrer);

        return $this->withCorsHeaders(new Response(status: Response::HTTP_NO_CONTENT), $request);
    }

    private function error(string $message, Request $request, int $status): Response
    {
        return $this->withCorsHeaders(new JsonResponse(['error' => $message], $status), $request);
    }

    /**
     * Setzt Cache-Korrektheits- und CORS-Header. "Vary: Origin"
     * verhindert, dass Antworten für verschiedene Origins falsch
     * wiederverwendet werden. Der Allow-Origin-Header wird nur für
     * ausdrücklich erlaubte, explizit genannte Origins gesetzt.
     */
    private function withCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->headers->get('Origin');

        if (null === $origin || '' === $origin) {
            return $response;
        }

        $response->headers->set('Vary', 'Origin');

        if ($request->getSchemeAndHttpHost() !== $origin && $this->isOriginAllowed($request)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }

    /**
     * Origin ist kein Authentifizierungsmechanismus: Nicht-browserbasierte
     * Clients können jeden Headerwert setzen. Für sie greifen
     * Rate-Limiting und Validierung. Fehlende Origins werden deshalb
     * nicht blockiert, unbekannte Origins hingegen strikt.
     */
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

    private function isContentTypeAllowed(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type');

        if (null === $contentType || '' === $contentType) {
            return true;
        }

        $mimePart = strstr($contentType, ';', true);
        $mime = strtolower(trim(false === $mimePart ? $contentType : (string) $mimePart));

        return \in_array($mime, self::ALLOWED_CONTENT_TYPES, true);
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

    private function secondsUntilNextWindow(\DateTimeImmutable $now): int
    {
        return max(1, 3600 - ((int) $now->format('i')) * 60 - (int) $now->format('s'));
    }
}
