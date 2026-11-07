<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PageViewRecorder
{
    public function __construct(
        private readonly PageViewRepository $repository,
        private readonly UserAgentParser $userAgentParser,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $siteKey,
        private readonly string $salt,
        private readonly array $excludedPaths,
        private readonly bool $recordUserIdentifier,
    ) {
    }

    /**
     * Zeichnet einen Seitenaufruf auf. Gibt false zurück, wenn der Pfad
     * über die Konfiguration ausgeschlossen ist.
     */
    public function record(Request $request, ?string $path = null, ?string $referrer = null): bool
    {
        $path ??= $request->getPathInfo();
        if ($this->isExcluded($path)) {
            return false;
        }

        $path = mb_substr($path, 0, 500);

        $route = $request->attributes->get('_route');
        $route = \is_string($route) && '' !== $route ? mb_substr($route, 0, 190) : null;

        $userAgent = $request->headers->get('User-Agent');
        $userAgent = null !== $userAgent && '' !== $userAgent ? mb_substr($userAgent, 0, 255) : null;

        $parsed = $this->userAgentParser->parse($userAgent);

        $referrer ??= $request->headers->get('Referer');
        $referrer = null !== $referrer && '' !== $referrer ? mb_substr($referrer, 0, 500) : null;

        $now = new \DateTimeImmutable();

        $pageView = PageView::create(
            $this->siteKey,
            $path,
            $now,
            $route,
            $referrer,
            $userAgent,
            $parsed['browser'],
            $parsed['operating_system'],
            $parsed['device'],
            $this->hashIp($request->getClientIp(), $now),
            $this->resolveUserIdentifier(),
        );

        $this->repository->save($pageView);

        return true;
    }

    public function isExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (\is_string($excludedPath) && '' !== $excludedPath && str_starts_with($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bildet einen anonymisierten Hash aus der gekürzten IP-Adresse, dem
     * Kalendertag und dem konfigurierten Salt. Aus dem Hash lässt sich die
     * IP-Adresse nicht rekonstruieren; er erlaubt lediglich die näherungsweise
     * Zählung wiederkehrender Besucher innerhalb eines Tages.
     */
    private function hashIp(?string $ip, \DateTimeImmutable $now): ?string
    {
        if (null === $ip || '' === $ip) {
            return null;
        }

        $anonymized = $this->anonymizeIp($ip);

        return hash('sha256', $anonymized.'|'.$now->format('Y-m-d').'|'.$this->salt);
    }

    private function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d{1,3}$/', '.0', $ip) ?? $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_replace('/:[0-9a-fA-F]{1,4}$/', ':0000', $ip) ?? $ip;
        }

        return 'unknown';
    }

    private function resolveUserIdentifier(): ?string
    {
        if (!$this->recordUserIdentifier) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return mb_substr($user->getUserIdentifier(), 0, 191);
    }
}
