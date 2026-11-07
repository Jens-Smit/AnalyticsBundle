<?php

namespace Jenssmit\AnalyticsBundle\Entity;

final class PageView
{
    private ?int $id = null;

    private string $siteKey;

    private string $path;

    private ?string $route = null;

    private ?string $referrer = null;

    private ?string $userAgent = null;

    private ?string $browser = null;

    private ?string $operatingSystem = null;

    private ?string $device = null;

    private ?string $ipHash = null;

    private ?string $userIdentifier = null;

    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        string $siteKey,
        string $path,
        \DateTimeImmutable $createdAt,
        ?string $route = null,
        ?string $referrer = null,
        ?string $userAgent = null,
        ?string $browser = null,
        ?string $operatingSystem = null,
        ?string $device = null,
        ?string $ipHash = null,
        ?string $userIdentifier = null,
    ): self {
        $view = new self();
        $view->siteKey = $siteKey;
        $view->path = $path;
        $view->createdAt = $createdAt;
        $view->route = $route;
        $view->referrer = $referrer;
        $view->userAgent = $userAgent;
        $view->browser = $browser;
        $view->operatingSystem = $operatingSystem;
        $view->device = $device;
        $view->ipHash = $ipHash;
        $view->userIdentifier = $userIdentifier;

        return $view;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
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

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
