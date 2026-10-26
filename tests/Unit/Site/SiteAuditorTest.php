<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;
use Prospec\Site\FetchResult;
use Prospec\Site\SiteAuditor;
use Prospec\Tests\Support\FakeSiteChecker;

final class SiteAuditorTest extends TestCase
{
    private const GOOD_PAGE = '<html><head><meta name="viewport" content="width=device-width">'
        . '<title>Clínica Veterinária em Ribeirão Preto</title>'
        . '<meta name="description" content="Atendimento veterinário 24 horas em Ribeirão Preto">'
        . '<meta property="og:image" content="https://a.com/og.png"><script>gtag("config")</script></head>'
        . '<body><h1>Clínica</h1><a href="https://wa.me/5516">WhatsApp</a><a href="tel:+5516">Ligar</a><p>© 2026</p></body></html>';

    public function testNormalizesTypedAddresses(): void
    {
        self::assertSame('https://www.empresa.com.br', SiteAuditor::normalizeUrl(' www.empresa.com.br '));
        self::assertSame('http://empresa.com', SiteAuditor::normalizeUrl('http://empresa.com'));
        self::assertNull(SiteAuditor::normalizeUrl('empresa'));
        self::assertNull(SiteAuditor::normalizeUrl('não é url'));
        self::assertNull(SiteAuditor::normalizeUrl(''));
    }

    public function testInvalidAddressIsNotFetched(): void
    {
        $checker = new FakeSiteChecker();

        $report = (new SiteAuditor($checker))->audit('empresa');

        self::assertArrayHasKey('error', $report);
        self::assertSame([], $checker->calls);
    }

    public function testFetchesPageAndSitemap(): void
    {
        $checker = new FakeSiteChecker([
            'https://a.com/' => new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.4, self::GOOD_PAGE),
            'https://a.com/sitemap.xml' => new FetchResult(FetchResult::OK, 200, 'https://a.com/sitemap.xml', 0.1, '<urlset></urlset>'),
        ]);

        $report = (new SiteAuditor($checker))->audit('a.com/');

        self::assertSame([[0 => 'https://a.com/', 1 => 'https://a.com/sitemap.xml']], $checker->calls);
        self::assertSame([], $report['findings']);
        self::assertContains('O site tem sitemap (mapa para o Google).', $report['good']);
    }

    public function testWellBuiltSiteHasNoFindings(): void
    {
        $report = SiteAuditor::evaluate(new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.4, self::GOOD_PAGE), self::sitemap(), 'https://a.com/', 2026);

        self::assertSame([], $report['findings']);
        self::assertCount(8, $report['good']);
    }

    public function testFindingsAreSortedBySeverity(): void
    {
        $html = '<title>Home</title><p>Copyright 2019 · lorem ipsum</p><img src="a"><img src="b"><img src="c">';

        $report = SiteAuditor::evaluate(new FetchResult(FetchResult::OK, 200, 'http://a.com/', 7.2, $html), null, 'http://a.com/', 2026);

        $severities = array_column($report['findings'], 'severity');
        self::assertSame($severities, array_merge(
            array_values(array_filter($severities, fn ($s) => $s === SiteAuditor::CRITICAL)),
            array_values(array_filter($severities, fn ($s) => $s === SiteAuditor::IMPORTANT)),
            array_values(array_filter($severities, fn ($s) => $s === SiteAuditor::ATTENTION)),
        ));
        $titles = array_column($report['findings'], 'title');
        self::assertContains('Site sem HTTPS', $titles);
        self::assertContains('Site muito lento para abrir', $titles);
        self::assertContains('Texto de exemplo ou "em construção"', $titles);
        self::assertContains('Título genérico', $titles);
        self::assertContains('Imagens sem descrição', $titles);
        self::assertContains('Rodapé com ano antigo', $titles);
        self::assertSame('A página inicial levou 7,2 s para responder.', $report['findings'][array_search('Site muito lento para abrir', $titles, true)]['detail']);
    }

    public function testMixedContentOnHttpsPage(): void
    {
        $html = self::GOOD_PAGE . '<script src="http://cdn.a.com/x.js"></script>';

        $report = SiteAuditor::evaluate(new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.4, $html), self::sitemap(), 'https://a.com/', 2026);

        self::assertSame(['Elementos carregados sem segurança'], array_column($report['findings'], 'title'));
    }

    public function testDetectsSiteBuilderAsInformation(): void
    {
        $report = SiteAuditor::evaluate(new FetchResult(FetchResult::OK, 200, 'https://a.com/', 0.4, self::GOOD_PAGE . '<img src="/wp-content/x.png" alt="x">'), self::sitemap(), 'https://a.com/', 2026);

        self::assertSame(['O site parece ter sido feito em WordPress (informação, não é defeito).'], $report['notes']);
    }

    public function testReportsWhyThePageCouldNotBeAudited(): void
    {
        $down = SiteAuditor::evaluate(new FetchResult(FetchResult::DOWN), null, 'https://a.com/');
        $blocked = SiteAuditor::evaluate(new FetchResult(FetchResult::BLOCKED, 403), null, 'https://a.com/');
        $empty = SiteAuditor::evaluate(new FetchResult(FetchResult::INCONCLUSIVE), null, 'https://a.com/');

        self::assertStringStartsWith('O site não abriu', $down['error']);
        self::assertStringContainsString('(resposta 403)', $blocked['error']);
        self::assertStringStartsWith('Não consegui ler o site', $empty['error']);
        self::assertSame('a.com', $down['host']);
    }

    private static function sitemap(): FetchResult
    {
        return new FetchResult(FetchResult::OK, 200, 'https://a.com/sitemap.xml', 0.1, '<?xml version="1.0"?><sitemapindex></sitemapindex>');
    }
}
