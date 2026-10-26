<?php

declare(strict_types=1);

namespace Prospec\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Prospec\Database\Database;
use Prospec\Tests\Support\FixedClock;

/**
 * Runs against a fresh SQLite file per test (a file, not :memory:, so reconnecting keeps the data).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Database $db;
    protected FixedClock $clock;
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'prospec-test-');
        $this->db = new Database('sqlite:' . $this->file);
        $this->db->pdo()->exec((string)file_get_contents(dirname(__DIR__) . '/schema.sqlite.sql'));
        $this->clock = new FixedClock(strtotime('2026-10-26 10:00:00'));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function insertLead(array $overrides = []): int
    {
        $row = $overrides + [
            'place_id' => 'p' . bin2hex(random_bytes(4)), 'nome' => 'Pet Feliz', 'endereco' => 'Rua A', 'telefone' => '(16) 99123-4567',
            'site' => '', 'nota' => 4.5, 'avaliacoes' => 50, 'nicho' => 'pet', 'cidade' => 'Leme, SP', 'perfil' => 'sem_site',
            'motivos' => 'sem site no Google', 'score' => 5, 'status' => 'novo', 'dono' => null, 'criado' => '2026-10-01 09:00:00',
        ];
        $columns = array_keys($row);
        $this->db->pdo()->prepare('INSERT INTO leads (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')')
            ->execute(array_values($row));
        return (int)$this->db->pdo()->lastInsertId();
    }

    protected function scalar(string $sql): mixed
    {
        return $this->db->pdo()->query($sql)->fetchColumn();
    }
}
