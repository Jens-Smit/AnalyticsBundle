<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Entity\AnalyticsEvent;
use Jenssmit\AnalyticsBundle\Repository\AnalyticsEventRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Erfassung eines Custom Events mit denselben Datenschutzregeln wie
 * der Seitenaufruf: Bots werden ausgeschlossen, DNT und Sec-GPC werden
 * respektiert, Pfade normalisiert, das Besucher-Pseudonym ist
 * tagesbezogen. Event-Namen werden streng validiert, Werte nur bei
 * aktivierter Option gespeichert.
 */
class AnalyticsEventRecorder
{
    public function __construct(
        private readonly AnalyticsEventRepository $repository,
        private readonly BotDetector $botDetector,
        private readonly PathNormalizer $pathNormalizer,
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly EventNameValidator $eventNameValidator,
        private readonly EventValueNormalizer $eventValueNormalizer,
        private readonly SystemClock $clock,
        private readonly string $siteKey,
        private readonly string $hashSecret,
        private readonly bool $recordEventValues,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(Request $request, array $payload): bool
    {
        $name = $payload['name'] ?? null;

        if (!\is_string($name) || '' === $name || !$this->eventNameValidator->isValidName($name)) {
            return false;
        }

        $category = $payload['category'] ?? null;

        if (\is_string($category) && !$this->eventNameValidator->isValidCategory($category)) {
            return false;
        }

        if (\is_string($category)) {
            $category = mb_substr($category, 0, EventNameValidator::MAX_CATEGORY_LENGTH);
        } else {
            $category = null;
        }

        if ($this->botDetector->isBot($request->headers->get('User-Agent'))) {
            return false;
        }

        if ('1' === $request->headers->get('DNT') || '1' === $request->headers->get('Sec-GPC')) {
            return false;
        }

        $path = $this->pathNormalizer->normalize(
            \is_string($payload['path'] ?? null) ? $payload['path'] : $request->getPathInfo(),
        );

        if (null === $path) {
            return false;
        }

        $value = null;
        if ($this->recordEventValues) {
            $value = $this->eventValueNormalizer->normalize($payload['value'] ?? null);
        }

        $now = $this->clock->now();
        $anonymizedIp = $this->ipAnonymizer->anonymize($request->getClientIp());
        $visitorHash = 'unknown' === $anonymizedIp
            ? null
            : $this->ipAnonymizer->hash($anonymizedIp, $now, $this->hashSecret);

        $this->repository->save(AnalyticsEvent::create(
            $this->siteKey,
            $name,
            $path,
            $now,
            $category,
            $value,
            $visitorHash,
        ));

        return true;
    }
}
