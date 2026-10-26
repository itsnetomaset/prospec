<?php

declare(strict_types=1);

namespace Prospec\Search;

/**
 * Summary of one search shown to the user, e.g.
 * "Página 1: 11 encontradas, 8 novas no funil. Descartadas: 1 sem telefone, 1 site ok."
 */
final class SearchReport
{
    private int $added = 0;
    /** @var array<string, int> */
    private array $discarded = [];

    public function __construct(
        public readonly int $page,
        public readonly int $found,
    ) {
    }

    public function discard(string $reason): void
    {
        $this->discarded[$reason] = ($this->discarded[$reason] ?? 0) + 1;
    }

    public function added(): void
    {
        $this->added++;
    }

    public function addedCount(): int
    {
        return $this->added;
    }

    /** @return array<string, int> */
    public function discarded(): array
    {
        return $this->discarded;
    }

    public function message(): string
    {
        $parts = [];
        foreach ($this->discarded as $reason => $count) {
            $parts[] = "$count $reason";
        }
        return "Página {$this->page}: {$this->found} encontradas, {$this->added} novas no funil"
            . ($parts ? '. Descartadas: ' . implode(', ', $parts) : '') . '.';
    }
}
