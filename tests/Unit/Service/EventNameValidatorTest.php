<?php

namespace Jenssmit\AnalyticsBundle\Tests\Unit\Service;

use Jenssmit\AnalyticsBundle\Service\EventNameValidator;
use Jenssmit\AnalyticsBundle\Service\EventValueNormalizer;
use PHPUnit\Framework\TestCase;

final class EventNameValidatorTest extends TestCase
{
    private EventNameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new EventNameValidator();
    }

    /**
     * @dataProvider validNameProvider
     */
    public function testAcceptsStrictNames(string $name): void
    {
        self::assertTrue($this->validator->isValidName($name));
    }

    public static function validNameProvider(): \Generator
    {
        yield 'einfach' => ['newsletter_signup'];
        yield 'mit Punkten' => ['form.submit'];
        yield 'mit Großbuchstaben' => ['VideoPlay'];
        yield 'mit Bindestrichen' => ['checkout-step-2'];
    }

    /**
     * @dataProvider invalidNameProvider
     */
    public function testRejectsInvalidNames(string $name): void
    {
        self::assertFalse($this->validator->isValidName($name));
    }

    public static function invalidNameProvider(): \Generator
    {
        yield 'leer' => [''];
        yield 'Leerzeichen' => ['newsletter signup'];
        yield 'Markup' => ['<script>'];
        yield 'Sonderzeichen' => ['event/1'];
        yield 'zu lang' => [str_repeat('a', 81)];
    }

    public function testCategoryIsOptionalButStrict(): void
    {
        self::assertTrue($this->validator->isValidCategory(null));
        self::assertTrue($this->validator->isValidCategory(''));
        self::assertTrue($this->validator->isValidCategory('engagement'));
        self::assertFalse($this->validator->isValidCategory('Kategorie mit Leerzeichen'));
        self::assertFalse($this->validator->isValidCategory(str_repeat('a', 65)));
    }

    public function testValueNormalizerSanitizesAndTruncates(): void
    {
        $normalizer = new EventValueNormalizer();

        self::assertSame('42', $normalizer->normalize(42));
        self::assertSame('3.5', $normalizer->normalize(3.5));
        self::assertNull($normalizer->normalize(['array']));
        self::assertNull($normalizer->normalize(null));
        self::assertSame(100, mb_strlen((string) $normalizer->normalize(str_repeat('b', 200))));
        self::assertSame('wert', $normalizer->normalize("<b>wert</b>\n"));
    }
}
