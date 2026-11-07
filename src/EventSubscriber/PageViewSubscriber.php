<?php

namespace Jenssmit\AnalyticsBundle\EventSubscriber;

use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PageViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PageViewRecorder $recorder,
        private readonly bool $serverSideTracking,
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
        if (!$this->serverSideTracking || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethodSafe(false)) {
            return;
        }

        if ($this->trackOnlySuccessful && $event->getResponse()->getStatusCode() >= 400) {
            return;
        }

        $this->recorder->record($request);
    }
}
