<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Controller;

use Jenssmit\AnalyticsBundle\Controller\CollectEventController;
use Jenssmit\AnalyticsBundle\Service\AnalyticsEventRecorder;
use Jenssmit\AnalyticsBundle\Service\CollectRateLimiter;
use Jenssmit\AnalyticsBundle\Service\CollectRequestGuard;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\SystemClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CollectEventControllerTest extends TestCase
{
    private AnalyticsEventRecorder&MockObject $recorder;

    protected function setUp(): void
    {
        $this->recorder = $this->createMock(AnalyticsEventRecorder::class);
    }

    private function createController(bool $rateLimitAllowed = true): CollectEventController
    {
        $rateLimiter = $this->createMock(CollectRateLimiter::class);
        $rateLimiter->method('allow')->willReturn($rateLimitAllowed);

        $guard = new CollectRequestGuard(
            $rateLimiter,
            new IpAnonymizer(),
            new SystemClock(),
            [],
            4096,
        );

        return new CollectEventController($this->recorder, $guard);
    }

    /**
     * @param array<string, string> $server
     */
    private function createRequest(string $content, array $server = []): Request
    {
        return Request::create(
            'http://www.example.com/analytics/collect/event',
            'POST',
            [],
            [],
            [],
            array_merge([
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'REMOTE_ADDR' => '192.168.178.42',
                'CONTENT_TYPE' => 'application/json',
            ], $server),
            $content,
        );
    }

    public function testRecordsAValidEvent(): void
    {
        $this->recorder
            ->expects(self::once())
            ->method('record')
            ->willReturn(true);

        $response = $this->createController()->__invoke($this->createRequest(
            '{"name":"newsletter_signup","category":"engagement"}',
        ));

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testRejectsInvalidEvents(): void
    {
        $this->recorder
            ->expects(self::once())
            ->method('record')
            ->willReturn(false);

        $response = $this->createController()->__invoke($this->createRequest(
            '{"name":"ungültig!"}',
        ));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsInvalidJson(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest('kein json'));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsForeignOrigin(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest(
            '{"name":"newsletter_signup"}',
            ['HTTP_ORIGIN' => 'http://evil.example'],
        ));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRejectsWhenRateLimitIsExhausted(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController(false)->__invoke($this->createRequest(
            '{"name":"newsletter_signup"}',
        ));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertNotSame('', (string) $response->headers->get('Retry-After'));
    }
}
