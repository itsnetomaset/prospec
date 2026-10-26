<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;
use Prospec\Site\FetchResult;
use Prospec\Site\NetworkGuard;
use Prospec\Site\SiteFetcher;

final class SiteFetcherTest extends TestCase
{
    public function testResolvesRedirectLocations(): void
    {
        self::assertSame('https://b.com/x', SiteFetcher::absoluteUrl('http://a.com/p/q', 'https://b.com/x'));
        self::assertSame('https://cdn.com/x', SiteFetcher::absoluteUrl('https://a.com/p', '//cdn.com/x'));
        self::assertSame('http://a.com:8080/home', SiteFetcher::absoluteUrl('http://a.com:8080/p/q', '/home'));
        self::assertSame('http://a.com/p/next', SiteFetcher::absoluteUrl('http://a.com/p/q', 'next'));
        self::assertSame('http://a.com/next', SiteFetcher::absoluteUrl('http://a.com', 'next'));
    }

    public function testRejectsUnsafeUrlsWithoutConnecting(): void
    {
        $results = (new SiteFetcher(new NetworkGuard()))->check([
            'ftp' => 'ftp://example.com/',
            'port' => 'http://example.com:22/',
            'private' => 'http://127.0.0.1/',
            'metadata' => 'http://169.254.169.254/latest/meta-data/',
        ], 5);

        foreach ($results as $key => $result) {
            self::assertSame(FetchResult::INVALID, $result->status, $key);
        }
    }
}
