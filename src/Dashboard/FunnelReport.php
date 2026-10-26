<?php

declare(strict_types=1);

namespace Prospec\Dashboard;

use PDO;
use Prospec\Database\Database;
use Prospec\Lead\LeadStatus;

/**
 * Conversion numbers for the dashboard.
 */
final class FunnelReport
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Approached = contacted, replied, meeting, closed or lost.
     *
     * @return list<array{nicho: string, perfil: string, total: int, abordados: int, responderam: int, reunioes: int, fechados: int}>
     */
    public function byNicheAndProfile(): array
    {
        $approached = "'" . implode("','", LeadStatus::APPROACHED) . "'";
        return $this->db->pdo()->query("SELECT nicho, perfil, COUNT(*) AS total,
            SUM(CASE WHEN status IN ($approached) THEN 1 ELSE 0 END) AS abordados,
            SUM(CASE WHEN status IN ('respondeu','reuniao','fechado') THEN 1 ELSE 0 END) AS responderam,
            SUM(CASE WHEN status IN ('reuniao','fechado') THEN 1 ELSE 0 END) AS reunioes,
            SUM(CASE WHEN status = 'fechado' THEN 1 ELSE 0 END) AS fechados
            FROM leads GROUP BY nicho, perfil ORDER BY total DESC")->fetchAll();
    }

    /** @return array<string, int> */
    public function byStatus(): array
    {
        return $this->db->pdo()->query('SELECT status, COUNT(*) AS n FROM leads GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return list<array{dono: string, pegos: int, trabalhados: int}> */
    public function byOwner(): array
    {
        return $this->db->pdo()->query("SELECT dono, COUNT(*) AS pegos, SUM(CASE WHEN status <> 'novo' THEN 1 ELSE 0 END) AS trabalhados
            FROM leads WHERE dono IS NOT NULL GROUP BY dono")->fetchAll();
    }

    public static function rate(int $part, int $whole): string
    {
        return $whole > 0 ? round(100 * $part / $whole) . '%' : '-';
    }
}
