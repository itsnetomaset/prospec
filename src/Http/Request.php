<?php

declare(strict_types=1);

namespace Prospec\Http;

/**
 * Read-only view of the current HTTP request. Every accessor returns a string, so array
 * injection (?id[]=1) never reaches the application code.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function query(string $key): string
    {
        return is_string($this->query[$key] ?? null) ? $this->query[$key] : '';
    }

    public function hasQuery(string $key): bool
    {
        return isset($this->query[$key]);
    }

    /** @return array<string, mixed> */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function post(string $key): string
    {
        return is_string($this->post[$key] ?? null) ? $this->post[$key] : '';
    }

    public function hasPost(string $key): bool
    {
        return isset($this->post[$key]);
    }

    public function isPost(): bool
    {
        return ($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function ip(): string
    {
        return substr((string)($this->server['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /** Current path and query string, used to come back to the same screen after an action. */
    public function currentUrl(): string
    {
        $path = '/' . ltrim((string)(parse_url((string)($this->server['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), '/');
        $query = (string)($this->server['QUERY_STRING'] ?? '');
        return $path . ($query !== '' ? '?' . $query : '');
    }

    /** Directory of the front controller, used to build asset URLs ("/" or "/prospec/"). */
    public function basePath(): string
    {
        return rtrim(str_replace('\\', '/', dirname((string)($this->server['SCRIPT_NAME'] ?? '/'))), '/') . '/';
    }
}
