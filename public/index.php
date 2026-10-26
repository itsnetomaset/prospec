<?php

declare(strict_types=1);

use Prospec\App;
use Prospec\Http\Request;
use Prospec\Http\Session;

$root = dirname(__DIR__);
require is_file($root . '/vendor/autoload.php') ? $root . '/vendor/autoload.php' : $root . '/src/autoload.php';

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e): void {
    error_log('prospec: ' . $e->getMessage());
    http_response_code(500);
    $missingTable = stripos($e->getMessage(), "doesn't exist") !== false || stripos($e->getMessage(), 'no such table') !== false;
    echo '<!doctype html><meta charset="utf-8"><title>Erro</title><p>Erro interno.'
        . ($missingTable ? ' Importe o arquivo database/schema.sql no phpMyAdmin.' : '') . '</p>';
});

$configFile = $root . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
$placeholder = fn (mixed $value): bool => str_starts_with((string)$value, 'COLE_AQUI');
if (!$config || $placeholder($config['db']['pass'] ?? '') || $placeholder($config['serper_key'] ?? '')) {
    http_response_code(500);
    exit('<!doctype html><meta charset="utf-8"><p>Copie config.example.php para config.php e preencha a senha do banco e a chave do Serper.</p>');
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
    . "script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; connect-src 'self'");

$request = Request::fromGlobals();
$session = Session::start($request->isHttps());
(new App($config, $root))->handle($request, $session)->send();
