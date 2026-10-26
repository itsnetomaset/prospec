<?php

declare(strict_types=1);

namespace Prospec\Lead;

/**
 * Filters of the lead list, read from the query string. Invalid values fall back to "all".
 */
final class LeadFilter
{
    public const ALL = 'todos';
    public const MINE = 'meus';
    public const UNOWNED = 'livres';

    public function __construct(
        public readonly string $status = self::ALL,
        public readonly string $ownership = self::ALL,
        public readonly string $profile = '',
        public readonly string $city = '',
        public readonly string $niche = '',
        public readonly int $page = 1,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        $get = fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';
        $status = $get('status') ?: self::ALL;
        if ($status !== self::ALL && !LeadStatus::isValid($status)) {
            $status = self::ALL;
        }
        $ownership = in_array($get('resp'), [self::MINE, self::UNOWNED], true) ? $get('resp') : self::ALL;
        $profile = LeadProfile::isValid($get('perfil')) ? $get('perfil') : '';
        return new self($status, $ownership, $profile, $get('cidade'), $get('nicho'), max(1, (int)$get('pag')));
    }

    /**
     * SQL conditions over the `leads l` alias.
     *
     * @return array{0: string, 1: list<string>} WHERE clause (or empty string) and its parameters
     */
    public function toSql(string $currentUser): array
    {
        $where = [];
        $args = [];
        if ($this->status !== self::ALL) {
            $where[] = 'l.status=?';
            $args[] = $this->status;
        }
        if ($this->city !== '') {
            $where[] = 'l.cidade=?';
            $args[] = $this->city;
        }
        if ($this->niche !== '') {
            $where[] = 'l.nicho=?';
            $args[] = $this->niche;
        }
        if ($this->profile !== '') {
            $where[] = 'l.perfil=?';
            $args[] = $this->profile;
        }
        if ($this->ownership === self::MINE) {
            $where[] = 'l.dono=?';
            $args[] = $currentUser;
        } elseif ($this->ownership === self::UNOWNED) {
            $where[] = 'l.dono IS NULL';
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $args];
    }
}
