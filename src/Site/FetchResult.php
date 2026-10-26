<?php

declare(strict_types=1);

namespace Prospec\Site;

/**
 * Outcome of fetching one URL.
 */
final class FetchResult
{
    public const OK = 'ok';
    public const DOWN = 'down';
    public const BLOCKED = 'blocked';
    public const INCONCLUSIVE = 'inconclusive';
    public const INVALID = 'invalid';

    public function __construct(
        public readonly string $status,
        public readonly int $httpCode = 0,
        public readonly string $finalUrl = '',
        public readonly float $elapsed = 0.0,
        public readonly string $html = '',
        public readonly bool $invalidCertificate = false,
    ) {
    }
}
