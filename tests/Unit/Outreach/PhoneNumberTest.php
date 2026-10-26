<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Outreach;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prospec\Outreach\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function numbers(): array
    {
        return [
            'mobile with mask' => ['(16) 99123-4567', '16991234567'],
            'mobile with country code' => ['+55 16 99888-7777', '16998887777'],
            'mobile with trunk prefix' => ['016 99123-4567', '16991234567'],
            'landline' => ['(16) 3610-0000', '1636100000'],
            'landline starting with 5' => ['11 5555-0000', '1155550000'],
            'toll free' => ['0800 123 4567', null],
            'premium' => ['0300 123 4567', null],
            'invalid area code' => ['(01) 99123-4567', null],
            'mobile without 9' => ['16 89123-4567', null],
            'landline starting with 6' => ['16 6123-4567', null],
            'too short' => ['123', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('numbers')]
    public function testNormalizesNumbersForWhatsApp(string $input, ?string $expected): void
    {
        self::assertSame($expected, PhoneNumber::toWhatsApp($input));
    }

    public function testFormatsMobileAndLandline(): void
    {
        self::assertSame('+55 16 99104-8833', PhoneNumber::format('16991048833'));
        self::assertSame('+55 16 3610-1234', PhoneNumber::format('1636101234'));
    }

    public function testDetectsLandline(): void
    {
        self::assertTrue(PhoneNumber::isLandline('1636101234'));
        self::assertFalse(PhoneNumber::isLandline('16991048833'));
    }
}
