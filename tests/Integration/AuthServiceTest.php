<?php

declare(strict_types=1);

namespace Prospec\Tests\Integration;

use Prospec\Auth\AuthService;
use Prospec\Auth\LoginThrottle;
use Prospec\Auth\PasswordStore;
use Prospec\Auth\UserDirectory;

final class AuthServiceTest extends DatabaseTestCase
{
    private AuthService $auth;
    private LoginThrottle $throttle;

    protected function setUp(): void
    {
        parent::setUp();
        $users = new UserDirectory([
            'giu' => ['nome' => 'Giuseppe', 'admin' => true, 'hash' => password_hash('initial-pass', PASSWORD_DEFAULT, ['cost' => 4])],
        ]);
        $this->throttle = new LoginThrottle($this->db, $this->clock);
        $this->auth = new AuthService($users, new PasswordStore($this->db, $this->clock), $this->throttle);
    }

    public function testFirstLoginRequiresAPasswordChange(): void
    {
        $result = $this->auth->attempt('giu', 'initial-pass', '1.1.1.1');

        self::assertTrue($result->ok);
        self::assertTrue($result->mustChangePassword);
    }

    public function testAfterChangingOnlyTheNewPasswordWorks(): void
    {
        self::assertNull($this->auth->changePassword('giu', 'senha-nova-1', 'senha-nova-1'));

        self::assertFalse($this->auth->attempt('giu', 'initial-pass', '1.1.1.1')->ok);
        $result = $this->auth->attempt('giu', 'senha-nova-1', '1.1.1.1');
        self::assertTrue($result->ok);
        self::assertFalse($result->mustChangePassword);
    }

    public function testPasswordRules(): void
    {
        self::assertSame('Use pelo menos 8 caracteres.', $this->auth->changePassword('giu', 'curta', 'curta'));
        self::assertSame('As duas senhas não são iguais.', $this->auth->changePassword('giu', 'senha-nova-1', 'senha-nova-2'));
        self::assertSame('Escolha uma senha diferente da atual.', $this->auth->changePassword('giu', 'initial-pass', 'initial-pass'));
    }

    public function testWrongPasswordAndUnknownUserGetTheSameMessage(): void
    {
        $wrong = $this->auth->attempt('giu', 'errada', '1.1.1.1');
        $unknown = $this->auth->attempt('ninguem', 'errada', '1.1.1.1');

        self::assertFalse($wrong->ok);
        self::assertSame($wrong->error, $unknown->error);
    }

    public function testLocksAfterRepeatedFailuresEvenWithTheRightPassword(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES; $i++) {
            $this->auth->attempt('giu', 'errada', '1.1.1.1');
        }

        $result = $this->auth->attempt('giu', 'initial-pass', '2.2.2.2'); // same user, other IP

        self::assertFalse($result->ok);
        self::assertSame('Muitas tentativas. Aguarde 15 minutos.', $result->error);
    }

    public function testLockExpiresAfterTheWindow(): void
    {
        for ($i = 0; $i < LoginThrottle::MAX_FAILURES; $i++) {
            $this->throttle->recordFailure('1.1.1.1', 'giu');
        }
        $this->clock->advance(LoginThrottle::WINDOW_SECONDS + 1);

        self::assertTrue($this->auth->attempt('giu', 'initial-pass', '1.1.1.1')->ok);
    }

    public function testSuccessfulLoginClearsFailures(): void
    {
        $this->auth->attempt('giu', 'errada', '1.1.1.1');
        $this->auth->attempt('giu', 'initial-pass', '1.1.1.1');

        self::assertSame(0, (int)$this->scalar('SELECT COUNT(*) FROM login_tentativas'));
    }
}
