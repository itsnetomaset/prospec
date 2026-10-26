<?php

declare(strict_types=1);

namespace Prospec\Auth;

use PDOException;
use Prospec\Database\Database;
use Prospec\Support\Clock;

/**
 * Passwords chosen by the users (table `senhas`). Until a user picks one, the initial
 * hash from config.php applies.
 */
final class PasswordStore
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function hashOf(string $user): ?string
    {
        $q = $this->db->pdo()->prepare('SELECT hash FROM senhas WHERE usuario=?');
        $q->execute([$user]);
        $hash = $q->fetchColumn();
        return $hash === false ? null : (string)$hash;
    }

    public function save(string $user, string $hash): void
    {
        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s', $this->clock->now());
        $update = $pdo->prepare('UPDATE senhas SET hash=?, atualizado=? WHERE usuario=?');
        $update->execute([$hash, $now, $user]);
        if ($update->rowCount() > 0) {
            return;
        }
        try {
            $pdo->prepare('INSERT INTO senhas (usuario, hash, atualizado) VALUES (?,?,?)')->execute([$user, $hash, $now]);
        } catch (PDOException) {
            $update->execute([$hash, $now, $user]);
        }
    }
}
