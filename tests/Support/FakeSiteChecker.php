<?php

declare(strict_types=1);

namespace Prospec\Tests\Support;

use Prospec\Site\FetchResult;
use Prospec\Site\SiteChecker;

/**
 * Returns canned results by URL; unknown URLs are reported as down.
 */
final class FakeSiteChecker implements SiteChecker
{
    /** @var list<array<array-key, string>> */
    public array $calls = [];

    /** @param array<string, FetchResult> $results */
    public function __construct(private readonly array $results = [])
    {
    }

    public function check(array $urls, float $deadlineSeconds = 60.0): array
    {
        $this->calls[] = $urls;
        $out = [];
        foreach ($urls as $key => $url) {
            $out[$key] = $this->results[$url] ?? new FetchResult(FetchResult::DOWN, 0, $url);
        }
        return $out;
    }
}
