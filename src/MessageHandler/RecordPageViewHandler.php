<?php

namespace Jenssmit\AnalyticsBundle\MessageHandler;

use Jenssmit\AnalyticsBundle\Message\RecordPageViewMessage;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;

/**
 * Speichert eine normalisierte PageView-Nachricht. Der Handler wird
 * nur registriert, wenn symfony/messenger installiert ist; das
 * synchrone Recording bleibt Standard und liefert dieselbe Semantik.
 *
 * Nachrichten enthalten keine personenbezogenen Rohdaten. Bei Retries
 * kann dieselbe Nachricht zweimal gespeichert werden (at-least-once);
 * Duplikate beeinflussen nur Zählermetriken minimal und sind dokumentiert.
 */
final class RecordPageViewHandler
{
    public function __construct(
        private readonly PageViewRepository $repository,
    ) {
    }

    public function __invoke(RecordPageViewMessage $message): void
    {
        $this->repository->save($message->toPageView());
    }
}
