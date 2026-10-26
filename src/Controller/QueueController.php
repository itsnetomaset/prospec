<?php

declare(strict_types=1);

namespace Prospec\Controller;

use Prospec\Http\Response;
use Prospec\Http\Session;
use Prospec\Lead\LeadRepository;
use Prospec\Outreach\MessageBuilder;
use Prospec\View\View;

/**
 * One lead at a time, with the message ready: the fastest way to work through the outreach.
 */
final class QueueController
{
    public function __construct(
        private readonly Session $session,
        private readonly LeadRepository $leads,
        private readonly MessageBuilder $messages,
        private readonly View $view,
        private readonly string $user,
    ) {
    }

    public function index(): Response
    {
        $skipped = array_flip((array)$this->session->get('pulados', []));
        $queue = [];
        $withoutWhatsApp = 0;
        foreach ($this->leads->queueOf($this->user) as $lead) {
            $message = $this->messages->first($lead);
            if ($message === null) {
                $withoutWhatsApp++;
                continue;
            }
            if (!isset($skipped[$lead->id])) {
                $queue[] = [$lead, $message];
            }
        }
        return Response::html($this->view->page('queue/index', [
            'tab' => 'queue',
            'lead' => $queue[0][0] ?? null,
            'message' => $queue[0][1] ?? null,
            'queued' => count($queue),
            'withoutWhatsApp' => $withoutWhatsApp,
            'cities' => $this->leads->cities(),
            'niches' => $this->leads->niches(),
        ]));
    }
}
