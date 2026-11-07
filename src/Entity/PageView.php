<?php

namespace Jenssmit\AnalyticsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ein einzelner, datensparsam erfasster Seitenaufruf. Es werden bewusst
 * keine IP-Adressen, keine vollständigen Referrer und keine
 * User-Agent-Zeichenketten gespeichert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'jenssmit_page_view')]
#[ORM\Index(name: 'idx_jenssmit_page_view_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_jenssmit_page_view_site_created', columns: ['site_key', 'created_at'])]
class PageView
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        #[ORM\GeneratedValue]
        private ?int $id = null,
        #[ORM\Column(name: 'site_key', length: 32)]
        private readonly ?string $siteKey = null,
        #[ORM\Column(name: 'path', length: 500)]
        private readonly ?string $path = null,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private readonly ?\DateTimeImmutable $createdAt = null,
        #[ORM\Column(name: 'route', length: 190, nullable: true)]
        private readonly ?string $route = null,
        #[ORM\Column(name: 'referrer', length: 255, nullable: true)]
        private readonly ?string $referrer = null,
        #[ORM\Column(name: 'browser', length: 32, nullable: true)]
        private readonly ?string $browser = null,
        #[ORM\Column(name: 'operating_system', length: 32, nullable: true)]
        private readonly ?string $operatingSystem = null,
        #[ORM\Column(name: 'device', length: 16, nullable: true)]
        private readonly ?string $device = null,
        #[ORM\Column(name: 'ip_hash', length: 64, nullable: true)]
        private readonly ?string $ipHash = null,
        #[ORM\Column(name: 'user_identifier', length: 191, nullable: true)]
        private readonly ?string $userIdentifier = null,
        #[ORM\Column(name: 'source', length: 16, nullable: true)]
        private readonly ?string $source = null,
        #[ORM\Column(name: 'utm_source', length: 100, nullable: true)]
        private readonly ?string $utmSource = null,
        #[ORM\Column(name: 'utm_medium', length: 50, nullable: true)]
        private readonly ?string $utmMedium = null,
        #[ORM\Column(name: 'utm_campaign', length: 100, nullable: true)]
        private readonly ?string $utmCampaign = null,
    ) {
    }

    public static function create(
        string $siteKey,
        string $path,
        \DateTimeImmutable $createdAt,
        ?string $route,
        ?string $referrer,
        ?string $browser,
        ?string $operatingSystem,
        ?string $device,
        ?string $ipHash,
        ?string $userIdentifier,
        ?string $source = null,
        ?string $utmSource = null,
        ?string $utmMedium = null,
        ?string $utmCampaign = null,
    ): self {
        return new self(
            null,
            $siteKey,
            $path,
            $createdAt,
            $route,
            $referrer,
            $browser,
            $operatingSystem,
            $device,
            $ipHash,
            $userIdentifier,
            $source,
            $utmSource,
            $utmMedium,
            $utmCampaign,
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

    public function getPath(): string
    {
        return (string) $this->path;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt ?? new \DateTimeImmutable();
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function getOperatingSystem(): ?string
    {
        return $this->operatingSystem;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    /**
     * Tagesbezogenes Pseudonym aus dem gekürzten IP-Präfix. Keine
     * anonyme Kennung, siehe README.
     */
    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    /**
     * Nur aktiv, wenn record_user_identifier explizit aktiviert wurde.
     */
    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    /**
     * Traffic-Quelle: direct, search, social, internal, referral oder
     * campaign. Alte Datensätze ohne Quelle haben null.
     */
    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getUtmSource(): ?string
    {
        return $this->utmSource;
    }

    public function getUtmMedium(): ?string
    {
        return $this->utmMedium;
    }

    public function getUtmCampaign(): ?string
    {
        return $this->utmCampaign;
    }
}
