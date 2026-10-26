<?php

declare(strict_types=1);

namespace Prospec\Search;

use PDO;
use PDOException;
use Prospec\Database\Database;
use Prospec\Support\Clock;

/**
 * Last result page fetched for each niche and city (table `buscas`), so repeating a search
 * moves on to new businesses instead of paying again for the same ones.
 */
final class SearchHistory
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function lastPage(string $niche, string $city): int
    {
        $q = $this->db->pdo()->prepare('SELECT pagina FROM buscas WHERE nicho=? AND cidade=?');
        $q->execute([$niche, $city]);
        $page = (int)$q->fetchColumn();
        $q->closeCursor();
        return $page;
    }

    public function savePage(string $niche, string $city, int $page): void
    {
        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s', $this->clock->now());
        $update = $pdo->prepare('UPDATE buscas SET pagina=?, atualizado=? WHERE nicho=? AND cidade=?');
        $update->execute([$page, $now, $niche, $city]);
        if ($update->rowCount() > 0) {
            return;
        }
        try {
            $pdo->prepare('INSERT INTO buscas (nicho, cidade, pagina, atualizado) VALUES (?,?,?,?)')->execute([$niche, $city, $page, $now]);
        } catch (PDOException) {
            $update->execute([$page, $now, $niche, $city]);
        }
    }

    /** @return list<string> */
    public function searchedCities(int $limit = 500): array
    {
        return $this->db->pdo()->query('SELECT DISTINCT cidade FROM buscas ORDER BY cidade LIMIT ' . $limit)->fetchAll(PDO::FETCH_COLUMN);
    }
}
