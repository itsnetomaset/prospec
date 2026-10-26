<?php

declare(strict_types=1);

namespace Prospec\Lead;

use PDO;
use PDOException;
use Prospec\Database\Database;
use Prospec\Support\Clock;

final class LeadRepository
{
    private const ORDER = ' ORDER BY l.score DESC, l.avaliacoes DESC, l.id DESC';

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function find(int $id): ?Lead
    {
        if ($id <= 0) {
            return null;
        }
        $q = $this->db->pdo()->prepare('SELECT * FROM leads WHERE id=?');
        $q->execute([$id]);
        $row = $q->fetch();
        return $row ? Lead::fromRow($row) : null;
    }

    /**
     * Atomically assigns an unowned lead, so two salespeople never get the same business.
     */
    public function claim(int $id, string $user): bool
    {
        $q = $this->db->pdo()->prepare('UPDATE leads SET dono=? WHERE id=? AND dono IS NULL');
        $q->execute([$user, $id]);
        return $q->rowCount() > 0;
    }

    public function release(int $id): void
    {
        $this->db->pdo()->prepare('UPDATE leads SET dono=NULL WHERE id=?')->execute([$id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->pdo()->prepare('UPDATE leads SET status=? WHERE id=?')->execute([$status, $id]);
    }

    /** Moves a new lead to "contacted"; leads already further in the funnel are left alone. */
    public function markContacted(int $id): void
    {
        $this->db->pdo()->prepare('UPDATE leads SET status=? WHERE id=? AND status=?')
            ->execute([LeadStatus::CONTACTED, $id, LeadStatus::NEW]);
    }

    public function updatePhone(int $id, string $phone): void
    {
        $this->db->pdo()->prepare('UPDATE leads SET telefone=? WHERE id=?')->execute([$phone, $id]);
    }

    /**
     * @param list<string> $placeIds
     * @return array<string, true> the ids that are already stored
     */
    public function knownPlaceIds(array $placeIds): array
    {
        if (!$placeIds) {
            return [];
        }
        $q = $this->db->pdo()->prepare('SELECT place_id FROM leads WHERE place_id IN (' . implode(',', array_fill(0, count($placeIds), '?')) . ')');
        $q->execute($placeIds);
        return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
    }

    /**
     * @param array{placeId: string, name: string, address: string, phone: string, website: string, rating: float,
     *              reviewCount: int, niche: string, city: string, profile: string, reasons: list<string>, score: int} $lead
     * @return bool false when another request stored the same place first
     */
    public function insert(array $lead): bool
    {
        try {
            $this->db->pdo()->prepare('INSERT INTO leads (place_id,nome,endereco,telefone,site,nota,avaliacoes,nicho,cidade,perfil,motivos,score,criado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $lead['placeId'], mb_substr($lead['name'], 0, 200), mb_substr($lead['address'], 0, 300),
                    mb_substr($lead['phone'], 0, 40), mb_substr($lead['website'], 0, 300),
                    $lead['rating'], $lead['reviewCount'], $lead['niche'], $lead['city'], $lead['profile'],
                    mb_substr(implode('; ', $lead['reasons']), 0, 300), $lead['score'], $this->now(),
                ]);
            return true;
        } catch (PDOException $e) {
            if (!Database::isDuplicateKey($e)) {
                throw $e;
            }
            return false;
        }
    }

    public function count(LeadFilter $filter, string $currentUser): int
    {
        [$where, $args] = $filter->toSql($currentUser);
        $q = $this->db->pdo()->prepare('SELECT COUNT(*) FROM leads l' . $where);
        $q->execute($args);
        return (int)$q->fetchColumn();
    }

    /**
     * Lead list with the last contact, last note and decision maker of each lead.
     *
     * @return list<Lead>
     */
    public function page(LeadFilter $filter, string $currentUser, int $limit, int $offset): array
    {
        [$where, $args] = $filter->toSql($currentUser);
        $q = $this->db->pdo()->prepare('SELECT l.*,
            (SELECT MAX(g.criado) FROM lead_log g WHERE g.lead_id = l.id AND g.acao = \'contatou\') AS ultimo_contato,
            (SELECT g2.detalhe FROM lead_log g2 WHERE g2.lead_id = l.id AND g2.acao = \'nota\' ORDER BY g2.id DESC LIMIT 1) AS ultima_nota,
            r.nome AS resp_nome, r.telefone AS resp_tel
            FROM leads l LEFT JOIN leads_resp r ON r.lead_id = l.id' . $where . self::ORDER . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
        $q->execute($args);
        return array_map([Lead::class, 'fromRow'], $q->fetchAll());
    }

    /**
     * Raw rows for the CSV export.
     *
     * @return list<array<string, mixed>>
     */
    public function export(LeadFilter $filter, string $currentUser, int $limit = 5000): array
    {
        [$where, $args] = $filter->toSql($currentUser);
        $q = $this->db->pdo()->prepare('SELECT l.* FROM leads l' . $where . self::ORDER . ' LIMIT ' . $limit);
        $q->execute($args);
        return $q->fetchAll();
    }

    /**
     * Unowned new leads matching the batch filters, best first.
     *
     * @return list<Lead>
     */
    public function batchCandidates(string $city, string $niche, string $profile, int $limit = 400): array
    {
        $where = ['l.dono IS NULL', 'l.status=?'];
        $args = [LeadStatus::NEW];
        foreach (['l.cidade' => $city, 'l.nicho' => $niche, 'l.perfil' => $profile] as $column => $value) {
            if ($value !== '') {
                $where[] = "$column=?";
                $args[] = $value;
            }
        }
        $q = $this->db->pdo()->prepare('SELECT l.* FROM leads l WHERE ' . implode(' AND ', $where) . self::ORDER . ' LIMIT ' . $limit);
        $q->execute($args);
        return array_map([Lead::class, 'fromRow'], $q->fetchAll());
    }

    /**
     * The salesperson's outreach queue: their leads not contacted yet, best first.
     *
     * @return list<Lead>
     */
    public function queueOf(string $user, int $limit = 300): array
    {
        $q = $this->db->pdo()->prepare('SELECT l.* FROM leads l WHERE l.dono=? AND l.status=?' . self::ORDER . ' LIMIT ' . $limit);
        $q->execute([$user, LeadStatus::NEW]);
        return array_map([Lead::class, 'fromRow'], $q->fetchAll());
    }

    /** @return list<string> */
    public function cities(): array
    {
        return $this->db->pdo()->query("SELECT DISTINCT cidade FROM leads WHERE cidade<>'' ORDER BY cidade")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function niches(): array
    {
        return $this->db->pdo()->query("SELECT DISTINCT nicho FROM leads WHERE nicho<>'' ORDER BY nicho")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s', $this->clock->now());
    }
}
