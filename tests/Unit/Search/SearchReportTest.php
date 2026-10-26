<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Prospec\Search\SearchReport;

final class SearchReportTest extends TestCase
{
    public function testSummarizesTheSearch(): void
    {
        $report = new SearchReport(page: 2, found: 5);
        $report->added();
        $report->discard('sem telefone');
        $report->discard('site ok');
        $report->discard('sem telefone');

        self::assertSame('Página 2: 5 encontradas, 1 novas no funil. Descartadas: 2 sem telefone, 1 site ok.', $report->message());
    }

    public function testWithoutDiscards(): void
    {
        self::assertSame('Página 1: 0 encontradas, 0 novas no funil.', (new SearchReport(1, 0))->message());
    }
}
