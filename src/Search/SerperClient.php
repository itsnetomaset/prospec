<?php

declare(strict_types=1);

namespace Prospec\Search;

/**
 * Google Maps search through the Serper API (https://serper.dev). Each call costs one credit.
 */
final class SerperClient implements SearchProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://google.serper.dev/maps',
    ) {
    }

    public function places(string $niche, string $city, int $page): array
    {
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_POST => 1, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . $this->apiKey],
            CURLOPT_POSTFIELDS => json_encode(['q' => "$niche em $city", 'gl' => 'br', 'hl' => 'pt-br', 'page' => $page])]);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            throw new SearchFailed('sem resposta do Serper' . ($error !== '' ? " ($error)" : ''));
        }
        if (!isset($data['places'])) {
            throw new SearchFailed((string)($data['message'] ?? 'a busca não retornou resultados') . ($http >= 400 ? " (HTTP $http)" : ''));
        }
        return array_values(array_filter((array)$data['places'], 'is_array'));
    }
}
