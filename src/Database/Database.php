<?php

declare(strict_types=1);

namespace Prospec\Database;

use PDO;

/**
 * Lazily opened PDO connection that can be reopened. Shared hosting MySQL drops idle
 * connections ("server has gone away") while a long website check runs, so the search
 * service reconnects before writing the results.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly ?string $user = null,
        private readonly ?string $password = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $db = $config['db'] ?? [];
        $dsn = $config['dsn'] ?? "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
        return new self($dsn, $db['user'] ?? null, $db['pass'] ?? null);
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    public function reconnect(): void
    {
        $this->pdo = $this->connect();
    }

    private function connect(): PDO
    {
        return new PDO($this->dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function isDuplicateKey(\PDOException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
