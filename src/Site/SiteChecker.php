<?php

declare(strict_types=1);

namespace Prospec\Site;

interface SiteChecker
{
    /**
     * Fetches several URLs and reports what happened to each one.
     *
     * @param array<array-key, string> $urls
     * @return array<array-key, FetchResult> keyed like $urls
     */
    public function check(array $urls, float $deadlineSeconds = 60.0): array;
}
