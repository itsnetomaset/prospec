<?php

declare(strict_types=1);

namespace Prospec\Tests\Support;

use Prospec\Search\SearchFailed;
use Prospec\Search\SearchProvider;

final class FakeSearchProvider implements SearchProvider
{
    /** @var list<array{string, string, int}> */
    public array $calls = [];

    /**
     * @param array<int, list<array<string, mixed>>> $pages places by page number
     */
    public function __construct(private readonly array $pages = [], private readonly ?string $failure = null)
    {
    }

    public function places(string $niche, string $city, int $page): array
    {
        $this->calls[] = [$niche, $city, $page];
        if ($this->failure !== null) {
            throw new SearchFailed($this->failure);
        }
        return $this->pages[$page] ?? [];
    }
}
