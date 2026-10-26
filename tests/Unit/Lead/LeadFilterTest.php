<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Lead;

use PHPUnit\Framework\TestCase;
use Prospec\Lead\LeadFilter;

final class LeadFilterTest extends TestCase
{
    public function testInvalidValuesFallBackToAll(): void
    {
        $filter = LeadFilter::fromQuery(['status' => 'xx', 'resp' => 'zz', 'perfil' => 'yy', 'pag' => '-3', 'cidade' => ['array']]);

        self::assertSame(LeadFilter::ALL, $filter->status);
        self::assertSame(LeadFilter::ALL, $filter->ownership);
        self::assertSame('', $filter->profile);
        self::assertSame('', $filter->city);
        self::assertSame(1, $filter->page);
        self::assertSame(['', []], $filter->toSql('giu'));
    }

    public function testBuildsParameterizedConditions(): void
    {
        $filter = LeadFilter::fromQuery(['status' => 'novo', 'resp' => 'meus', 'perfil' => 'sem_site', 'cidade' => 'Leme, SP', 'nicho' => 'pet', 'pag' => '2']);

        self::assertSame(
            [' WHERE l.status=? AND l.cidade=? AND l.nicho=? AND l.perfil=? AND l.dono=?', ['novo', 'Leme, SP', 'pet', 'sem_site', 'giu']],
            $filter->toSql('giu'),
        );
        self::assertSame(2, $filter->page);
    }

    public function testUnownedLeads(): void
    {
        self::assertSame([' WHERE l.dono IS NULL', []], LeadFilter::fromQuery(['resp' => 'livres'])->toSql('giu'));
    }
}
