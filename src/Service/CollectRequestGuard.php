<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gemeinsame Schutzschichten aller Collect-Endpunkte in fester
 * Reihenfolge: Origin-Prüfung, Request-Größenlimit, Content-Type-Prüfung
 * und atomares Rate-Limiting. Fehlerantworten sind bewusst generisch
 * und verraten keine internen Details.
 *
 * Der Origin-Header ist kein Authentifizierungsmechanismus:
 * Nicht-browserbasierte Clients können jeden Headerwert setzen. CORS
 * schützt Browser, nicht vor automatisiertem Missbrauch; dafür sind
 * Rate-Limiting und Validierung zuständig.
 */
class CollectRequestGuard
{
    private const ALLOWED_CONTENT_TYPES = ['application/json', 'text/plain'];

    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        private readonly CollectRateLimiter $rateLimiter,
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly SystemClock $clock,
        private readonly array $allowedOrigins,
        private readonly int $maxRequestSize,
    ) {
    }

    /**
     * Prüft eine Collect-Anfrage. Gibt null zurück, wenn die Anfrage
     * zulässig ist, andernfalls die Fehlerantwort.
     */
    public function guard(Request $request): ?Response
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

        $now = $this->clock->now();

        if (!$this->rateLimiter->allow($this->ipAnonymizer->anonymize($request->getClientIp()), $now)) {
            $response = $this->error('too many requests', $request, Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $this->secondsUntilNextWindow($now));

            return $response;
        }

        return null;
    }

    /**
     * Setzt Cache-Korrektheits- und CORS-Header. "Vary: Origin"
     * verhindert, dass Antworten für verschiedene Origins falsch
     * wiederverwendet werden. Der Allow-Origin-Header wird nur für
     * ausdrücklich erlaubte, explizit genannte Origins gesetzt.
     */
    public function withCorsHeaders(Response $response, Request $request): Response
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

    private function error(string $message, Request $request, int $status): Response
    {
        return $this->withCorsHeaders(new JsonResponse(['error' => $message], $status), $request);
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

    private function isContentTypeAllowed(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type');

        if (null === $contentType || '' === trim($contentType)) {
            return false;
        }

        $mimePart = strstr($contentType, ';', true);
        $mime = strtolower(trim(false === $mimePart ? $contentType : (string) $mimePart));

        return \in_array($mime, self::ALLOWED_CONTENT_TYPES, true);
    }

    private function secondsUntilNextWindow(\DateTimeImmutable $now): int
    {
        return max(1, 3600 - ((int) $now->format('i')) * 60 - (int) $now->format('s'));
    }
}
