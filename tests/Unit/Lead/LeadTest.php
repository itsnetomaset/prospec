<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Lead;

use PHPUnit\Framework\TestCase;
use Prospec\Lead\Lead;

final class LeadTest extends TestCase
{
    public function testMapsDatabaseRow(): void
    {
        $lead = Lead::fromRow([
            'id' => '7', 'place_id' => 'p1', 'nome' => 'Pet Feliz', 'endereco' => 'Rua A', 'telefone' => '16 99999-0000',
            'site' => '', 'nota' => '4.8', 'avaliacoes' => '120', 'nicho' => 'pet', 'cidade' => 'Leme, SP', 'perfil' => 'site_ruim',
            'motivos' => 'sem HTTPS; carrega devagar', 'score' => '9', 'status' => 'novo', 'dono' => 'giu', 'criado' => '2026-10-01 10:00:00',
            'resp_nome' => 'Dra. Ana', 'resp_tel' => '+55 16 99111-2222',
        ]);

        self::assertSame(7, $lead->id);
        self::assertSame(['sem HTTPS', 'carrega devagar'], $lead->reasons);
        self::assertSame(120, $lead->reviewCount);
        self::assertTrue($lead->isOwnedBy('giu'));
        self::assertSame('Dra. Ana', $lead->contact?->name);
    }

    public function testLeadWithoutOwnerOrContact(): void
    {
        $lead = Lead::fromRow(['id' => 1, 'dono' => null, 'resp_tel' => null]);

        self::assertNull($lead->owner);
        self::assertNull($lead->contact);
        self::assertFalse($lead->isOwnedBy('giu'));
    }
}
