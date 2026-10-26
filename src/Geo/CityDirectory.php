<?php

declare(strict_types=1);

namespace Prospec\Geo;

use Closure;

/**
 * Autocomplete over the official list of Brazilian municipalities (IBGE).
 * The list is downloaded once and cached in a JSON file; if IBGE is unreachable,
 * the cities already searched are used instead.
 */
final class CityDirectory
{
    private const IBGE_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';
    private const RETRY_AFTER_SECONDS = 900;
    private const MAX_SUGGESTIONS = 8;

    /** @var list<array{0: string, 1: string, 2: string}>|null name, state, normalized name */
    private ?array $cities = null;

    /**
     * @param Closure(): list<string> $fallback cities already searched
     */
    public function __construct(
        private readonly string $cacheFile,
        private readonly Closure $fallback,
    ) {
    }

    public static function normalize(string $text): string
    {
        return strtr(mb_strtolower($text, 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);
    }

    /**
     * Cities whose name starts with the typed text come first, then those that contain it.
     *
     * @return list<string> "Ribeirão Preto, SP"
     */
    public function suggest(string $typed): array
    {
        $term = self::normalize(trim($typed));
        $term = preg_replace('/\s*[,\-]\s*[a-z]{2}$/', '', $term) ?? $term; // ignore a trailing ", SP"
        if (mb_strlen($term) < 2) {
            return [];
        }
        $prefix = [];
        $contains = [];
        foreach ($this->all() as $city) {
            if (str_starts_with($city[2], $term)) {
                $prefix[] = $city;
            } elseif (str_contains($city[2], $term)) {
                $contains[] = $city;
            }
            if (count($prefix) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }
        $out = [];
        foreach (array_slice(array_merge($prefix, $contains), 0, self::MAX_SUGGESTIONS) as $city) {
            $out[] = $city[1] !== '' ? $city[0] . ', ' . $city[1] : $city[0];
        }
        return $out;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function all(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }
        if (is_file($this->cacheFile) && filesize($this->cacheFile) > 1000) {
            $cached = json_decode((string)file_get_contents($this->cacheFile), true);
            if (is_array($cached) && $cached) {
                return $this->cities = $cached;
            }
        }
        $downloaded = $this->download();
        if (count($downloaded) > 5000) {
            @file_put_contents($this->cacheFile, json_encode($downloaded, JSON_UNESCAPED_UNICODE), LOCK_EX);
            return $this->cities = $downloaded;
        }
        $cities = [];
        foreach (($this->fallback)() as $name) {
            $cities[] = [$name, '', self::normalize($name)];
        }
        return $this->cities = $cities;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function download(): array
    {
        // After a failure, wait before trying again so typing does not hang the server on every keystroke.
        $failureMarker = $this->cacheFile . '.failed';
        if (is_file($failureMarker) && filemtime($failureMarker) >= time() - self::RETRY_AFTER_SECONDS) {
            return [];
        }
        $ch = curl_init(self::IBGE_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = ($raw !== false && $http === 200) ? json_decode((string)$raw, true) : null;
        if (!is_array($data)) {
            @file_put_contents($failureMarker, (string)time());
            return [];
        }
        $cities = [];
        foreach ($data as $m) {
            $name = is_string($m['nome'] ?? null) ? $m['nome'] : '';
            $state = $m['microrregiao']['mesorregiao']['UF']['sigla'] ?? ($m['regiao-imediata']['regiao-intermediaria']['UF']['sigla'] ?? '');
            if ($name !== '' && is_string($state) && $state !== '') {
                $cities[] = [$name, $state, self::normalize($name)];
            }
        }
        return $cities;
    }
}
