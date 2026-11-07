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
final class PageView
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        #[ORM\GeneratedValue]
        private ?int $id = null,
        #[ORM\Column(length: 32)]
        private readonly ?string $siteKey = null,
        #[ORM\Column(length: 500)]
        private readonly ?string $path = null,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly ?\DateTimeImmutable $createdAt = null,
        #[ORM\Column(length: 190, nullable: true)]
        private readonly ?string $route = null,
        #[ORM\Column(length: 255, nullable: true)]
        private readonly ?string $referrer = null,
        #[ORM\Column(length: 32, nullable: true)]
        private readonly ?string $browser = null,
        #[ORM\Column(length: 32, nullable: true)]
        private readonly ?string $operatingSystem = null,
        #[ORM\Column(length: 16, nullable: true)]
        private readonly ?string $device = null,
        #[ORM\Column(length: 64, nullable: true)]
        private readonly ?string $ipHash = null,
        #[ORM\Column(length: 191, nullable: true)]
        private readonly ?string $userIdentifier = null,
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
}
