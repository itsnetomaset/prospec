<?php

declare(strict_types=1);

namespace Prospec\Tests\Integration;

use Prospec\App;
use Prospec\Http\Request;
use Prospec\Http\Response;
use Prospec\Http\Session;

/**
 * End-to-end tests through the front controller (routing, CSRF, permissions, templates).
 */
final class AppTest extends DatabaseTestCase
{
    private App $app;
    /** @var array<string, array<string, mixed>> session data per user */
    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $hash = password_hash('initial-pass', PASSWORD_DEFAULT, ['cost' => 4]);
        $this->app = new App([
            'agencia' => 'NEMAX Digital',
            'serper_key' => 'test',
            'limite_buscas_dia' => 30,
            'users' => [
                'giu' => ['nome' => 'Giuseppe', 'admin' => true, 'hash' => $hash],
                'ana' => ['nome' => 'Ana', 'admin' => false, 'hash' => $hash],
            ],
        ], dirname(__DIR__, 2), $this->db, $this->clock);
    }

    public function testLoginPasswordChangeAndLeadList(): void
    {
        self::assertStringContainsString('Entrar no <em>painel</em>', $this->get('giu', '/')->body);

        $wrong = $this->post('giu', ['login' => '1', 'u' => 'giu', 'p' => 'errada']);
        self::assertStringContainsString('Usuário ou senha incorretos.', $wrong->body);

        $login = $this->post('giu', ['login' => '1', 'u' => ' GIU ', 'p' => 'initial-pass']);
        self::assertSame(302, $login->status);
        self::assertStringContainsString('Crie sua <em>senha</em>', $this->get('giu', '/')->body);

        $changed = $this->post('giu', ['a' => 'senha', 'n1' => 'senha-nova-1', 'n2' => 'senha-nova-1']);
        self::assertSame(302, $changed->status);

        $page = $this->get('giu', '/');
        self::assertStringContainsString('Senha alterada.', $page->body);
        self::assertStringContainsString('Nenhum lead com esses filtros.', $page->body);
        self::assertStringContainsString('>CSV</a>', $page->body);
    }

    public function testActionsRequireTheCsrfToken(): void
    {
        $this->logIn('giu');
        $id = $this->insertLead();

        $response = $this->post('giu', ['a' => 'pegar', 'id' => (string)$id], token: 'forged');

        self::assertSame(403, $response->status);
        self::assertNull($this->scalar('SELECT dono FROM leads'));
    }

    public function testTakingALeadShowsItsWhatsAppMessage(): void
    {
        $this->logIn('giu');
        $id = $this->insertLead(['nome' => 'Pet & Cia']);

        $response = $this->post('giu', ['a' => 'pegar', 'id' => (string)$id], uri: '/?status=novo');

        self::assertSame(302, $response->status);
        self::assertSame('/?status=novo', $response->headers['Location']);
        $page = $this->get('giu', '/')->body;
        self::assertStringContainsString('Pet &amp; Cia', $page);
        self::assertStringContainsString('https://wa.me/5516991234567?text=', $page);
        self::assertSame('pegou', $this->scalar('SELECT acao FROM lead_log'));
    }

    public function testUsersCannotChangeLeadsOfOthers(): void
    {
        $this->logIn('ana');
        $id = $this->insertLead(['dono' => 'giu']);

        $this->post('ana', ['a' => 'status', 'id' => (string)$id, 'status' => 'perdido']);
        $this->post('ana', ['a' => 'pegar', 'id' => (string)$id]);

        self::assertSame('novo', $this->scalar('SELECT status FROM leads'));
        self::assertSame('giu', $this->scalar('SELECT dono FROM leads'));
        self::assertStringContainsString('Esse lead já foi pego por outra pessoa.', $this->get('ana', '/')->body);
    }

    public function testAdminCanReleaseAnotherUsersLead(): void
    {
        $this->logIn('giu');
        $id = $this->insertLead(['dono' => 'ana']);

        $this->post('giu', ['a' => 'devolver', 'id' => (string)$id]);

        self::assertNull($this->scalar('SELECT dono FROM leads'));
        self::assertSame('devolvido por admin (era de ana)', $this->scalar('SELECT detalhe FROM lead_log'));
    }

    public function testCsvExportIsForAdminsOnly(): void
    {
        $this->logIn('giu');
        $this->logIn('ana');
        $this->insertLead(['nome' => '=cmd']);

        $admin = $this->get('giu', '/?csv=1');
        $user = $this->get('ana', '/?csv=1');

        self::assertSame('text/csv; charset=utf-8', $admin->headers['Content-Type']);
        self::assertStringContainsString('"\'=cmd"', $admin->body);
        self::assertArrayNotHasKey('Content-Type', $user->headers);
        self::assertStringNotContainsString('>CSV</a>', $user->body);
    }

    public function testCityAutocomplete(): void
    {
        $this->logIn('giu');

        $response = $this->get('giu', '/?cid=pirassun');

        self::assertSame('["Pirassununga, SP"]', $response->body);
    }

    public function testQueueShowsTheNextLeadWithItsMessage(): void
    {
        $this->logIn('giu');
        $this->insertLead(['dono' => 'giu', 'nome' => 'Primeiro', 'score' => 9]);
        $this->insertLead(['dono' => 'giu', 'nome' => 'Sem Zap', 'telefone' => '0800 000 0000']);

        $page = $this->get('giu', '/?fila=1')->body;

        self::assertStringContainsString('<b>1</b> na fila, 1 sem WhatsApp válido', $page);
        self::assertStringContainsString('Primeiro', $page);
        self::assertStringContainsString('Enviar no WhatsApp', $page);
    }

    private function logIn(string $user): void
    {
        $this->post($user, ['login' => '1', 'u' => $user, 'p' => 'initial-pass']);
        $this->post($user, ['a' => 'senha', 'n1' => 'senha-de-' . $user, 'n2' => 'senha-de-' . $user]);
        $this->get($user, '/'); // consume the flash message
    }

    private function get(string $user, string $uri): Response
    {
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);
        return $this->dispatch($user, $query, [], 'GET', $uri);
    }

    /** @param array<string, string> $post */
    private function post(string $user, array $post, ?string $token = null, string $uri = '/'): Response
    {
        if (!isset($this->sessions[$user]['t'])) {
            $this->get($user, '/'); // opens the session, like loading the login page
        }
        $post['t'] = $token ?? (string)($this->sessions[$user]['t'] ?? '');
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $query);
        return $this->dispatch($user, $query, $post, 'POST', $uri);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    private function dispatch(string $user, array $query, array $post, string $method, string $uri): Response
    {
        $this->sessions[$user] ??= [];
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'QUERY_STRING' => (string)parse_url($uri, PHP_URL_QUERY),
            'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '127.0.0.1'];
        return $this->app->handle(new Request($query, $post, $server), Session::inMemory($this->sessions[$user]));
    }
}
