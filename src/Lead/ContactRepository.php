<?php

declare(strict_types=1);

namespace Prospec\Lead;

use PDOException;
use Prospec\Database\Database;
use Prospec\Support\Clock;

/**
 * Decision maker of each lead (table `leads_resp`, one row per lead).
 */
final class ContactRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function save(int $leadId, string $name, string $phone): void
    {
        $now = date('Y-m-d H:i:s', $this->clock->now());
        $pdo = $this->db->pdo();
        $update = $pdo->prepare('UPDATE leads_resp SET nome=?, telefone=?, atualizado=? WHERE lead_id=?');
        $update->execute([$name, $phone, $now, $leadId]);
        if ($update->rowCount() > 0) {
            return;
        }
        try {
            $pdo->prepare('INSERT INTO leads_resp (lead_id, nome, telefone, atualizado) VALUES (?,?,?,?)')->execute([$leadId, $name, $phone, $now]);
        } catch (PDOException $e) {
            if (!Database::isDuplicateKey($e)) {
                throw $e;
            }
        }
    }

    public function remove(int $leadId): void
    {
        $this->db->pdo()->prepare('DELETE FROM leads_resp WHERE lead_id=?')->execute([$leadId]);
    }
}
