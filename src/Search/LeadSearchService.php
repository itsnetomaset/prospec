<?php

declare(strict_types=1);

namespace Prospec\Search;

use Prospec\Database\Database;
use Prospec\Lead\LeadProfile;
use Prospec\Lead\LeadRepository;
use Prospec\Site\QuickRating;
use Prospec\Site\SiteChecker;

/**
 * Turns one paid search ("niche in city") into qualified leads:
 * fetch the next result page, drop known and unqualified businesses, check the websites
 * of the remaining ones in parallel and store the weak ones.
 */
final class LeadSearchService
{
    private const WEBSITE_CHECK_DEADLINE = 90.0;

    public function __construct(
        private readonly SearchProvider $provider,
        private readonly LeadQualifier $qualifier,
        private readonly SiteChecker $checker,
        private readonly LeadRepository $leads,
        private readonly SearchHistory $history,
        private readonly SearchQuota $quota,
        private readonly Database $db,
    ) {
    }

    /**
     * @throws SearchFailed when the search provider fails (no credit is counted)
     */
    public function run(string $user, string $niche, string $city): SearchReport
    {
        $page = $this->history->lastPage($niche, $city) + 1;
        $places = $this->provider->places($niche, $city, $page);
        $this->quota->increment($user);

        $keys = array_map([LeadQualifier::class, 'placeKey'], $places);
        $known = $this->leads->knownPlaceIds($keys);
        $report = new SearchReport($page, count($places));

        $accepted = [];
        $toCheck = [];
        foreach ($places as $i => $place) {
            if (isset($known[$keys[$i]])) {
                $report->discard('já conhecidas');
                continue;
            }
            $screening = $this->qualifier->screen($place);
            if ($screening->isDiscarded()) {
                $report->discard((string)$screening->discardReason);
                continue;
            }
            $accepted[$i] = ['profile' => $screening->profile, 'reasons' => $screening->reasons, 'score' => $screening->score];
            if ($screening->needsWebsiteCheck()) {
                $toCheck[$i] = (string)$screening->websiteToCheck;
            }
        }

        if ($toCheck) {
            $results = $this->checker->check($toCheck, self::WEBSITE_CHECK_DEADLINE);
            foreach (array_keys($toCheck) as $i) {
                $rating = QuickRating::rate($results[$i]);
                if ($rating->verdict === QuickRating::INCONCLUSIVE) {
                    $report->discard('site sem resposta conclusiva');
                    unset($accepted[$i]);
                } elseif ($rating->verdict === QuickRating::GOOD) {
                    $report->discard('site ok');
                    unset($accepted[$i]);
                } else {
                    $accepted[$i] = ['profile' => LeadProfile::WEAK_WEBSITE, 'reasons' => $rating->reasons, 'score' => $accepted[$i]['score'] + $rating->points];
                }
            }
            // Checking websites can take over a minute; reopen the connection before writing.
            $this->db->reconnect();
        }

        foreach ($accepted as $i => $lead) {
            $place = $places[$i];
            $stored = $this->leads->insert([
                'placeId' => $keys[$i],
                'name' => (string)($place['title'] ?? ''),
                'address' => (string)($place['address'] ?? ''),
                'phone' => (string)($place['phoneNumber'] ?? ''),
                'website' => (string)($place['website'] ?? ''),
                'rating' => (float)($place['rating'] ?? 0),
                'reviewCount' => (int)($place['ratingCount'] ?? 0),
                'niche' => $niche,
                'city' => $city,
                'profile' => (string)$lead['profile'],
                'reasons' => $lead['reasons'],
                'score' => (int)$lead['score'],
            ]);
            if ($stored) {
                $report->added();
            }
        }
        $this->history->savePage($niche, $city, $page);
        return $report;
    }
}
