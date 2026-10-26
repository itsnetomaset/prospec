<?php

declare(strict_types=1);

namespace Prospec\Search;

interface SearchProvider
{
    /**
     * Searches Google Maps for businesses of a niche in a city.
     *
     * @return list<array<string, mixed>> places
     * @throws SearchFailed when the provider returns no usable answer
     */
    public function places(string $niche, string $city, int $page): array;
}
