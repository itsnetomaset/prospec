<?php

declare(strict_types=1);

namespace Prospec\Tests\Integration;

use Prospec\Lead\LeadRepository;
use Prospec\Search\LeadQualifier;
use Prospec\Search\LeadSearchService;
use Prospec\Search\SearchFailed;
use Prospec\Search\SearchHistory;
use Prospec\Search\SearchProvider;
use Prospec\Search\SearchQuota;
use Prospec\Site\FetchResult;
use Prospec\Tests\Support\FakeSearchProvider;
use Prospec\Tests\Support\FakeSiteChecker;

final class LeadSearchServiceTest extends DatabaseTestCase
{
    private SearchQuota $quota;
    private SearchHistory $history;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quota = new SearchQuota($this->db, $this->clock, dailyLimit: 30);
        $this->history = new SearchHistory($this->db, $this->clock);
    }

    public function testQualifiesAndStoresLeads(): void
    {
        $phone = '(16) 99123-4567';
        $provider = new FakeSearchProvider([1 => [
            ['placeId' => 'a', 'title' => 'Sem Site', 'phoneNumber' => $phone, 'ratingCount' => 120, 'rating' => 4.8],
            ['placeId' => 'b', 'title' => 'Só Insta', 'phoneNumber' => $phone, 'ratingCount' => 45, 'website' => 'https://instagram.com/b'],
            ['placeId' => 'c', 'title' => 'Site Fraco', 'phoneNumber' => $phone, 'ratingCount' => 60, 'website' => 'http://fraco.com.br'],
            ['placeId' => 'd', 'title' => 'Site Bom', 'phoneNumber' => $phone, 'ratingCount' => 60, 'website' => 'https://bom.com.br'],
            ['placeId' => 'e', 'title' => 'Bloqueado', 'phoneNumber' => $phone, 'ratingCount' => 60, 'website' => 'https://bloq.com.br'],
            ['placeId' => 'f', 'title' => 'Sem Telefone', 'ratingCount' => 60],
            ['placeId' => 'g', 'title' => 'Poucas', 'phoneNumber' => $phone, 'ratingCount' => 2],
        ]]);
        $checker = new FakeSiteChecker([
            'http://fraco.com.br' => new FetchResult(FetchResult::OK, 200, 'http://fraco.com.br', 0.5, '<p>sem nada</p>'),
            'https://bom.com.br' => new FetchResult(FetchResult::OK, 200, 'https://bom.com.br', 0.5, '<meta name="viewport" content="x">wa.me'),
            'https://bloq.com.br' => new FetchResult(FetchResult::BLOCKED, 403),
        ]);

        $report = $this->service($provider, $checker)->run('giu', 'pet', 'Leme, SP');

        self::assertSame('Página 1: 7 encontradas, 3 novas no funil. Descartadas: 1 sem telefone, 1 poucas avaliacoes, 1 site ok, 1 site sem resposta conclusiva.', $report->message());
        self::assertSame([['http://fraco.com.br', 'https://bom.com.br', 'https://bloq.com.br']], array_map('array_values', $checker->calls));
        self::assertSame(
            [['a', 'sem_site', 9], ['b', 'so_rede', 6], ['c', 'site_ruim', 7]],
            $this->db->pdo()->query('SELECT place_id, perfil, score FROM leads ORDER BY place_id')->fetchAll(\PDO::FETCH_NUM),
        );
        self::assertSame('sem HTTPS; não adaptado ao celular; sem botão de WhatsApp', $this->scalar("SELECT motivos FROM leads WHERE place_id='c'"));
        self::assertSame(1, $this->quota->usedToday('giu'));
        self::assertSame(1, $this->history->lastPage('pet', 'Leme, SP'));
    }

    public function testNextSearchFetchesTheNextPageAndSkipsKnownPlaces(): void
    {
        $place = ['placeId' => 'a', 'title' => 'Pet', 'phoneNumber' => '16 99999-0000', 'ratingCount' => 20];
        $provider = new FakeSearchProvider([1 => [$place], 2 => [$place]]);
        $service = $this->service($provider, new FakeSiteChecker());

        $service->run('giu', 'pet', 'Leme, SP');
        $report = $service->run('giu', 'pet', 'Leme, SP');

        self::assertSame([['pet', 'Leme, SP', 1], ['pet', 'Leme, SP', 2]], $provider->calls);
        self::assertSame('Página 2: 1 encontradas, 0 novas no funil. Descartadas: 1 já conhecidas.', $report->message());
        self::assertSame(2, $this->quota->usedToday('giu'));
    }

    public function testFailedSearchDoesNotSpendQuota(): void
    {
        $service = $this->service(new FakeSearchProvider(failure: 'Unauthorized'), new FakeSiteChecker());

        try {
            $service->run('giu', 'pet', 'Leme, SP');
            self::fail('SearchFailed expected');
        } catch (SearchFailed $e) {
            self::assertSame('Unauthorized', $e->getMessage());
        }
        self::assertSame(0, $this->quota->usedToday('giu'));
        self::assertSame(0, $this->history->lastPage('pet', 'Leme, SP'));
    }

    public function testQuotaResetsEveryDay(): void
    {
        $this->quota->increment('giu');
        $this->quota->increment('giu');
        self::assertSame(2, $this->quota->usedToday('giu'));

        $this->clock->advance(86400);
        self::assertSame(0, $this->quota->usedToday('giu'));
        self::assertFalse($this->quota->isExhausted('giu'));
    }

    private function service(SearchProvider $provider, FakeSiteChecker $checker): LeadSearchService
    {
        return new LeadSearchService($provider, new LeadQualifier(9), $checker, new LeadRepository($this->db, $this->clock),
            $this->history, $this->quota, $this->db);
    }
}
