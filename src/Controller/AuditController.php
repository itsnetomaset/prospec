<?php

declare(strict_types=1);

namespace Prospec\Controller;

use Prospec\Http\Request;
use Prospec\Http\Response;
use Prospec\Http\Session;
use Prospec\Outreach\MessageBuilder;
use Prospec\Outreach\WhatsAppMessage;
use Prospec\Site\SiteAuditor;
use Prospec\View\View;
use Throwable;

final class AuditController
{
    private const MIN_SECONDS_BETWEEN_AUDITS = 4;

    public function __construct(
        private readonly Session $session,
        private readonly SiteAuditor $auditor,
        private readonly MessageBuilder $messages,
        private readonly View $view,
        private readonly string $agency,
    ) {
    }

    /** Runs the audit and keeps the result in the session (post/redirect/get). */
    public function analyze(Request $request): Response
    {
        if (time() - (int)$this->session->get('t_analise', 0) < self::MIN_SECONDS_BETWEEN_AUDITS) {
            $this->session->flash('error', 'Aguarde alguns segundos entre uma análise e outra.');
            return Response::redirect('./?site=1');
        }
        $this->session->set('t_analise', time());
        set_time_limit(60);
        try {
            $audit = $this->auditor->audit($request->post('url'));
        } catch (Throwable $e) {
            error_log('prospec audit: ' . $e->getMessage());
            $audit = ['error' => 'A análise falhou. Tente de novo em instantes.'];
        }
        $this->session->set('analise', $audit + [
            'company' => mb_substr(trim($request->post('empresa')), 0, 100),
            'typedUrl' => mb_substr($request->post('url'), 0, 300),
        ]);
        return Response::redirect('./?site=1');
    }

    public function index(): Response
    {
        $audit = $this->session->get('analise');
        $summary = is_array($audit) && !isset($audit['error']) ? $this->messages->auditSummary($audit, (string)$audit['company']) : '';
        return Response::html($this->view->page('audit/index', [
            'tab' => 'audit',
            'audit' => is_array($audit) ? $audit : null,
            'summary' => $summary,
            'shareUrl' => WhatsAppMessage::share($summary)->url,
            'agency' => $this->agency,
        ]));
    }
}
