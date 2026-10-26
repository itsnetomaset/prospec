<?php

declare(strict_types=1);

namespace Prospec;

use Prospec\Auth\AuthService;
use Prospec\Auth\LoginThrottle;
use Prospec\Auth\PasswordStore;
use Prospec\Auth\UserDirectory;
use Prospec\Controller\AuditController;
use Prospec\Controller\AuthController;
use Prospec\Controller\DashboardController;
use Prospec\Controller\LeadController;
use Prospec\Controller\QueueController;
use Prospec\Controller\SearchController;
use Prospec\Dashboard\FunnelReport;
use Prospec\Database\Database;
use Prospec\Geo\CityDirectory;
use Prospec\Http\Request;
use Prospec\Http\Response;
use Prospec\Http\Session;
use Prospec\Lead\ActivityLog;
use Prospec\Lead\ContactRepository;
use Prospec\Lead\LeadRepository;
use Prospec\Outreach\MessageBuilder;
use Prospec\Search\LeadQualifier;
use Prospec\Search\LeadSearchService;
use Prospec\Search\SearchHistory;
use Prospec\Search\SearchQuota;
use Prospec\Search\SerperClient;
use Prospec\Site\NetworkGuard;
use Prospec\Site\SiteAuditor;
use Prospec\Site\SiteFetcher;
use Prospec\Support\Clock;
use Prospec\Support\SystemClock;
use Prospec\View\View;

/**
 * Front controller: wires the services and routes each request.
 *
 * Routes keep the original URL scheme so bookmarks keep working:
 * GET  /            lead list          GET ?fila=1   outreach queue
 * GET  ?site=1      website audit      GET ?painel=1 dashboard
 * GET  ?csv=1       export (admin)     GET ?cid=...  city autocomplete (JSON)
 * POST a=<action>   lead actions, always with a CSRF token, followed by a redirect
 */
final class App
{
    private readonly Database $db;
    private readonly Clock $clock;
    private readonly UserDirectory $users;
    private readonly View $view;

    /**
     * @param array<string, mixed> $config contents of config.php
     */
    public function __construct(
        private readonly array $config,
        private readonly string $root,
        ?Database $db = null,
        ?Clock $clock = null,
    ) {
        $this->db = $db ?? Database::fromConfig($config);
        $this->clock = $clock ?? new SystemClock();
        $this->users = new UserDirectory((array)($config['users'] ?? []));
        $this->view = new View($root . '/views');
    }

    public function handle(Request $request, Session $session): Response
    {
        $this->view->share('base', $request->basePath());
        $this->view->share('csrf', $session->csrfToken());
        $auth = new AuthController($session, $this->authService(), $this->users, $this->view);

        $user = $session->user();
        if ($user !== null && !$this->users->exists($user)) { // removed from config.php
            $session->forgetUser();
            $user = null;
        }
        if ($user === null) {
            return $request->hasPost('login') ? $auth->login($request) : $auth->loginForm();
        }
        if ($session->mustChangePassword()) {
            return $auth->changePassword($request);
        }
        if ($request->hasQuery('cid')) {
            return Response::json($this->cities()->suggest($request->query('cid')));
        }
        if ($request->isPost()) {
            return $this->dispatchAction($request, $session, $user, $auth);
        }
        return $this->dispatchPage($request, $session, $user);
    }

    private function dispatchAction(Request $request, Session $session, string $user, AuthController $auth): Response
    {
        if (!$session->isValidCsrf($request->post('t'))) {
            return Response::forbidden('Sessão expirada. Recarregue a página.');
        }
        $leads = $this->leadController($session, $user);
        $id = (int)$request->post('id');

        switch ($request->post('a')) {
            case 'trocarsenha':
                return $auth->requestPasswordChange();
            case 'sair':
                return $auth->logout($request);
            case 'contatou':
                return $leads->contacted($id);
            case 'analisar':
                return $this->auditController($session)->analyze($request);
            case 'pegarlote':
                return $leads->takeBatch($request);
            case 'pular':
                $leads->skip($id);
                break;
            case 'pegar':
                $leads->take($id);
                break;
            case 'devolver':
                $leads->release($id);
                break;
            case 'status':
                $leads->changeStatus($id, $request->post('status'));
                break;
            case 'nota':
                $leads->addNote($id, $request->post('texto'));
                break;
            case 'whatsapp':
                $leads->setWhatsApp($id, $request->post('zap'));
                break;
            case 'responsavel':
                $leads->setContact($id, $request->post('nome'), $request->post('zap'));
                break;
            case 'buscar':
            case 'proxima':
                $this->searchController($session, $user)->run($request, $request->post('a') === 'proxima');
                break;
        }
        return Response::redirect($request->currentUrl());
    }

    private function dispatchPage(Request $request, Session $session, string $user): Response
    {
        $isAdmin = $this->users->isAdmin($user);
        if ($request->hasQuery('csv') && $isAdmin) {
            return $this->leadController($session, $user)->export($request);
        }
        $quota = $this->quota();
        $this->view->share('flash', $session->pullFlash());
        $this->view->share('userName', $this->users->displayName($user));
        $this->view->share('isAdmin', $isAdmin);
        $this->view->share('searchesToday', $quota->usedToday($user));
        $this->view->share('searchLimit', $quota->limit());
        $this->view->share('csvUrl', list_url($request->allQuery(), ['csv' => '1']));

        if ($request->hasQuery('painel')) {
            return (new DashboardController(new FunnelReport($this->db), $this->users, $this->view))->index();
        }
        if ($request->hasQuery('fila')) {
            return (new QueueController($session, $this->leads(), $this->messages($user), $this->view, $user))->index();
        }
        if ($request->hasQuery('site')) {
            return $this->auditController($session)->index();
        }
        return $this->leadController($session, $user)->index($request);
    }

    private function authService(): AuthService
    {
        return new AuthService($this->users, new PasswordStore($this->db, $this->clock), new LoginThrottle($this->db, $this->clock));
    }

    private function leads(): LeadRepository
    {
        return new LeadRepository($this->db, $this->clock);
    }

    private function quota(): SearchQuota
    {
        return new SearchQuota($this->db, $this->clock, (int)($this->config['limite_buscas_dia'] ?? 30));
    }

    private function messages(string $user): MessageBuilder
    {
        return new MessageBuilder($this->users->displayName($user), (string)($this->config['agencia'] ?? ''));
    }

    private function leadController(Session $session, string $user): LeadController
    {
        $perPage = max(10, min(200, (int)($this->config['por_pagina'] ?? 50)));
        return new LeadController($session, $this->leads(), new ContactRepository($this->db, $this->clock),
            new ActivityLog($this->db, $this->clock), $this->users, $this->messages($user), $this->view, $user, $perPage);
    }

    private function searchController(Session $session, string $user): SearchController
    {
        $service = new LeadSearchService(
            new SerperClient((string)$this->config['serper_key'], (string)($this->config['serper_url'] ?? 'https://google.serper.dev/maps')),
            new LeadQualifier((int)($this->config['min_avaliacoes'] ?? 9)),
            $this->fetcher(),
            $this->leads(),
            new SearchHistory($this->db, $this->clock),
            $this->quota(),
            $this->db,
        );
        return new SearchController($session, $service, $this->quota(), $user, $this->users->isAdmin($user));
    }

    private function auditController(Session $session): AuditController
    {
        $user = (string)$session->user();
        return new AuditController($session, new SiteAuditor($this->fetcher()), $this->messages($user), $this->view, (string)($this->config['agencia'] ?? ''));
    }

    private function fetcher(): SiteFetcher
    {
        $verifySsl = (bool)($this->config['verificar_ssl'] ?? SiteFetcher::serverTrustsCertificates());
        return new SiteFetcher(new NetworkGuard((bool)($this->config['permitir_ip_privado'] ?? false)), $verifySsl);
    }

    private function cities(): CityDirectory
    {
        $history = new SearchHistory($this->db, $this->clock);
        return new CityDirectory($this->root . '/data/cidades_ibge.json', fn (): array => $history->searchedCities());
    }
}
