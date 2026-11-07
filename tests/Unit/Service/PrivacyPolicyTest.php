<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\BotDetector;
use Jenssmit\AnalyticsBundle\Service\PrivacyPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PrivacyPolicyTest extends TestCase
{
    /**
     * @dataProvider allowsProvider
     *
     * @param array<string, mixed> $server
     */
    public function testAllowsRecording(array $server, bool $excludeBots, bool $respectDnt, bool $respectGpc, bool $expected): void
    {
        $policy = new PrivacyPolicy(new BotDetector(), $excludeBots, $respectDnt, $respectGpc);
        $request = Request::create('/blog', 'GET', [], [], [], $server);

        self::assertSame($expected, $policy->allowsRecording($request));
    }

    public static function allowsProvider(): \Generator
    {
        $browser = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'];
        $bot = ['HTTP_USER_AGENT' => 'curl/8.5.0'];
        $dnt = $browser + ['HTTP_DNT' => '1'];
        $gpc = $browser + ['HTTP_SEC_GPC' => '1'];

        yield 'Normaler Browser wird erfasst' => [$browser, true, true, true, true];
        yield 'Bot wird ausgeschlossen' => [$bot, true, true, true, false];
        yield 'Bot wird bei deaktiviertem Filter erfasst' => [$bot, false, true, true, true];
        yield 'DNT wird respektiert' => [$dnt, true, true, true, false];
        yield 'DNT wird bei deaktivierter Option ignoriert' => [$dnt, true, false, true, true];
        yield 'GPC wird respektiert' => [$gpc, true, true, true, false];
        yield 'GPC wird bei deaktivierter Option ignoriert' => [$gpc, true, true, false, true];
    }
}
