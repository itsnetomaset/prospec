<?php

declare(strict_types=1);

namespace Prospec\Auth;

final class LoginResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $error = '',
        public readonly bool $mustChangePassword = false,
    ) {
    }

    public static function success(bool $mustChangePassword): self
    {
        return new self(true, '', $mustChangePassword);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
