<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Site;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prospec\Site\NetworkGuard;

final class NetworkGuardTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function addresses(): array
    {
        return [
            'public' => ['8.8.8.8', true],
            'loopback' => ['127.0.0.1', false],
            'private 10/8' => ['10.1.2.3', false],
            'private 192.168/16' => ['192.168.0.10', false],
            'link local (cloud metadata)' => ['169.254.169.254', false],
            'carrier-grade NAT' => ['100.64.0.1', false],
            'ipv6 is not fetched' => ['2001:4860:4860::8888', false],
            'not an ip' => ['example.com', false],
        ];
    }

    #[DataProvider('addresses')]
    public function testOnlyPublicIpv4IsAllowed(string $ip, bool $expected): void
    {
        self::assertSame($expected, (new NetworkGuard())->isPublic($ip));
    }

    public function testPrivateAddressesCanBeAllowedForTests(): void
    {
        self::assertTrue((new NetworkGuard(['127.0.0.1']))->isPublic('127.0.0.1'));
        self::assertFalse((new NetworkGuard(['127.0.0.1']))->isPublic('10.0.0.1'));
        self::assertTrue((new NetworkGuard(true))->isPublic('10.0.0.1'));
    }

    public function testResolvesIpLiterals(): void
    {
        self::assertSame(['ip' => '8.8.8.8', 'reason' => null], (new NetworkGuard())->resolve('8.8.8.8'));
        self::assertSame(['ip' => null, 'reason' => 'private'], (new NetworkGuard())->resolve('127.0.0.1'));
    }
}
