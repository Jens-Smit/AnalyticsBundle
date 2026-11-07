<?php

namespace Jenssmit\AnalyticsBundle\Message;

use Jenssmit\AnalyticsBundle\Entity\PageView;

/**
 * Nachricht für die asynchrone Speicherung eines normalisierten
 * Seitenaufrufs. Enthält ausschließlich bereits normalisierte Daten:
 * keine rohen IP-Adressen, keine User-Agent-Zeichenketten, keine
 * vollständigen Referrer-URLs und keine Query-Parameter.
 */
final class RecordPageViewMessage
{
    /**
     * @param \DateTimeImmutable $occurredAt UTC-Zeitpunkt des Aufrufs
     */
    public function __construct(
        public readonly string $siteKey,
        public readonly string $path,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $route,
        public readonly ?string $referrerHost,
        public readonly ?string $browser,
        public readonly ?string $operatingSystem,
        public readonly ?string $device,
        public readonly ?string $ipHash,
        public readonly ?string $userIdentifier,
        public readonly ?string $source,
        public readonly ?string $utmSource,
        public readonly ?string $utmMedium,
        public readonly ?string $utmCampaign,
    ) {
    }

    public function toPageView(): PageView
    {
        return PageView::create(
            $this->siteKey,
            $this->path,
            $this->occurredAt,
            $this->route,
            $this->referrerHost,
            $this->browser,
            $this->operatingSystem,
            $this->device,
            $this->ipHash,
            $this->userIdentifier,
            $this->source,
            $this->utmSource,
            $this->utmMedium,
            $this->utmCampaign,
        );
    }
}
