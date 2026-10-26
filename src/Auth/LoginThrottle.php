<?php

declare(strict_types=1);

namespace Prospec\Auth;

use Prospec\Database\Database;
use Prospec\Support\Clock;

/**
 * Failed login attempts per IP and per username (table `login_tentativas`).
 */
final class LoginThrottle
{
    public const MAX_FAILURES = 8;
    public const WINDOW_SECONDS = 900;
    private const RETENTION_SECONDS = 86400;

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function isLocked(string $ip, string $user): bool
    {
        $q = $this->db->pdo()->prepare('SELECT COUNT(*) FROM login_tentativas WHERE criado > ? AND (ip = ? OR usuario = ?)');
        $q->execute([$this->time(-self::WINDOW_SECONDS), $ip, $user]);
        return (int)$q->fetchColumn() >= self::MAX_FAILURES;
    }

    public function recordFailure(string $ip, string $user): void
    {
        $pdo = $this->db->pdo();
        $pdo->prepare('INSERT INTO login_tentativas (ip, usuario, criado) VALUES (?,?,?)')->execute([$ip, $user, $this->time()]);
        if (random_int(1, 20) === 1) { // occasional cleanup, no cron needed
            $pdo->prepare('DELETE FROM login_tentativas WHERE criado < ?')->execute([$this->time(-self::RETENTION_SECONDS)]);
        }
    }

    public function clear(string $ip, string $user): void
    {
        $this->db->pdo()->prepare('DELETE FROM login_tentativas WHERE ip = ? OR usuario = ?')->execute([$ip, $user]);
    }

    private function time(int $offset = 0): string
    {
        return date('Y-m-d H:i:s', $this->clock->now() + $offset);
    }
}
