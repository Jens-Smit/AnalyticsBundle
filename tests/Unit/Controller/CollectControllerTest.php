<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Controller;

use Jenssmit\AnalyticsBundle\Controller\CollectController;
use Jenssmit\AnalyticsBundle\Service\CollectRateLimiter;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CollectControllerTest extends TestCase
{
    private PageViewRecorder&MockObject $recorder;

    protected function setUp(): void
    {
        $this->recorder = $this->createMock(PageViewRecorder::class);
    }

    /**
     * @param array<string> $allowedOrigins
     */
    private function createController(array $allowedOrigins = [], int $maxRequestSize = 4096, bool $rateLimitAllowed = true): CollectController
    {
        $rateLimiter = $this->createMock(CollectRateLimiter::class);
        $rateLimiter->method('allow')->willReturn($rateLimitAllowed);

        return new CollectController(
            $this->recorder,
            $rateLimiter,
            new IpAnonymizer(),
            new PathNormalizer(),
            new ReferrerNormalizer(),
            $allowedOrigins,
            $maxRequestSize,
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function createRequest(string $content, array $server = []): Request
    {
        $defaults = [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
            'REMOTE_ADDR' => '192.168.178.42',
        ];

        return Request::create(
            'http://www.example.com/analytics/collect',
            'POST',
            [],
            [],
            [],
            array_merge($defaults, $server),
            $content,
        );
    }

    public function testRecordsValidSameOriginBeacon(): void
    {
        $this->recorder
            ->expects(self::once())
            ->method('record')
            ->with(
                self::isInstanceOf(Request::class),
                '/blog/post',
                'www.google.com',
            )
            ->willReturn(true);

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog/post","referrer":"https://www.google.com/search?q=x"}',
            [
                'HTTP_ORIGIN' => 'http://www.example.com',
                'CONTENT_TYPE' => 'text/plain',
            ],
        ));

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('Origin', $response->headers->get('Vary'));
    }

    public function testRejectsForeignOrigin(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog"}',
            [
                'HTTP_ORIGIN' => 'http://evil.example',
                'CONTENT_TYPE' => 'application/json',
            ],
        ));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Origin', $response->headers->get('Vary'));
        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAllowsExplicitlyConfiguredOrigin(): void
    {
        $this->recorder->expects(self::once())->method('record')->willReturn(true);

        $response = $this->createController(['http://partner.example'])->__invoke($this->createRequest(
            '{"path":"/blog"}',
            [
                'HTTP_ORIGIN' => 'http://partner.example',
                'CONTENT_TYPE' => 'application/json',
            ],
        ));

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('http://partner.example', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testRejectsOversizedPayload(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController([], 64)->__invoke($this->createRequest(
            '{"path":"'.str_repeat('/a', 100).'"}',
            [
                'HTTP_ORIGIN' => 'http://www.example.com',
                'CONTENT_TYPE' => 'application/json',
            ],
        ));

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
    }

    public function testRejectsUnsupportedContentType(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog"}',
            [
                'HTTP_ORIGIN' => 'http://www.example.com',
                'CONTENT_TYPE' => 'text/csv',
            ],
        ));

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode());
    }

    public function testRejectsInvalidJson(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest(
            'kein json',
            [
                'HTTP_ORIGIN' => 'http://www.example.com',
                'CONTENT_TYPE' => 'application/json',
            ],
        ));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsMissingAndInvalidPath(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $origin = ['HTTP_ORIGIN' => 'http://www.example.com', 'CONTENT_TYPE' => 'application/json'];

        $missing = $this->createController()->__invoke($this->createRequest('{"referrer":"https://google.com"}', $origin));
        $relative = $this->createController()->__invoke($this->createRequest('{"path":"ohne-slash"}', $origin));
        $wrongType = $this->createController()->__invoke($this->createRequest('{"path":42}', $origin));

        self::assertSame(Response::HTTP_BAD_REQUEST, $missing->getStatusCode());
        self::assertSame(Response::HTTP_BAD_REQUEST, $relative->getStatusCode());
        self::assertSame(Response::HTTP_BAD_REQUEST, $wrongType->getStatusCode());
    }

    public function testRejectsWhenRateLimitIsExhausted(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController([], 4096, false)->__invoke($this->createRequest(
            '{"path":"/blog"}',
            [
                'HTTP_ORIGIN' => 'http://www.example.com',
                'CONTENT_TYPE' => 'application/json',
            ],
        ));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertNotSame('', (string) $response->headers->get('Retry-After'));
    }

    public function testRequestsWithoutOriginHeaderAreProcessed(): void
    {
        // Nicht-browserbasierte Clients senden oft keinen Origin-Header.
        // Der Schutz läuft dann über Rate-Limiting und Validierung.
        $this->recorder->expects(self::once())->method('record')->willReturn(true);

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog"}',
            ['CONTENT_TYPE' => 'application/json'],
        ));

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNull($response->headers->get('Vary'));
    }

    public function testRejectsMissingContentType(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog"}',
            ['HTTP_ORIGIN' => 'http://www.example.com'],
        ));

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode());
    }

    public function testRejectsEmptyContentType(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog"}',
            ['HTTP_ORIGIN' => 'http://www.example.com', 'CONTENT_TYPE' => '  '],
        ));

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode());
    }

    /**
     * @dataProvider allowedContentTypeProvider
     */
    public function testAcceptsContentTypeVariants(string $contentType): void
    {
        $this->recorder->expects(self::once())->method('record')->willReturn(true);

        $response = $this->createController()->__invoke($this->createRequest(
            '{"path":"/blog"}',
            ['HTTP_ORIGIN' => 'http://www.example.com', 'CONTENT_TYPE' => $contentType],
        ));

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public static function allowedContentTypeProvider(): \Generator
    {
        yield 'JSON mit Charset' => ['application/json; charset=utf-8'];
        yield 'Text mit Charset' => ['text/plain; charset=utf-8'];
        yield 'Großschreibung' => ['APPLICATION/JSON'];
        yield 'Führendes Leerzeichen' => [' text/plain'];
    }

    public function testRejectsPayloadThatIsNotAJsonObject(): void
    {
        $this->recorder->expects(self::never())->method('record');

        $origin = ['HTTP_ORIGIN' => 'http://www.example.com', 'CONTENT_TYPE' => 'application/json'];

        $list = $this->createController()->__invoke($this->createRequest('[1,2,3]', $origin));
        $string = $this->createController()->__invoke($this->createRequest('"text"', $origin));

        self::assertSame(Response::HTTP_BAD_REQUEST, $list->getStatusCode());
        self::assertSame(Response::HTTP_BAD_REQUEST, $string->getStatusCode());
    }
}
