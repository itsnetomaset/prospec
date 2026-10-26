<?php

declare(strict_types=1);

namespace Prospec\Search;

use Prospec\Lead\LeadProfile;

/**
 * First screening of a Google Maps result, without any network access.
 */
final class LeadQualifier
{
    /** Hosts that mean "the business only has a social media profile". */
    public const SOCIAL_HOSTS = ['instagram.com', 'facebook.com', 'fb.com', 'linktr.ee', 'wa.me', 'whatsapp.com', 'tiktok.com', 'beacons.ai', 'linkin.bio', 'bio.site'];

    /**
     * @param list<string> $socialHosts
     */
    public function __construct(
        private readonly int $minReviews,
        private readonly array $socialHosts = self::SOCIAL_HOSTS,
    ) {
    }

    /**
     * @param array<string, mixed> $place Serper "places" item
     */
    public function screen(array $place): Screening
    {
        $phone = trim((string)($place['phoneNumber'] ?? ''));
        $website = trim((string)($place['website'] ?? ''));
        $reviews = (int)($place['ratingCount'] ?? 0);
        $rating = (float)($place['rating'] ?? 0);
        if ($phone === '') {
            return Screening::discard('sem telefone');
        }
        if ($reviews < $this->minReviews) {
            return Screening::discard('poucas avaliacoes');
        }
        // Busier and better rated businesses come first in the queue.
        $score = ($reviews >= 100 ? 3 : ($reviews >= 40 ? 2 : 1)) + ($rating >= 4.3 ? 1 : 0);
        if ($website === '') {
            return Screening::accept(LeadProfile::NO_WEBSITE, ['sem site no Google'], $score + 5);
        }
        if (!preg_match('~^https?://~i', $website)) {
            $website = 'http://' . $website;
        }
        $host = strtolower((string)preg_replace('/^www\./', '', (string)(parse_url($website, PHP_URL_HOST) ?: '')));
        foreach ($this->socialHosts as $social) {
            if ($host === $social || str_ends_with($host, '.' . $social)) {
                return Screening::accept(LeadProfile::SOCIAL_ONLY, ['o link do Google leva só para rede social'], $score + 4);
            }
        }
        return Screening::checkWebsite($website, $score);
    }

    /**
     * Stable identifier of a place, used to avoid importing the same business twice.
     *
     * @param array<string, mixed> $place
     */
    public static function placeKey(array $place): string
    {
        return substr((string)($place['placeId'] ?? $place['cid'] ?? md5(($place['title'] ?? '') . ($place['address'] ?? ''))), 0, 120);
    }
}
