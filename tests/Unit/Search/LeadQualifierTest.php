<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Prospec\Lead\LeadProfile;
use Prospec\Search\LeadQualifier;

final class LeadQualifierTest extends TestCase
{
    private LeadQualifier $qualifier;

    protected function setUp(): void
    {
        $this->qualifier = new LeadQualifier(minReviews: 9);
    }

    public function testDiscardsBusinessesWithoutPhoneOrWithFewReviews(): void
    {
        self::assertSame('sem telefone', $this->qualifier->screen(['ratingCount' => 300])->discardReason);
        self::assertSame('poucas avaliacoes', $this->qualifier->screen(['phoneNumber' => '16 99999-0000', 'ratingCount' => 8])->discardReason);
    }

    public function testBusinessWithoutWebsiteIsTheBestLead(): void
    {
        $screening = $this->qualifier->screen(['phoneNumber' => '16 99999-0000', 'ratingCount' => 120, 'rating' => 4.8]);

        self::assertFalse($screening->isDiscarded());
        self::assertSame(LeadProfile::NO_WEBSITE, $screening->profile);
        self::assertSame(9, $screening->score); // 3 (100+ reviews) + 1 (rating 4.3+) + 5
    }

    public function testSocialMediaLinkCountsAsNoWebsite(): void
    {
        foreach (['https://www.instagram.com/pet', 'm.facebook.com/pet', 'https://linktr.ee/pet'] as $url) {
            $screening = $this->qualifier->screen(['phoneNumber' => '16 99999-0000', 'ratingCount' => 45, 'rating' => 4.1, 'website' => $url]);
            self::assertSame(LeadProfile::SOCIAL_ONLY, $screening->profile, $url);
            self::assertSame(6, $screening->score, $url); // 2 (40+ reviews) + 4
        }
    }

    public function testLookalikeDomainIsNotSocialMedia(): void
    {
        $screening = $this->qualifier->screen(['phoneNumber' => '16 99999-0000', 'ratingCount' => 10, 'website' => 'https://notinstagram.com']);

        self::assertTrue($screening->needsWebsiteCheck());
    }

    public function testRealWebsiteNeedsToBeChecked(): void
    {
        $screening = $this->qualifier->screen(['phoneNumber' => '16 99999-0000', 'ratingCount' => 10, 'rating' => 4.0, 'website' => 'clinica.com.br']);

        self::assertTrue($screening->needsWebsiteCheck());
        self::assertSame('http://clinica.com.br', $screening->websiteToCheck);
        self::assertSame(1, $screening->score);
    }

    public function testPlaceKeyPrefersGoogleIds(): void
    {
        self::assertSame('abc', LeadQualifier::placeKey(['placeId' => 'abc', 'cid' => '1']));
        self::assertSame('123', LeadQualifier::placeKey(['cid' => '123']));
        self::assertSame(md5('Pet' . 'Rua A'), LeadQualifier::placeKey(['title' => 'Pet', 'address' => 'Rua A']));
    }
}
