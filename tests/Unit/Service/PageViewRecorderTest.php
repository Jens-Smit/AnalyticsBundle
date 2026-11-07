<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Entity\PageView;
use Jenssmit\AnalyticsBundle\Repository\PageViewRepository;
use Jenssmit\AnalyticsBundle\Service\IpAnonymizer;
use Jenssmit\AnalyticsBundle\Service\PageViewRecorder;
use Jenssmit\AnalyticsBundle\Service\PathNormalizer;
use Jenssmit\AnalyticsBundle\Service\ReferrerNormalizer;
use Jenssmit\AnalyticsBundle\Service\UserAgentParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PageViewRecorderTest extends TestCase
{
    private PageViewRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PageViewRepository::class);
    }

    private function createRecorder(bool $recordUserIdentifier = false, ?TokenInterface $token = null): PageViewRecorder
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return new PageViewRecorder(
            $this->repository,
            new UserAgentParser(),
            new PathNormalizer(),
            new ReferrerNormalizer(),
            new IpAnonymizer(),
            $tokenStorage,
            'site-a',
            'unit-test-salt',
            ['/health'],
            $recordUserIdentifier,
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
    }

    public function testExcludedPathIsNotRecorded(): void
    {
        $this->repository->expects(self::never())->method('save');

        $request = Request::create('/health', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.178.42']);

        self::assertFalse($this->createRecorder()->record($request));
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
