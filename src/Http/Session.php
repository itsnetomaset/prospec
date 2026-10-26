<?php

declare(strict_types=1);

namespace Prospec\Http;

/**
 * Typed access to $_SESSION.
 */
final class Session
{
    private const IDLE_TIMEOUT = 43200; // 12 hours

    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    private function __construct(array &$data)
    {
        $this->data = &$data;
    }

    public static function start(bool $https): self
    {
        session_name('prospec');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        $session = new self($_SESSION);
        $session->expireIfIdle();
        return $session;
    }

    /** @param array<string, mixed> $data */
    public static function inMemory(array &$data): self
    {
        $session = new self($data);
        $session->expireIfIdle();
        return $session;
    }

    private function expireIfIdle(): void
    {
        if (isset($this->data['ts']) && time() - (int)$this->data['ts'] > self::IDLE_TIMEOUT) {
            $this->data = [];
        }
        $this->data['ts'] = time();
        $this->data['t'] ??= bin2hex(random_bytes(16));
        $this->data['n'] ??= bin2hex(random_bytes(8));
    }

    public function csrfToken(): string
    {
        return (string)$this->data['t'];
    }

    public function isValidCsrf(string $token): bool
    {
        return hash_equals($this->csrfToken(), $token);
    }

    /**
     * Single-use token of the search forms: a double submit (refresh, double click) must not
     * spend another paid search.
     */
    public function searchNonce(): string
    {
        return (string)$this->data['n'];
    }

    public function consumeSearchNonce(string $nonce): bool
    {
        if (!hash_equals($this->searchNonce(), $nonce)) {
            return false;
        }
        $this->data['n'] = bin2hex(random_bytes(8));
        return true;
    }

    public function user(): ?string
    {
        return isset($this->data['user']) ? (string)$this->data['user'] : null;
    }

    public function logIn(string $user, bool $mustChangePassword): void
    {
        session_status() === PHP_SESSION_ACTIVE && session_regenerate_id(true);
        $this->data['user'] = $user;
        $this->data['trocar'] = $mustChangePassword;
        $this->data['t'] = bin2hex(random_bytes(16));
        $this->data['n'] = bin2hex(random_bytes(8));
    }

    public function forgetUser(): void
    {
        unset($this->data['user']);
    }

    public function destroy(bool $https): void
    {
        $this->data = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
    }

    public function mustChangePassword(): bool
    {
        return !empty($this->data['trocar']);
    }

    public function requirePasswordChange(bool $required): void
    {
        if (!$required) {
            session_status() === PHP_SESSION_ACTIVE && session_regenerate_id(true);
        }
        $this->data['trocar'] = $required;
    }

    public function flash(string $type, string $message): void
    {
        $this->data['flash'] = [$type, $message];
    }

    /** @return array{0: string, 1: string}|null */
    public function pullFlash(): ?array
    {
        $flash = $this->data['flash'] ?? null;
        unset($this->data['flash']);
        return $flash;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
