<?php

declare(strict_types=1);

namespace Prospec\Lead;

use Prospec\Database\Database;
use Prospec\Support\Clock;

/**
 * Append-only history of what each user did with each lead (table `lead_log`).
 */
final class ActivityLog
{
    public const TOOK = 'pegou';
    public const RELEASED = 'devolveu';
    public const CONTACTED = 'contatou';
    public const STATUS = 'status';
    public const NOTE = 'nota';
    public const PHONE = 'telefone';
    public const CONTACT = 'responsavel';

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function record(int $leadId, string $user, string $action, ?string $detail = null): void
    {
        $this->db->pdo()->prepare('INSERT INTO lead_log (lead_id, usuario, acao, detalhe, criado) VALUES (?,?,?,?,?)')
            ->execute([$leadId, $user, $action, $detail, date('Y-m-d H:i:s', $this->clock->now())]);
    }
}
