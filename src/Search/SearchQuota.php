<?php

declare(strict_types=1);

namespace Prospec\Search;

use PDOException;
use Prospec\Database\Database;
use Prospec\Support\Clock;

/**
 * Daily number of paid searches per user (table `uso_busca`).
 */
final class SearchQuota
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
        private readonly int $dailyLimit,
    ) {
    }

    public function limit(): int
    {
        return $this->dailyLimit;
    }

    public function usedToday(string $user): int
    {
        $q = $this->db->pdo()->prepare('SELECT qtd FROM uso_busca WHERE usuario=? AND dia=?');
        $q->execute([$user, $this->today()]);
        return (int)$q->fetchColumn();
    }

    public function isExhausted(string $user): bool
    {
        return $this->usedToday($user) >= $this->dailyLimit;
    }

    public function increment(string $user): void
    {
        $pdo = $this->db->pdo();
        $day = $this->today();
        $update = $pdo->prepare('UPDATE uso_busca SET qtd=qtd+1 WHERE usuario=? AND dia=?');
        $update->execute([$user, $day]);
        if ($update->rowCount() > 0) {
            return;
        }
        try {
            $pdo->prepare('INSERT INTO uso_busca (usuario, dia, qtd) VALUES (?,?,1)')->execute([$user, $day]);
        } catch (PDOException) {
            $update->execute([$user, $day]); // a concurrent request inserted the row first
        }
    }

    private function today(): string
    {
        return date('Y-m-d', $this->clock->now());
    }
}
