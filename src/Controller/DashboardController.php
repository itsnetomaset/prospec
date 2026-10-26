<?php

declare(strict_types=1);

namespace Prospec\Controller;

use Prospec\Auth\UserDirectory;
use Prospec\Dashboard\FunnelReport;
use Prospec\Http\Response;
use Prospec\View\View;

final class DashboardController
{
    public function __construct(
        private readonly FunnelReport $report,
        private readonly UserDirectory $users,
        private readonly View $view,
    ) {
    }

    public function index(): Response
    {
        return Response::html($this->view->page('dashboard/index', [
            'tab' => 'dashboard',
            'byNiche' => $this->report->byNicheAndProfile(),
            'byStatus' => $this->report->byStatus(),
            'byOwner' => $this->report->byOwner(),
            'users' => $this->users,
        ]));
    }
}
