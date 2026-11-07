<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Message\RecordPageViewMessage;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\BotDetector;
use Jenssmit\AnalyticsBundle\Service\CampaignParameterNormalizer;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PrivacyPolicy;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use Jenssmit\AnalyticsBundle\Service\SystemClock;
use Jenssmit\AnalyticsBundle\Service\TrafficSourceClassifier;
use Jenssmit\AnalyticsBundle\Service\UserAgentParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PageViewRecorderTest extends TestCase
{
    private PageViewRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PageViewRepository::class);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRecorder(
        bool $recordUserIdentifier = false,
        ?TokenInterface $token = null,
        array $overrides = [],
    ): PageViewRecorder {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return new PageViewRecorder(
            $this->repository,
            new UserAgentParser(),
            new PathNormalizer(),
            new ReferrerNormalizer(),
            new IpAnonymizer(),
            new PrivacyPolicy(
                new BotDetector($overrides['bot_patterns'] ?? [], $overrides['bot_exceptions'] ?? []),
                $overrides['exclude_bots'] ?? true,
                $overrides['respect_dnt'] ?? true,
                $overrides['respect_gpc'] ?? true,
            ),
            new TrafficSourceClassifier(),
            new CampaignParameterNormalizer($overrides['campaign_allowlist'] ?? []),
            $tokenStorage,
            new SystemClock(),
            'site-a',
            'unit-test-hash-secret',
            $overrides['excluded_paths'] ?? ['/health'],
            $recordUserIdentifier,
            $overrides['message_bus'] ?? null,
            $overrides['async_recording'] ?? false,
        );
    }

    private function createRequest(): Request
    {
        return Request::create('/blog/post?utm_source=mail', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'HTTP_REFERER' => 'https://www.google.com/search?q=geheim',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);
    }

    public function testRecordNormalizesAndMinimizesData(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$captured): void {
                $captured = $pageView;
            });

        $recorded = $this->createRecorder()->record($this->createRequest());

        self::assertTrue($recorded);
        self::assertInstanceOf(PageView::class, $captured);

        // Query-Parameter werden nicht gespeichert.
        self::assertSame('/blog/post', $captured->getPath());
        // Referrer wird auf den Host reduziert.
        self::assertSame('www.google.com', $captured->getReferrer());
        // User-Agent wird nur klassifiziert, nicht gespeichert.
        self::assertSame('Chrome', $captured->getBrowser());
        self::assertSame('Windows', $captured->getOperatingSystem());
        self::assertSame('desktop', $captured->getDevice());
        // Mandanten-Trennschlüssel wird gesetzt.
        self::assertSame('site-a', $captured->getSiteKey());
        // IP-Pseudonym ist ein Hex-Hash, niemals eine IP-Adresse.
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $captured->getIpHash());
        // Standardmäßig wird kein Nutzer-Identifier gespeichert.
        self::assertNull($captured->getUserIdentifier());
        // Der utm_source-Parameter der aktuellen URL macht den Aufruf
        // zur Kampagne; Kampagnen haben Vorrang vor der Referrer-Klasse.
        self::assertSame('campaign', $captured->getSource());
        self::assertSame('mail', $captured->getUtmSource());
    }

    public function testExcludedPathIsNotRecorded(): void
    {
        $this->repository->expects(self::never())->method('save');

        $request = Request::create('/health', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.178.42']);

        self::assertFalse($this->createRecorder()->record($request));
    }

    public function testExcludedPathsRespectSegmentBoundaries(): void
    {
        $this->repository->expects(self::never())->method('save');

        $recorder = $this->createRecorder(false, null, ['excluded_paths' => ['/admin']]);

        // "/administrator" teilt nur das Präfix, aber nicht das Segment.
        self::assertFalse($recorder->isExcluded('/administrator'));
        self::assertTrue($recorder->isExcluded('/admin'));
        self::assertTrue($recorder->isExcluded('/admin/dashboard'));
    }

    public function testBotsAreExcludedWhenEnabled(): void
    {
        $this->repository->expects(self::never())->method('save');

        $request = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        self::assertFalse($this->createRecorder()->record($request));
    }

    public function testBotsAreRecordedWhenExclusionIsDisabled(): void
    {
        $this->repository->expects(self::once())->method('save');

        $request = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        self::assertTrue($this->createRecorder(false, null, ['exclude_bots' => false])->record($request));
    }

    public function testBotExceptionAllowsKnownFalsePositive(): void
    {
        $this->repository->expects(self::once())->method('save');

        $request = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/126.0.0.0 Safari/537.36',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        $recorder = $this->createRecorder(false, null, ['bot_exceptions' => ['HeadlessChrome']]);

        self::assertTrue($recorder->record($request));
    }

    public function testDntRequestsAreNotRecorded(): void
    {
        $this->repository->expects(self::never())->method('save');

        $request = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_DNT' => '1',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        self::assertFalse($this->createRecorder()->record($request));
    }

    public function testGpcRequestsAreNotRecorded(): void
    {
        $this->repository->expects(self::never())->method('save');

        $request = Request::create('/blog', 'GET', [], [], [], [
            'HTTP_SEC_GPC' => '1',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        self::assertFalse($this->createRecorder()->record($request));
    }

    public function testCampaignFromPayloadIsStoredAndClassified(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$captured): void {
                $captured = $pageView;
            });

        $request = Request::create('/blog/post', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        $recorded = $this->createRecorder()->record($request, '/blog/post', null, [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'summer',
        ]);

        self::assertTrue($recorded);
        self::assertSame('campaign', $captured->getSource());
        self::assertSame('newsletter', $captured->getUtmSource());
        self::assertSame('email', $captured->getUtmMedium());
        self::assertSame('summer', $captured->getUtmCampaign());
    }

    public function testCampaignAllowlistDiscardsUnknownValues(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$captured): void {
                $captured = $pageView;
            });

        $request = Request::create('/blog/post', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        $this->createRecorder(false, null, ['campaign_allowlist' => ['newsletter']])->record(
            $request,
            '/blog/post',
            null,
            ['utm_source' => 'evil', 'utm_medium' => 'email'],
        );

        self::assertNull($captured->getUtmSource());
    }

    public function testInternalReferrerIsClassifiedAsInternal(): void
    {
        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$captured): void {
                $captured = $pageView;
            });

        $request = Request::create('http://www.example.com/blog', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'HTTP_REFERER' => 'http://www.example.com/',
            'REMOTE_ADDR' => '192.168.178.42',
        ]);

        $this->createRecorder()->record($request);

        self::assertSame('internal', $captured->getSource());
    }

    public function testUserIdentifierIsStoredOnlyWhenEnabled(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('jens@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$captured): void {
                $captured = $pageView;
            });

        $this->createRecorder(true, $token)->record($this->createRequest());

        self::assertInstanceOf(PageView::class, $captured);
        self::assertSame('jens@example.com', $captured->getUserIdentifier());
    }

    public function testAsyncRecordingDispatchesANormalizedMessage(): void
    {
        $this->repository->expects(self::never())->method('save');

        $messageBus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RecordPageViewMessage::class))
            ->willReturnCallback(static fn (object $message): array => [$message]);

        $recorder = $this->createRecorder(false, null, [
            'message_bus' => $messageBus,
            'async_recording' => true,
        ]);

        self::assertTrue($recorder->record($this->createRequest()));
    }

    public function testAsyncRecordingFallsBackToSyncWithoutBus(): void
    {
        $this->repository->expects(self::once())->method('save');

        $recorder = $this->createRecorder(false, null, ['async_recording' => true]);

        self::assertTrue($recorder->record($this->createRequest()));
    }

    public function testUserIdentifierIsNullWhenDisabledEvenIfLoggedIn(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('jens@example.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $captured = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$captured): void {
                $captured = $pageView;
            });

        $this->createRecorder(false, $token)->record($this->createRequest());

        self::assertInstanceOf(PageView::class, $captured);
        self::assertNull($captured->getUserIdentifier());
    }
}
