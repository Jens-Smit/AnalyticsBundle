<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Erfassung eines Seitenaufrufs nach dem Datensparsamkeitsprinzip.
 *
 * Gespeichert werden ausschließlich: Pfad, Route, Referrer-Host,
 * Browser-, System- und Geräte-Klassifikation, tagesbezogenes
 * IP-Pseudonym und optional ein Login-Identifier.
 *
 * Nicht gespeichert werden: rohe IP-Adressen, User-Agent-Zeichenketten,
 * vollständige Referrer-URLs, Query-Parameter oder Fragmente.
 */
final class PageViewRecorder
{
    /**
     * @param list<string> $excludedPaths
     */
    public function __construct(
        private readonly PageViewRepository $repository,
        private readonly UserAgentParser $userAgentParser,
        private readonly PathNormalizer $pathNormalizer,
        private readonly ReferrerNormalizer $referrerNormalizer,
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $siteKey,
        private readonly string $salt,
        private readonly array $excludedPaths,
        private readonly bool $recordUserIdentifier,
    ) {
    }

    /**
     * Erfasst einen Seitenaufruf. Gibt false zurück, wenn der Pfad
     * ungültig ist oder ausgeschlossen wurde; in diesem Fall wird
     * nichts gespeichert.
     */
    public function record(Request $request, ?string $path = null, ?string $referrer = null): bool
    {
        $normalizedPath = $this->pathNormalizer->normalize($path ?? $request->getPathInfo());

        if (null === $normalizedPath) {
            return false;
        }

        if ($this->isExcluded($normalizedPath)) {
            return false;
        }

        $route = $request->attributes->get('_route');
        $route = \is_string($route) && '' !== $route ? mb_substr($route, 0, 190) : null;

        // Der User-Agent dient ausschließlich der Klassifikation und
        // wird niemals in die Datenbank geschrieben.
        $classification = $this->userAgentParser->parse($request->headers->get('User-Agent'));

        $referrerHost = $this->referrerNormalizer->toHost($referrer ?? $request->headers->get('Referer'));

        $now = new \DateTimeImmutable();
        $anonymizedIp = $this->ipAnonymizer->anonymize($request->getClientIp());
        $ipHash = 'unknown' === $anonymizedIp
            ? null
            : $this->ipAnonymizer->hash($anonymizedIp, $now, $this->salt);

        $pageView = PageView::create(
            $this->siteKey,
            $normalizedPath,
            $now,
            $route,
            $referrerHost,
            $classification['browser'],
            $classification['operating_system'],
            $classification['device'],
            $ipHash,
            $this->resolveUserIdentifier(),
        );

        $this->repository->save($pageView);

        return true;
    }

    public function isExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if ('' === $excludedPath) {
                continue;
            }

            if (str_starts_with($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Login-Identifier nur bei explizit aktivierter Option. Ein
     * Identifier kann personenbezogen sein, daher ist die Funktion
     * standardmäßig deaktiviert.
     */
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
