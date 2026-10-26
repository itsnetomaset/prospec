<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;
use Prospec\Site\FetchResult;
use Prospec\Site\QuickRating;

final class QuickRatingTest extends TestCase
{
    private const GOOD_HTML = '<meta name="viewport" content="width=device-width"><a href="https://wa.me/55">WhatsApp</a><p>© 2026</p>';

    public function testGoodWebsiteIsNotAnOpportunity(): void
    {
        $rating = QuickRating::rate(new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.8, self::GOOD_HTML), 2026);

        self::assertSame(QuickRating::GOOD, $rating->verdict);
        self::assertSame([], $rating->reasons);
    }

    public function testAddsUpTheIssues(): void
    {
        $rating = QuickRating::rate(new FetchResult(FetchResult::OK, 200, 'http://a.com/', 5.0, '<p>© 2019</p>'), 2026);

        self::assertSame(QuickRating::WEAK, $rating->verdict);
        self::assertSame(['sem HTTPS', 'não adaptado ao celular', 'carrega devagar', 'sem botão de WhatsApp', 'rodapé desatualizado'], $rating->reasons);
        self::assertSame(7, $rating->points);
    }

    public function testInvalidCertificateWeighsMoreThanMissingHttps(): void
    {
        $rating = QuickRating::rate(new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.5, self::GOOD_HTML, invalidCertificate: true), 2026);

        self::assertSame(['certificado inválido'], $rating->reasons);
        self::assertSame(3, $rating->points);
    }

    public function testSingleMinorIssueIsStillGood(): void
    {
        $rating = QuickRating::rate(new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.5, '<meta name="viewport" content="x">'), 2026);

        self::assertSame(QuickRating::GOOD, $rating->verdict);
        self::assertSame(['sem botão de WhatsApp'], $rating->reasons);
    }

    public function testSiteThatDoesNotOpenIsAnOpportunity(): void
    {
        $rating = QuickRating::rate(new FetchResult(FetchResult::DOWN));

        self::assertSame(QuickRating::WEAK, $rating->verdict);
        self::assertSame(['site não abriu'], $rating->reasons);
    }

    public function testBlockedOrEmptyResponsesProveNothing(): void
    {
        foreach ([FetchResult::BLOCKED, FetchResult::INCONCLUSIVE, FetchResult::INVALID] as $status) {
            self::assertSame(QuickRating::INCONCLUSIVE, QuickRating::rate(new FetchResult($status))->verdict, $status);
        }
    }
}
