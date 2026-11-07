<?php

namespace Jenssmit\AnalyticsBundle\EventSubscriber;

use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serverseitige Erfassung über kernel.terminate.
 *
 * Der Subscriber ist nur im page_view_mode "server" aktiv. In den
 * Modi "client" und "manual" übernehmen das JavaScript-SDK
 * beziehungsweise explizite record()-Aufrufe, damit Kombinationen aus
 * Server-Tracking und Client-Beacon nicht doppelt zählen.
 */
final class PageViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PageViewRecorder $recorder,
        private readonly string $pageViewMode,
        private readonly bool $trackOnlySuccessful,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', 0],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ('server' !== $this->pageViewMode || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethodSafe()) {
            return;
        }

        if ($this->trackOnlySuccessful && $event->getResponse()->getStatusCode() >= 400) {
            return;
        }

        $this->recorder->record($request);
    }
}
