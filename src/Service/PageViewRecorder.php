<?php

namespace Jenssmit\AnalyticsBundle\Service;

use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Message\RecordPageViewMessage;
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
 *
 * Die zentrale PrivacyPolicy schließt Bots aus und respektiert DNT
 * und Sec-GPC. DNT- und Sec-GPC-Signale werden respektiert. Server-Tracking
 * und Collect-Endpunkt laufen über denselben Recorder und werden
 * identisch behandelt.
 */
class PageViewRecorder
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
        private readonly PrivacyPolicy $privacyPolicy,
        private readonly TrafficSourceClassifier $trafficSourceClassifier,
        private readonly CampaignParameterNormalizer $campaignParameterNormalizer,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly SystemClock $clock,
        private readonly string $siteKey,
        private readonly string $hashSecret,
        private readonly array $excludedPaths,
        private readonly bool $recordUserIdentifier,
        private readonly ?\Symfony\Component\Messenger\MessageBusInterface $messageBus = null,
        private readonly bool $asyncRecording = false,
    ) {
    }

    /**
     * Erfasst einen Seitenaufruf. Gibt false zurück, wenn der Pfad
     * ungültig ist oder ausgeschlossen wurde; in diesem Fall wird
     * nichts gespeichert.
     *
     * Im asynchronen Modus (async_recording, benötigt Messenger) wird
     * eine normalisierte Nachricht dispatcht statt direkt zu
     * speichern; ohne Messenger-Abhängigkeit läuft die Erfassung
     * synchron.
     *
     * @param array<string, mixed>|null $campaign Optionale Kampagnen-Parameter (utm_source, utm_medium, utm_campaign); ohne Angabe werden sie aus den Query-Parametern der aktuellen Anfrage abgeleitet
     */
    public function record(Request $request, ?string $path = null, ?string $referrer = null, ?array $campaign = null): bool
    {
        if (!$this->privacyPolicy->allowsRecording($request)) {
            return false;
        }

        $userAgent = $request->headers->get('User-Agent');

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
        $classification = $this->userAgentParser->parse($userAgent);

        $referrerHost = $this->referrerNormalizer->toHost($referrer ?? $request->headers->get('Referer'));

        $campaignParameters = $this->campaignParameterNormalizer->normalize(
            $campaign ?? [
                'utm_source' => $request->query->get('utm_source'),
                'utm_medium' => $request->query->get('utm_medium'),
                'utm_campaign' => $request->query->get('utm_campaign'),
            ],
        );

        $hasCampaign = null !== $campaignParameters['utm_source']
            || null !== $campaignParameters['utm_medium']
            || null !== $campaignParameters['utm_campaign'];

        $source = $this->trafficSourceClassifier->classify($referrerHost, $request->getHost(), $hasCampaign);

        $now = $this->clock->now();
        $anonymizedIp = $this->ipAnonymizer->anonymize($request->getClientIp());
        $ipHash = 'unknown' === $anonymizedIp
            ? null
            : $this->ipAnonymizer->hash($anonymizedIp, $now, $this->hashSecret);

        $userIdentifier = $this->resolveUserIdentifier();

        if ($this->asyncRecording && null !== $this->messageBus) {
            $this->messageBus->dispatch(new RecordPageViewMessage(
                $this->siteKey,
                $normalizedPath,
                $now,
                $route,
                $referrerHost,
                $classification['browser'],
                $classification['operating_system'],
                $classification['device'],
                $ipHash,
                $userIdentifier,
                $source,
                $campaignParameters['utm_source'],
                $campaignParameters['utm_medium'],
                $campaignParameters['utm_campaign'],
            ));

            return true;
        }

        $this->repository->save(PageView::create(
            $this->siteKey,
            $normalizedPath,
            $now,
            $route,
            $referrerHost,
            $classification['browser'],
            $classification['operating_system'],
            $classification['device'],
            $ipHash,
            $userIdentifier,
            $source,
            $campaignParameters['utm_source'],
            $campaignParameters['utm_medium'],
            $campaignParameters['utm_campaign'],
        ));

        return true;
    }

    /**
     * Prüft Pfadausschlüsse auf Segmentgrenzen: "/admin" schließt
     * "/admin" und "/admin/..." aus, aber nicht "/administrator".
     */
    public function isExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            $normalized = rtrim($excludedPath, '/');

            if ('' === $normalized) {
                continue;
            }

            if ($path === $normalized || str_starts_with($path, $normalized.'/')) {
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
