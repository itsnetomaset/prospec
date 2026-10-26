<?php

declare(strict_types=1);

namespace Prospec\Auth;

final class AuthService
{
    /** Hash checked when the user does not exist, so response time does not reveal valid usernames. */
    private const DUMMY_HASH = '$2y$10$2gS0BGcgl.laP8TOjWcZM.cI0BmbmjTbBfqjpZoFTpZngTVMHu1aC';
    public const MIN_PASSWORD_LENGTH = 8;
    private const MAX_INPUT_LENGTH = 200;

    public function __construct(
        private readonly UserDirectory $users,
        private readonly PasswordStore $passwords,
        private readonly LoginThrottle $throttle,
    ) {
    }

    public function attempt(string $user, string $password, string $ip): LoginResult
    {
        if ($this->throttle->isLocked($ip, $user)) {
            return LoginResult::failed('Muitas tentativas. Aguarde 15 minutos.');
        }
        $exists = $this->users->exists($user);
        $stored = $exists ? $this->passwords->hashOf($user) : null;
        $hash = $exists ? ($stored ?? $this->users->initialHash($user)) : self::DUMMY_HASH;
        $valid = password_verify(substr($password, 0, self::MAX_INPUT_LENGTH), $hash);
        if ($exists && $valid) {
            $this->throttle->clear($ip, $user);
            return LoginResult::success(mustChangePassword: $stored === null);
        }
        $this->throttle->recordFailure($ip, $user);
        return LoginResult::failed('Usuário ou senha incorretos.');
    }

    /**
     * @return string|null error message, or null when the password was changed
     */
    public function changePassword(string $user, string $new, string $confirmation): ?string
    {
        $new = substr($new, 0, self::MAX_INPUT_LENGTH);
        $confirmation = substr($confirmation, 0, self::MAX_INPUT_LENGTH);
        if (strlen($new) < self::MIN_PASSWORD_LENGTH) {
            return 'Use pelo menos 8 caracteres.';
        }
        if ($new !== $confirmation) {
            return 'As duas senhas não são iguais.';
        }
        $current = $this->passwords->hashOf($user) ?? $this->users->initialHash($user);
        if (password_verify($new, $current) || password_verify($new, $this->users->initialHash($user))) {
            return 'Escolha uma senha diferente da atual.';
        }
        $this->passwords->save($user, password_hash($new, PASSWORD_DEFAULT));
        return null;
    }
}
