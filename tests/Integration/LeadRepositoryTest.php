<?php

declare(strict_types=1);

namespace Prospec\Tests\Integration;

use Prospec\Lead\ActivityLog;
use Prospec\Lead\ContactRepository;
use Prospec\Lead\LeadFilter;
use Prospec\Lead\LeadRepository;
use Prospec\Lead\LeadStatus;

final class LeadRepositoryTest extends DatabaseTestCase
{
    private LeadRepository $leads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leads = new LeadRepository($this->db, $this->clock);
    }

    public function testInsertIgnoresDuplicatePlaces(): void
    {
        $lead = ['placeId' => 'p1', 'name' => 'Pet', 'address' => 'Rua A', 'phone' => '16 99999-0000', 'website' => '', 'rating' => 4.5,
            'reviewCount' => 20, 'niche' => 'pet', 'city' => 'Leme, SP', 'profile' => 'sem_site', 'reasons' => ['sem site no Google'], 'score' => 6];

        self::assertTrue($this->leads->insert($lead));
        self::assertFalse($this->leads->insert($lead));
        self::assertSame(['p1' => true], $this->leads->knownPlaceIds(['p1', 'p2']));
        self::assertSame('2026-10-26 10:00:00', $this->scalar('SELECT criado FROM leads'));
    }

    public function testALeadCanOnlyBeClaimedOnce(): void
    {
        $id = $this->insertLead();

        self::assertTrue($this->leads->claim($id, 'giu'));
        self::assertFalse($this->leads->claim($id, 'ana'));
        self::assertSame('giu', $this->leads->find($id)?->owner);

        $this->leads->release($id);
        self::assertTrue($this->leads->claim($id, 'ana'));
    }

    public function testMarkContactedOnlyMovesNewLeads(): void
    {
        $new = $this->insertLead();
        $replied = $this->insertLead(['status' => 'respondeu']);

        $this->leads->markContacted($new);
        $this->leads->markContacted($replied);

        self::assertSame(LeadStatus::CONTACTED, $this->leads->find($new)?->status);
        self::assertSame('respondeu', $this->leads->find($replied)?->status);
    }

    public function testListIsFilteredSortedAndCarriesLastActivity(): void
    {
        $best = $this->insertLead(['score' => 9, 'dono' => 'giu']);
        $this->insertLead(['score' => 3, 'dono' => 'ana']);
        $this->insertLead(['score' => 7]);
        $log = new ActivityLog($this->db, $this->clock);
        $log->record($best, 'giu', ActivityLog::NOTE, 'primeira');
        $log->record($best, 'giu', ActivityLog::NOTE, 'ligar segunda');
        $log->record($best, 'giu', ActivityLog::CONTACTED);
        (new ContactRepository($this->db, $this->clock))->save($best, 'Dra. Ana', '+55 16 99111-2222');

        $all = $this->leads->page(new LeadFilter(), 'giu', 50, 0);
        $mine = $this->leads->page(new LeadFilter(ownership: LeadFilter::MINE), 'giu', 50, 0);

        self::assertSame([9, 7, 3], array_map(fn ($lead) => $lead->score, $all));
        self::assertSame(3, $this->leads->count(new LeadFilter(), 'giu'));
        self::assertCount(1, $mine);
        self::assertSame('ligar segunda', $mine[0]->lastNote);
        self::assertSame('2026-10-26 10:00:00', $mine[0]->lastContactAt);
        self::assertSame('Dra. Ana', $mine[0]->contact?->name);
        self::assertSame(1, $this->leads->count(new LeadFilter(ownership: LeadFilter::UNOWNED), 'giu'));
    }

    public function testBatchCandidatesAreUnownedNewLeads(): void
    {
        $this->insertLead(['score' => 9, 'dono' => 'ana']);
        $this->insertLead(['score' => 8, 'status' => 'perdido']);
        $wanted = $this->insertLead(['score' => 7, 'perfil' => 'so_rede']);
        $this->insertLead(['score' => 6, 'cidade' => 'Pirassununga, SP']);

        $candidates = $this->leads->batchCandidates('Leme, SP', '', '');

        self::assertSame([$wanted], array_map(fn ($lead) => $lead->id, $candidates));
        self::assertSame([], $this->leads->batchCandidates('', '', 'site_ruim'));
    }

    public function testQueueHasOnlyTheUsersNewLeads(): void
    {
        $this->insertLead(['dono' => 'giu', 'score' => 2]);
        $this->insertLead(['dono' => 'giu', 'score' => 5]);
        $this->insertLead(['dono' => 'giu', 'status' => 'contatado']);
        $this->insertLead(['dono' => 'ana']);

        self::assertSame([5, 2], array_map(fn ($lead) => $lead->score, $this->leads->queueOf('giu')));
    }

    public function testExportAndDistinctValues(): void
    {
        $this->insertLead(['cidade' => 'Leme, SP', 'nicho' => 'pet']);
        $this->insertLead(['cidade' => 'Araras, SP', 'nicho' => 'dentista']);

        self::assertSame(['Araras, SP', 'Leme, SP'], $this->leads->cities());
        self::assertSame(['dentista', 'pet'], $this->leads->niches());
        self::assertCount(1, $this->leads->export(new LeadFilter(city: 'Araras, SP'), 'giu'));
    }

    public function testContactCanBeReplacedAndRemoved(): void
    {
        $id = $this->insertLead();
        $contacts = new ContactRepository($this->db, $this->clock);

        $contacts->save($id, 'Dra. Ana', '+55 16 99111-2222');
        $contacts->save($id, 'Dra. Bia', '+55 16 99111-3333');
        self::assertSame('Dra. Bia', $this->scalar('SELECT nome FROM leads_resp'));
        self::assertSame(1, (int)$this->scalar('SELECT COUNT(*) FROM leads_resp'));

        $contacts->remove($id);
        self::assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM leads_resp'));
    }
}
