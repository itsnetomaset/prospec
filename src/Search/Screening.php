<?php

declare(strict_types=1);

namespace Prospec\Search;

/**
 * Result of LeadQualifier::screen(): discard it, accept it, or check its website first.
 */
final class Screening
{
    /**
     * @param list<string> $reasons
     */
    private function __construct(
        public readonly ?string $discardReason = null,
        public readonly ?string $profile = null,
        public readonly array $reasons = [],
        public readonly int $score = 0,
        public readonly ?string $websiteToCheck = null,
    ) {
    }

    public static function discard(string $reason): self
    {
        return new self(discardReason: $reason);
    }

    /**
     * @param list<string> $reasons
     */
    public static function accept(string $profile, array $reasons, int $score): self
    {
        return new self(profile: $profile, reasons: $reasons, score: $score);
    }

    public static function checkWebsite(string $url, int $score): self
    {
        return new self(score: $score, websiteToCheck: $url);
    }

    public function isDiscarded(): bool
    {
        return $this->discardReason !== null;
    }

    public function needsWebsiteCheck(): bool
    {
        return $this->websiteToCheck !== null;
    }
}
