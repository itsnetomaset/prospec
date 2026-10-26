<?php

declare(strict_types=1);

namespace Prospec\Controller;

use Prospec\Auth\AuthService;
use Prospec\Auth\UserDirectory;
use Prospec\Http\Request;
use Prospec\Http\Response;
use Prospec\Http\Session;
use Prospec\View\View;

final class AuthController
{
    private const MAX_USERNAME_LENGTH = 40;

    public function __construct(
        private readonly Session $session,
        private readonly AuthService $auth,
        private readonly UserDirectory $users,
        private readonly View $view,
    ) {
    }

    public function loginForm(string $error = ''): Response
    {
        return Response::html($this->view->page('auth/login', [
            'title' => 'Entrar | Prospec',
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
        ], 'auth-layout'));
    }

    public function login(Request $request): Response
    {
        if (!$this->session->isValidCsrf($request->post('t'))) {
            return $this->loginForm('Sessão expirada. Tente de novo.');
        }
        $user = substr(strtolower(trim($request->post('u'))), 0, self::MAX_USERNAME_LENGTH);
        $result = $this->auth->attempt($user, $request->post('p'), $request->ip());
        if (!$result->ok) {
            return $this->loginForm($result->error);
        }
        $this->session->logIn($user, $result->mustChangePassword); // first login: the user must pick a password
        return Response::redirect('./');
    }

    /**
     * Password change screen, mandatory on first login and available from the menu.
     */
    public function changePassword(Request $request): Response
    {
        $user = (string)$this->session->user();
        $error = '';
        if ($request->isPost()) {
            if (!$this->session->isValidCsrf($request->post('t'))) {
                return Response::forbidden('Sessão expirada. Recarregue a página.');
            }
            if ($request->post('a') === 'sair') {
                return $this->logout($request);
            }
            if ($request->post('a') === 'senha') {
                $error = $this->auth->changePassword($user, $request->post('n1'), $request->post('n2')) ?? '';
                if ($error === '') {
                    $this->session->requirePasswordChange(false);
                    $this->session->flash('info', 'Senha alterada.');
                    return Response::redirect('./');
                }
            }
        }
        return Response::html($this->view->page('auth/password', [
            'title' => 'Nova senha | Prospec',
            'error' => $error,
            'csrf' => $this->session->csrfToken(),
            'userName' => $this->users->displayName($user),
        ], 'auth-layout'));
    }

    public function requestPasswordChange(): Response
    {
        $this->session->requirePasswordChange(true);
        return Response::redirect('./');
    }

    public function logout(Request $request): Response
    {
        $this->session->destroy($request->isHttps());
        return Response::redirect('./');
    }
}
