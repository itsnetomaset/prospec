<?php

declare(strict_types=1);

namespace Prospec\Auth;

/**
 * Team members defined in config.php.
 */
final class UserDirectory
{
    /**
     * @param array<string, array{nome?: string, admin?: bool, hash?: string}> $users
     */
    public function __construct(private readonly array $users)
    {
    }

    public function exists(string $user): bool
    {
        return isset($this->users[$user]);
    }

    /** How the user signs the outreach messages. */
    public function displayName(string $user): string
    {
        return $this->users[$user]['nome'] ?? ucfirst($user);
    }

    public function isAdmin(string $user): bool
    {
        return !empty($this->users[$user]['admin']);
    }

    public function initialHash(string $user): string
    {
        return (string)($this->users[$user]['hash'] ?? '');
    }
}
