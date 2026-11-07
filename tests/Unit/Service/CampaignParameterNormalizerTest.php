<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\CampaignParameterNormalizer;
use PHPUnit\Framework\TestCase;

final class CampaignParameterNormalizerTest extends TestCase
{
    public function testNormalizesAndTruncatesValues(): void
    {
        $normalizer = new CampaignParameterNormalizer();

        $result = $normalizer->normalize([
            'utm_source' => '  Newsletter  ',
            'utm_medium' => 'email',
            'utm_campaign' => str_repeat('a', 150),
        ]);

        self::assertSame('Newsletter', $result['utm_source']);
        self::assertSame('email', $result['utm_medium']);
        self::assertSame(100, mb_strlen((string) $result['utm_campaign']));
    }

    public function testStripsMarkupAndControlCharacters(): void
    {
        $normalizer = new CampaignParameterNormalizer();

        $result = $normalizer->normalize([
            'utm_source' => "<script>alert(1)</script>news\n",
        ]);

        self::assertSame('alert(1)news', $result['utm_source']);
    }

    public function testInvalidTypesYieldNull(): void
    {
        $normalizer = new CampaignParameterNormalizer();

        $result = $normalizer->normalize([
            'utm_source' => 42,
            'utm_medium' => ['array'],
            'utm_campaign' => null,
        ]);

        self::assertNull($result['utm_source']);
        self::assertNull($result['utm_medium']);
        self::assertNull($result['utm_campaign']);
    }

    public function testAllowlistAppliesOnlyToUtmSource(): void
    {
        $normalizer = new CampaignParameterNormalizer(['newsletter', 'ads']);

        $result = $normalizer->normalize([
            'utm_source' => 'newsletter',
            'utm_medium' => 'organic',
            'utm_campaign' => 'summer-sale',
        ]);

        self::assertSame('newsletter', $result['utm_source']);
        // Medium und Kampagne werden unabhängig von der Quellen-Allowlist gespeichert.
        self::assertSame('organic', $result['utm_medium']);
        self::assertSame('summer-sale', $result['utm_campaign']);
    }

    public function testAllowlistDiscardsUnknownSourcesOnly(): void
    {
        $normalizer = new CampaignParameterNormalizer(['newsletter']);

        $result = $normalizer->normalize([
            'utm_source' => 'google',
            'utm_medium' => 'organic',
        ]);

        self::assertNull($result['utm_source']);
        self::assertSame('organic', $result['utm_medium']);
    }
}
