<?php

declare(strict_types=1);

namespace Prospec\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    public static function redirect(string $location): self
    {
        return new self(302, '', ['Location' => $location]);
    }

    /** @param list<mixed>|array<string, mixed> $data */
    public static function json(array $data): self
    {
        return new self(200, (string)json_encode($data, JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public static function forbidden(string $message): self
    {
        return new self(403, $message);
    }

    public static function csv(string $body, string $filename): self
    {
        return new self(200, $body, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
