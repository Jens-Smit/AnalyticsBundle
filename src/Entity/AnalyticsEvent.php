<?php

namespace Jenssmit\AnalyticsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ein einzelnes, datensparsam erfasstes Custom Event.
 *
 * Gespeichert werden ausschließlich: Event-Name, optional eine
 * Kategorie, der normalisierte Pfad, ein optionaler validierter Wert,
 * ein tagesbezogenes Besucher-Pseudonym und der UTC-Zeitpunkt.
 *
 * Nicht gespeichert werden: rohe IP-Adressen, User-Agent-Zeichenketten,
 * freie JSON-Payloads oder User-Properties.
 */
#[ORM\Entity]
#[ORM\Table(name: 'jenssmit_analytics_event')]
#[ORM\Index(name: 'idx_jenssmit_event_site_occurred', columns: ['site_key', 'occurred_at'])]
class AnalyticsEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        #[ORM\GeneratedValue]
        private ?int $id = null,
        #[ORM\Column(name: 'site_key', length: 32)]
        private readonly ?string $siteKey = null,
        #[ORM\Column(name: 'event_name', length: 80)]
        private readonly ?string $eventName = null,
        #[ORM\Column(name: 'event_category', length: 64, nullable: true)]
        private readonly ?string $eventCategory = null,
        #[ORM\Column(name: 'path', length: 500)]
        private readonly ?string $path = null,
        #[ORM\Column(name: 'event_value', length: 100, nullable: true)]
        private readonly ?string $eventValue = null,
        #[ORM\Column(name: 'visitor_hash', length: 64, nullable: true)]
        private readonly ?string $visitorHash = null,
        #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
        private readonly ?\DateTimeImmutable $occurredAt = null,
    ) {
    }

    public static function create(
        string $siteKey,
        string $eventName,
        string $path,
        \DateTimeImmutable $occurredAt,
        ?string $eventCategory,
        ?string $eventValue,
        ?string $visitorHash,
    ): self {
        return new self(
            null,
            $siteKey,
            $eventName,
            $eventCategory,
            $path,
            $eventValue,
            $visitorHash,
            $occurredAt,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteKey(): string
    {
        return (string) $this->siteKey;
    }

    public function getEventName(): string
    {
        return (string) $this->eventName;
    }

    public function getEventCategory(): ?string
    {
        return $this->eventCategory;
    }

    public function getPath(): string
    {
        return (string) $this->path;
    }

    public function getEventValue(): ?string
    {
        return $this->eventValue;
    }

    public function getVisitorHash(): ?string
    {
        return $this->visitorHash;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
