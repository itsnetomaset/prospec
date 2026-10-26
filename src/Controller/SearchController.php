<?php

declare(strict_types=1);

namespace Prospec\Controller;

use Prospec\Http\Request;
use Prospec\Http\Session;
use Prospec\Search\LeadSearchService;
use Prospec\Search\SearchFailed;
use Prospec\Search\SearchQuota;
use Throwable;

final class SearchController
{
    private const MAX_FIELD_LENGTH = 100;
    private const TIME_LIMIT_SECONDS = 150;

    public function __construct(
        private readonly Session $session,
        private readonly LeadSearchService $search,
        private readonly SearchQuota $quota,
        private readonly string $user,
        private readonly bool $isAdmin,
    ) {
    }

    /**
     * @param bool $nextPage repeat the last search, fetching its next result page
     */
    public function run(Request $request, bool $nextPage): void
    {
        if (!$this->session->consumeSearchNonce($request->post('n'))) {
            $this->session->flash('error', 'Essa busca já tinha sido enviada, nada foi cobrado de novo.');
            return;
        }
        if ($nextPage) {
            $last = $this->session->get('ultima');
            if (!$last) {
                $this->session->flash('error', 'Faça uma busca primeiro.');
                return;
            }
            [$niche, $city] = [(string)$last['n'], (string)$last['c']];
        } else {
            $niche = mb_strtolower(trim($request->post('nicho')), 'UTF-8');
            $city = self::normalizeCity($request->post('cidade'));
        }
        if ($niche === '' || $city === '' || mb_strlen($niche) > self::MAX_FIELD_LENGTH || mb_strlen($city) > self::MAX_FIELD_LENGTH) {
            $this->session->flash('error', 'Informe nicho e cidade.');
            return;
        }
        if ($this->quota->isExhausted($this->user)) {
            $this->session->flash('error', 'Limite de ' . $this->quota->limit() . ' buscas por dia atingido.');
            return;
        }

        set_time_limit(self::TIME_LIMIT_SECONDS);
        try {
            $report = $this->search->run($this->user, $niche, $city);
        } catch (SearchFailed $e) {
            $this->session->flash('error', 'Serper: ' . $e->getMessage());
            return;
        } catch (Throwable $e) {
            error_log('prospec search: ' . $e->getMessage());
            $this->session->flash('error', 'A busca falhou' . ($this->isAdmin ? ': ' . mb_substr($e->getMessage(), 0, 300) : '. Avise o administrador.'));
            return;
        }
        $this->session->flash('info', $report->message());
        $this->session->set('ultima', ['n' => $niche, 'c' => $city]);
    }

    /** "ribeirão preto, sp" becomes "Ribeirão Preto, SP". */
    public static function normalizeCity(string $input): string
    {
        $city = mb_convert_case(trim($input), MB_CASE_TITLE, 'UTF-8');
        return preg_replace_callback('/([,\s-]\s*)([A-Za-z]{2})$/u', fn (array $m): string => $m[1] . strtoupper($m[2]), $city) ?? $city;
    }
}
