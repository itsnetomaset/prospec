<?php

declare(strict_types=1);

namespace Prospec\Lead;

/**
 * A prospected business. Built from a `leads` row (column names are kept in Portuguese
 * for compatibility with the production database).
 */
final class Lead
{
    /**
     * @param list<string> $reasons why the lead qualified (e.g. "sem HTTPS")
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly string $profile,
        public readonly array $reasons = [],
        public readonly string $placeId = '',
        public readonly string $address = '',
        public readonly string $website = '',
        public readonly string $rating = '0',
        public readonly int $reviewCount = 0,
        public readonly string $niche = '',
        public readonly string $city = '',
        public readonly int $score = 0,
        public readonly string $status = LeadStatus::NEW,
        public readonly ?string $owner = null,
        public readonly string $createdAt = '',
        public readonly ?string $lastContactAt = null,
        public readonly ?string $lastNote = null,
        public readonly ?Contact $contact = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $contactPhone = (string)($row['resp_tel'] ?? '');
        $reasons = (string)($row['motivos'] ?? '');

        return new self(
            id: (int)$row['id'],
            name: (string)($row['nome'] ?? ''),
            phone: (string)($row['telefone'] ?? ''),
            profile: (string)($row['perfil'] ?? ''),
            reasons: explode('; ', $reasons),
            placeId: (string)($row['place_id'] ?? ''),
            address: (string)($row['endereco'] ?? ''),
            website: (string)($row['site'] ?? ''),
            rating: (string)($row['nota'] ?? '0'),
            reviewCount: (int)($row['avaliacoes'] ?? 0),
            niche: (string)($row['nicho'] ?? ''),
            city: (string)($row['cidade'] ?? ''),
            score: (int)($row['score'] ?? 0),
            status: (string)($row['status'] ?? LeadStatus::NEW),
            owner: isset($row['dono']) ? (string)$row['dono'] : null,
            createdAt: (string)($row['criado'] ?? ''),
            lastContactAt: isset($row['ultimo_contato']) ? (string)$row['ultimo_contato'] : null,
            lastNote: isset($row['ultima_nota']) ? (string)$row['ultima_nota'] : null,
            contact: $contactPhone !== '' ? new Contact((string)($row['resp_nome'] ?? ''), $contactPhone) : null,
        );
    }

    public function isOwnedBy(string $user): bool
    {
        return $this->owner === $user;
    }
}
