<?php

declare(strict_types=1);

namespace Prospec\Controller;

use Prospec\Auth\UserDirectory;
use Prospec\Http\Request;
use Prospec\Http\Response;
use Prospec\Http\Session;
use Prospec\Lead\ActivityLog;
use Prospec\Lead\ContactRepository;
use Prospec\Lead\Lead;
use Prospec\Lead\LeadFilter;
use Prospec\Lead\LeadProfile;
use Prospec\Lead\LeadRepository;
use Prospec\Lead\LeadStatus;
use Prospec\Outreach\MessageBuilder;
use Prospec\Outreach\PhoneNumber;
use Prospec\Support\Csv;
use Prospec\View\View;

/**
 * Lead list, export and every action a salesperson takes on a lead.
 */
final class LeadController
{
    private const EXPORT_COLUMNS = ['nome', 'telefone', 'endereco', 'site', 'nota', 'avaliacoes', 'nicho', 'cidade', 'perfil', 'motivos', 'score', 'status', 'dono', 'criado'];
    private const MAX_BATCH = 50;
    private const MAX_NOTE_LENGTH = 500;
    private const MAX_SKIPPED = 500;

    public function __construct(
        private readonly Session $session,
        private readonly LeadRepository $leads,
        private readonly ContactRepository $contacts,
        private readonly ActivityLog $log,
        private readonly UserDirectory $users,
        private readonly MessageBuilder $messages,
        private readonly View $view,
        private readonly string $user,
        private readonly int $perPage,
    ) {
    }

    public function index(Request $request): Response
    {
        $filter = LeadFilter::fromQuery($request->allQuery());
        $total = $this->leads->count($filter, $this->user);
        $pages = max(1, (int)ceil($total / $this->perPage));
        $filter = new LeadFilter($filter->status, $filter->ownership, $filter->profile, $filter->city, $filter->niche, min($filter->page, $pages));

        $cards = [];
        foreach ($this->leads->page($filter, $this->user, $this->perPage, ($filter->page - 1) * $this->perPage) as $lead) {
            $cards[] = $this->card($lead);
        }
        return Response::html($this->view->page('leads/index', [
            'tab' => 'leads',
            'filter' => $filter,
            'cards' => $cards,
            'total' => $total,
            'pages' => $pages,
            'cities' => $this->leads->cities(),
            'niches' => $this->leads->niches(),
            'lastSearch' => $this->session->get('ultima'),
            'searchNonce' => $this->session->searchNonce(),
            'query' => $request->allQuery(),
        ]));
    }

    public function export(Request $request): Response
    {
        $filter = LeadFilter::fromQuery($request->allQuery());
        $csv = "\xEF\xBB\xBF" . Csv::row(self::EXPORT_COLUMNS); // BOM so Excel opens UTF-8 correctly
        foreach ($this->leads->export($filter, $this->user) as $row) {
            $csv .= Csv::row(array_map(fn (string $column) => $row[$column] ?? '', self::EXPORT_COLUMNS));
        }
        return Response::csv($csv, 'leads-' . date('Ymd') . '.csv');
    }

    public function take(int $id): void
    {
        if ($this->leads->claim($id, $this->user)) {
            $this->log->record($id, $this->user, ActivityLog::TOOK);
        } else {
            $this->session->flash('error', 'Esse lead já foi pego por outra pessoa.');
        }
    }

    /**
     * Takes the next N unowned leads that have a WhatsApp number, best first.
     */
    public function takeBatch(Request $request): Response
    {
        $wanted = max(1, min(self::MAX_BATCH, (int)$request->post('qtd')));
        $profile = LeadProfile::isValid($request->post('perfil')) ? $request->post('perfil') : '';
        $taken = 0;
        foreach ($this->leads->batchCandidates($request->post('cidade'), $request->post('nicho'), $profile) as $lead) {
            if ($taken >= $wanted) {
                break;
            }
            if ($this->messages->first($lead) === null) { // no WhatsApp-capable number
                continue;
            }
            if ($this->leads->claim($lead->id, $this->user)) {
                $this->log->record($lead->id, $this->user, ActivityLog::TOOK, 'lote');
                $taken++;
            }
        }
        $this->session->flash($taken ? 'info' : 'error', $taken ? "$taken leads na sua fila." : 'Não há leads livres com WhatsApp nesse filtro.');
        return Response::redirect('./?fila=1');
    }

    /** Queue: moves the lead to the end of the queue for this session. */
    public function skip(int $id): void
    {
        $skipped = (array)$this->session->get('pulados', []);
        $skipped[] = $id;
        $this->session->set('pulados', array_slice(array_unique($skipped), -self::MAX_SKIPPED));
    }

    /** Called when the WhatsApp button is clicked (beacon). */
    public function contacted(int $id): Response
    {
        $lead = $this->leads->find($id);
        if ($lead && $lead->isOwnedBy($this->user)) {
            $this->leads->markContacted($id);
            $this->log->record($id, $this->user, ActivityLog::CONTACTED);
        }
        return Response::noContent();
    }

    public function release(int $id): void
    {
        $lead = $this->manageable($id);
        if ($lead === null) {
            return;
        }
        $this->leads->release($id);
        $this->log->record($id, $this->user, ActivityLog::RELEASED, $lead->owner !== $this->user ? 'devolvido por admin (era de ' . $lead->owner . ')' : null);
        $this->session->flash('info', 'Lead devolvido para a fila.');
    }

    public function changeStatus(int $id, string $status): void
    {
        if ($this->manageable($id) === null || !LeadStatus::isValid($status)) {
            return;
        }
        $this->leads->setStatus($id, $status);
        $this->log->record($id, $this->user, ActivityLog::STATUS, $status);
    }

    public function addNote(int $id, string $text): void
    {
        $text = mb_substr(trim($text), 0, self::MAX_NOTE_LENGTH);
        if ($this->manageable($id) === null || $text === '') {
            return;
        }
        $this->log->record($id, $this->user, ActivityLog::NOTE, $text);
        $this->session->flash('info', 'Nota salva.');
    }

    /** WhatsApp number found by hand (website, Instagram, link in bio). */
    public function setWhatsApp(int $id, string $input): void
    {
        if ($this->manageable($id) === null) {
            return;
        }
        $number = PhoneNumber::toWhatsApp($input);
        if ($number === null) {
            $this->session->flash('error', 'Número inválido. Use DDD + 9 + número, por exemplo (17) 99104-8833.');
            return;
        }
        $formatted = PhoneNumber::format($number);
        $this->leads->updatePhone($id, $formatted);
        $this->log->record($id, $this->user, ActivityLog::PHONE, $formatted);
        $this->session->flash('info', 'WhatsApp salvo. A mensagem já está pronta no card.');
    }

    /** Decision maker's number, passed on by the front desk. An empty number removes it. */
    public function setContact(int $id, string $name, string $phone): void
    {
        if ($this->manageable($id) === null) {
            return;
        }
        if (trim($phone) === '') {
            $this->contacts->remove($id);
            $this->log->record($id, $this->user, ActivityLog::CONTACT, 'removido');
            $this->session->flash('info', 'Responsável removido.');
            return;
        }
        $number = PhoneNumber::toWhatsApp($phone);
        if ($number === null) {
            $this->session->flash('error', 'Número inválido. Use DDD + número, por exemplo (17) 99104-8833.');
            return;
        }
        $formatted = PhoneNumber::format($number);
        $name = mb_substr(trim((string)preg_replace('/\s+/', ' ', $name)), 0, 80);
        $this->contacts->save($id, $name, $formatted);
        $this->log->record($id, $this->user, ActivityLog::CONTACT, ($name !== '' ? $name . ' ' : '') . $formatted);
        $this->session->flash('info', 'Responsável salvo. A mensagem para ele já está pronta no card.');
    }

    /**
     * The lead, if the current user may change it: its owner, or an admin when the lead has an owner.
     */
    private function manageable(int $id): ?Lead
    {
        $lead = $this->leads->find($id);
        if ($lead === null || $lead->owner === null) {
            return null;
        }
        return $lead->isOwnedBy($this->user) || $this->users->isAdmin($this->user) ? $lead : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Lead $lead): array
    {
        $mine = $lead->isOwnedBy($this->user);
        $first = $mine ? $this->messages->first($lead) : null;
        return [
            'lead' => $lead,
            'mine' => $mine,
            'canManage' => $mine || ($this->users->isAdmin($this->user) && $lead->owner !== null),
            'ownerName' => $lead->owner !== null ? $this->users->displayName($lead->owner) : '',
            'first' => $first,
            'followUp' => $mine ? $this->messages->followUp($lead) : null,
            'toContact' => $mine && $lead->contact ? $this->messages->toContact($lead, $lead->contact) : null,
        ];
    }
}
