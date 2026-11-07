<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Entity\AnalyticsEvent;
use Jenssmit\AnalyticsBundle\Repository\AnalyticsEventRepository;
use Jenssmit\AnalyticsBundle\Service\AnalyticsEventRecorder;
use Jenssmit\AnalyticsBundle\Service\BotDetector;
use Jenssmit\AnalyticsBundle\Service\EventNameValidator;
use Jenssmit\AnalyticsBundle\Service\EventValueNormalizer;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\SystemClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AnalyticsEventRecorderTest extends TestCase
{
    private AnalyticsEventRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AnalyticsEventRepository::class);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRecorder(array $overrides = []): AnalyticsEventRecorder
    {
        return new AnalyticsEventRecorder(
            $this->repository,
            new BotDetector(),
            new PathNormalizer(),
            new IpAnonymizer(),
            new EventNameValidator(),
            new EventValueNormalizer(),
            new SystemClock(),
            'site-a',
            'unit-test-hash-secret',
            $overrides['record_event_values'] ?? false,
        );
    }

    private function createRequest(): Request
    {
        return Request::create('/checkout', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);
    }

    public function testRecordsAValidEvent(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (AnalyticsEvent $event) use (&$captured): void {
                $captured = $event;
            });

        $recorded = $this->createRecorder()->record($this->createRequest(), [
            'name' => 'checkout_complete',
            'category' => 'ecommerce',
        ]);

        self::assertTrue($recorded);
        self::assertSame('site-a', $captured->getSiteKey());
        self::assertSame('checkout_complete', $captured->getEventName());
        self::assertSame('ecommerce', $captured->getEventCategory());
        self::assertSame('/checkout', $captured->getPath());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $captured->getVisitorHash());
    }

    public function testRejectsInvalidNames(): void
    {
        $this->repository->expects(self::never())->method('save');

        self::assertFalse($this->createRecorder()->record($this->createRequest(), ['name' => '']));
        self::assertFalse($this->createRecorder()->record($this->createRequest(), ['name' => 'ungültiger name!']));
        self::assertFalse($this->createRecorder()->record($this->createRequest(), ['name' => str_repeat('a', 81)]));
        self::assertFalse($this->createRecorder()->record($this->createRequest(), []));
    }

    public function testRejectsInvalidCategories(): void
    {
        $this->repository->expects(self::never())->method('save');

        self::assertFalse($this->createRecorder()->record($this->createRequest(), [
            'name' => 'valid_name',
            'category' => 'Kategorie mit Leerzeichen',
        ]));
    }

    public function testBotsAndPrivacySignalsAreNotRecorded(): void
    {
        $this->repository->expects(self::never())->method('save');

        $bot = Request::create('/checkout', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'curl/8.5.0',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);
        $dnt = Request::create('/checkout', 'GET', [], [], [], [
            'HTTP_DNT' => '1',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);
        $gpc = Request::create('/checkout', 'GET', [], [], [], [
            'HTTP_SEC_GPC' => '1',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        $recorder = $this->createRecorder();

        self::assertFalse($recorder->record($bot, ['name' => 'valid_name']));
        self::assertFalse($recorder->record($dnt, ['name' => 'valid_name']));
        self::assertFalse($recorder->record($gpc, ['name' => 'valid_name']));
    }

    public function testValuesAreOnlyStoredWhenEnabled(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (AnalyticsEvent $event) use (&$captured): void {
                $captured = $event;
            });

        $this->createRecorder(['record_event_values' => true])->record($this->createRequest(), [
            'name' => 'video_play',
            'value' => '  <b>42</b> ',
        ]);

        self::assertSame('42', $captured->getEventValue());
    }

    public function testValuesAreDiscardedWhenDisabled(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (AnalyticsEvent $event) use (&$captured): void {
                $captured = $event;
            });

        $this->createRecorder()->record($this->createRequest(), [
            'name' => 'video_play',
            'value' => '42',
        ]);

        self::assertNull($captured->getEventValue());
    }
}
