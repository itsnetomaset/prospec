<?php
date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
require __DIR__ . '/lib.php';

set_exception_handler(function (Throwable $e) {
    error_log('prospec: ' . $e->getMessage());
    http_response_code(500);
    $m = $e->getMessage();
    $dica = (stripos($m, "doesn't exist") !== false || stripos($m, 'no such table') !== false) ? ' Importe o arquivo schema.sql no phpMyAdmin.' : '';
    echo '<!doctype html><meta charset="utf-8"><title>Erro</title><p style="font-family:system-ui;padding:24px">Erro interno.' . h($dica) . '</p>';
});

$C = require __DIR__ . '/config.php';
if (str_starts_with((string)$C['db']['pass'], 'COLE_AQUI') || str_starts_with((string)$C['serper_key'], 'COLE_AQUI')) {
    http_response_code(500);
    exit('<!doctype html><meta charset="utf-8"><p style="font-family:system-ui;padding:24px">Preencha a senha do banco e a chave do Serper no config.php.</p>');
}
$USERS = $C['users'];

// ---------- sessão e cabeçalhos ----------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('prospec');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (isset($_SESSION['ts']) && time() - $_SESSION['ts'] > 43200) $_SESSION = []; // 12 h sem uso
$_SESSION['ts'] = time();
$_SESSION['t'] ??= bin2hex(random_bytes(16));
$_SESSION['n'] ??= bin2hex(random_bytes(8));

$nonce = base64_encode(random_bytes(12));
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'nonce-$nonce'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; connect-src 'self'");

// Visual: design system da NEMAX Digital (fontes do Google, tokens do style.css do site)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
$fontes = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;600;800&family=Manrope:wght@400..700&display=swap" rel="stylesheet">';
$css = <<<'CSS'
/* Prospec · design system NEMAX Digital (tokens copiados do style.css do site) */
:root{
  --bg:#070A12;--bg-2:#0B101C;--surface:#111827;--surface-2:#161F33;
  --line:rgba(148,170,255,.12);--line-2:rgba(148,170,255,.22);
  --text:#EEF2FF;--muted:#93A0BF;
  --blue:#4F7CFF;--cyan:#22D3EE;--cta:#FF7A1A;--cta-2:#FF9A3D;--ok:#2EE59D;--wa:#25D366;
  --err:#FF5F57;
  --r:20px;--r-lg:28px;
  --ease:cubic-bezier(.7,0,.2,1);--ease-out:cubic-bezier(.16,1,.3,1);
  --f-d:"Bricolage Grotesque",system-ui,sans-serif;--f-b:"Manrope",system-ui,sans-serif;
  color-scheme:dark;
}
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--text);font:400 16px/1.6 var(--f-b);-webkit-font-smoothing:antialiased;min-height:100vh;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:
  radial-gradient(640px 640px at -6% -12%,rgba(79,124,255,.30),transparent 70%),
  radial-gradient(520px 520px at 106% 6%,rgba(124,58,237,.16),transparent 70%),
  radial-gradient(480px 480px at 42% 118%,rgba(34,211,238,.12),transparent 70%)}
a{color:inherit;text-decoration:none}
h1,h2,h3{font-family:var(--f-d);line-height:1.05;margin:0;letter-spacing:-.02em}
p{margin:0}
em{font-style:normal;background:linear-gradient(90deg,var(--blue),var(--cyan));-webkit-background-clip:text;background-clip:text;color:transparent}
::selection{background:var(--blue);color:#fff}
:focus-visible{outline:2px solid var(--cyan);outline-offset:3px;border-radius:6px}
.wrap{width:100%;max-width:1240px;margin:0 auto;padding:0 24px}

/* botões */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:14px 22px;border-radius:999px;font:700 15px/1 var(--f-b);white-space:nowrap;border:0;cursor:pointer;color:var(--text);background:none;transition:transform .3s var(--ease-out),box-shadow .3s,background .3s,border-color .3s}
.btn:disabled{opacity:.6;cursor:wait}
.btn--cta{background:linear-gradient(135deg,var(--cta),var(--cta-2));color:#1A0C00;box-shadow:0 10px 30px -10px rgba(255,122,26,.7),inset 0 1px 0 rgba(255,255,255,.35)}
.btn--cta:hover{box-shadow:0 16px 44px -10px rgba(255,122,26,.9),inset 0 1px 0 rgba(255,255,255,.35)}
.btn--ghost{border:1px solid var(--line-2);background:rgba(255,255,255,.03)}
.btn--ghost:hover{border-color:var(--blue);background:rgba(79,124,255,.1)}
.btn--wa{background:var(--wa);color:#04150D;box-shadow:0 10px 30px -12px rgba(37,211,102,.7)}
.btn--wa:hover{box-shadow:0 14px 38px -10px rgba(37,211,102,.85)}
.btn--sm{padding:10px 16px;font-size:14px}
.btn--lg{padding:17px 26px;font-size:16px}

/* cabeçalho */
.hdr{position:sticky;top:0;z-index:50;background:rgba(7,10,18,.72);backdrop-filter:blur(14px) saturate(1.4);-webkit-backdrop-filter:blur(14px) saturate(1.4);border-bottom:1px solid var(--line)}
.hdr__in{display:flex;align-items:center;gap:8px 24px;flex-wrap:wrap;min-height:68px;padding-block:10px}
.brand{display:inline-flex;align-items:center;gap:10px;font-family:var(--f-d);font-weight:600;font-size:18px;letter-spacing:.06em}
.brand__logo{display:block;height:40px;width:auto}
.brand__sub{padding-left:14px;border-left:1px solid var(--line-2);font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--cyan)}
.nav{display:flex;align-items:center;gap:26px;font-size:15px;font-weight:600;margin-right:auto}
.nav a{color:var(--muted);transition:color .2s}.nav a:hover,.nav a.is-on{color:var(--text)}
.nav a.is-on{box-shadow:0 2px 0 var(--cyan)}
.nav__f{margin:0}.nav__f button{background:none;border:0;padding:0;font:inherit;font-weight:600;color:var(--muted);cursor:pointer;transition:color .2s}.nav__f button:hover{color:var(--text)}
.user{display:flex;align-items:center;gap:14px;font-size:14px;color:var(--muted)}
.user b{color:var(--text);font-weight:700}
.user form{margin:0}
.uso{padding:6px 12px;border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.02);font-size:13px}

main.wrap{padding-top:32px;padding-bottom:72px}

/* eyebrow e títulos */
.eyebrow{display:inline-flex;align-items:center;gap:10px;font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--cyan)}
.dot{width:8px;height:8px;border-radius:50%;background:var(--cyan);box-shadow:0 0 0 4px rgba(34,211,238,.15),0 0 16px var(--cyan)}
.pg-title{font-size:clamp(32px,4.4vw,52px);font-weight:800;margin:14px 0 10px}
.lead{color:var(--muted);max-width:720px;font-size:15px}

/* avisos */
.msg{padding:14px 18px;border-radius:var(--r);margin-bottom:20px;border:1px solid rgba(46,229,157,.35);background:rgba(46,229,157,.08);color:var(--ok);font-weight:600}
.msg.e{border-color:rgba(255,95,87,.4);background:rgba(255,95,87,.08);color:#FF8F89}

/* formulários */
input,select,textarea{font:500 15px var(--f-b);color:var(--text);background:var(--surface-2);border:1px solid var(--line-2);border-radius:14px;padding:12px 14px;min-width:0;transition:border-color .2s,box-shadow .2s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(79,124,255,.25)}
::placeholder{color:var(--muted);opacity:.75}
select{cursor:pointer}

.box{padding:26px;border-radius:var(--r-lg);background:var(--surface);border:1px solid var(--line);margin-bottom:16px}
.fila{max-width:640px;margin:0 auto}.fila .btn--wa{width:100%;justify-content:center}.fila .acoes{margin-top:14px}form.lote{display:grid;gap:10px;grid-template-columns:1fr 1fr;margin-top:14px}form.lote .btn{grid-column:1/-1}
.rel{max-width:820px;margin:0 auto}.rel textarea{width:100%}.ach h3{margin:10px 0 8px}.ach p{margin:4px 0}.ach--critico{border-color:rgba(255,95,87,.45)}.ach--importante{border-color:rgba(255,154,61,.4)}ul.ok{list-style:none;padding:0;margin:12px 0 0}ul.ok li{padding:6px 0 6px 28px;position:relative}ul.ok li::before{content:"\2713";position:absolute;left:0;color:var(--ok);font-weight:700}
@media print{:root{--bg:#fff;--bg-2:#fff;--surface:#fff;--surface-2:#fff;--text:#111;--muted:#444;--line:#bbb;--line-2:#bbb}body::before{display:none}.hdr,.noprint,.nav{display:none!important}.box{break-inside:avoid;border:1px solid #bbb}em{-webkit-text-fill-color:#111;color:#111;background:none}}
.ac{position:relative;display:flex}.ac input{flex:1}
.ac ul{position:absolute;z-index:20;left:0;right:0;top:calc(100% + 6px);margin:0;padding:6px;list-style:none;background:var(--surface-2);border:1px solid var(--line-2);border-radius:14px;box-shadow:0 18px 40px rgba(0,0,0,.45);max-height:280px;overflow:auto}
.ac li{padding:10px 12px;border-radius:10px;cursor:pointer;font-size:15px}
.ac li[aria-selected=true],.ac li:hover{background:rgba(79,124,255,.18)}
form.busca{display:grid;gap:12px;grid-template-columns:1fr 1fr auto;margin-top:16px}
form.prox{margin:0 0 16px}form.prox .btn{width:100%}
.filtro{display:grid;gap:10px;grid-template-columns:repeat(5,1fr);margin:24px 0 8px}
.meta{color:var(--muted);font-size:14px}
.count{margin:14px 0 18px}.count b{color:var(--text)}

/* cards */
.leads{display:grid;gap:16px;grid-template-columns:repeat(auto-fill,minmax(min(100%,340px),1fr));align-items:start}
.card{position:relative;padding:24px;border-radius:var(--r);background:var(--surface);border:1px solid var(--line);overflow:hidden;transition:border-color .4s}
.card::before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(320px circle at 50% 0%,rgba(79,124,255,.16),transparent 60%);opacity:0;transition:opacity .4s}
.card:hover{border-color:var(--line-2)}.card:hover::before{opacity:1}
.card__top{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.card h3{font-size:21px;font-weight:700;line-height:1.15;overflow-wrap:anywhere}
.card .meta{margin-top:6px}
.badge{display:inline-flex;align-items:center;gap:8px;font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:6px 11px;border-radius:999px;border:1px solid var(--line-2);color:var(--muted)}
.badge--perfil{color:var(--cyan);border-color:rgba(34,211,238,.35);background:rgba(34,211,238,.08)}
.st-contatado{color:var(--blue);border-color:rgba(79,124,255,.45);background:rgba(79,124,255,.1)}
.st-respondeu,.st-reuniao{color:var(--cyan);border-color:rgba(34,211,238,.4);background:rgba(34,211,238,.08)}
.st-fechado{color:var(--ok);border-color:rgba(46,229,157,.4);background:rgba(46,229,157,.08)}
.st-perdido,.st-descartado,.st-nao_contatar{opacity:.7}
.tags{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 4px}
.tag{padding:6px 12px;border:1px solid var(--line);border-radius:999px;font-size:13px;color:var(--muted);background:rgba(255,255,255,.02)}
.acoes{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;align-items:center}.acoes form{margin:0}
.acoes select{border-radius:999px;padding:10px 16px;font-size:14px;font-weight:600}
.tel{color:var(--cyan);font-weight:700;font-size:14.5px;text-decoration:underline;text-underline-offset:3px}
details{margin-top:14px;border-top:1px solid var(--line);padding-top:12px;font-size:14px}
details summary{cursor:pointer;color:var(--cyan);font-weight:700}
.txt{background:rgba(255,255,255,.03);border:1px solid var(--line);border-radius:14px;padding:14px;margin-top:10px;color:var(--muted);line-height:1.55}
details form{display:grid;gap:10px;margin-top:10px}
details textarea,details input[type=tel],details input[name=nome]{width:100%}
details form input+input{margin-top:8px}
details .btn{justify-self:start}
.pg{display:flex;justify-content:space-between;margin:20px 0}

/* painel */
.tw{overflow-x:auto;border:1px solid var(--line);border-radius:var(--r);background:var(--surface);margin-bottom:20px}
table{width:100%;border-collapse:collapse;font-size:14.5px}
th,td{padding:12px 16px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}
tr:last-child td{border-bottom:0}
th{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);background:var(--surface-2)}
td:not(:first-child),th:not(:first-child){font-variant-numeric:tabular-nums}

/* entrar */
body.login{display:grid;place-items:center;padding:24px}
.login__box{width:min(420px,100%);padding:34px;border-radius:var(--r-lg);background:var(--surface);border:1px solid var(--line-2)}
.login__box .brand{margin-bottom:26px}
.login__box h1{font-size:clamp(30px,8vw,40px);font-weight:800;margin:12px 0 20px}
.login__box form{display:grid;gap:12px}
.login__box .e{color:#FF8F89;font-weight:600;font-size:14px}

@media (max-width:1100px){.filtro{grid-template-columns:repeat(3,1fr)}}
@media (max-width:960px){
  .hdr__in{padding-block:12px}
  .nav{order:3;width:100%;margin-right:0;gap:22px;padding-top:2px}
  .user{margin-left:auto}
  form.busca{grid-template-columns:1fr 1fr}form.busca .btn{grid-column:1/-1}
}
@media (max-width:600px){
  .wrap{padding:0 16px}
  .brand__logo{height:32px}
  main.wrap{padding-top:24px}
  .box{padding:20px}
  form.busca{grid-template-columns:1fr}
  .filtro{grid-template-columns:1fr 1fr}
  .user .uso,.user b{display:none}
  input,select,textarea{font-size:16px}
  .card{padding:20px}
  .acoes .btn--wa,.acoes .btn--cta{flex:1 1 100%}
  .login__box{padding:26px}
}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
CSS;

// ---------- banco ----------
$dsn = $C['dsn'] ?? "mysql:host={$C['db']['host']};dbname={$C['db']['name']};charset=utf8mb4";
$pdo = new PDO($dsn, $C['db']['user'] ?? null, $C['db']['pass'] ?? null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

const DUMMY_HASH = '$2y$10$2gS0BGcgl.laP8TOjWcZM.cI0BmbmjTbBfqjpZoFTpZngTVMHu1aC'; // iguala o tempo quando o usuário não existe
$STATUS = ['novo' => 'Novo', 'contatado' => 'Contatado', 'respondeu' => 'Respondeu', 'reuniao' => 'Reunião', 'fechado' => 'Fechado',
    'perdido' => 'Perdido', 'nao_contatar' => 'Não contatar', 'descartado' => 'Descartado'];
$SOCIAL = ['instagram.com', 'facebook.com', 'fb.com', 'linktr.ee', 'wa.me', 'whatsapp.com', 'tiktok.com', 'beacons.ai', 'linkin.bio', 'bio.site'];
$ROT = ['sem_site' => 'Sem site', 'so_rede' => 'Só rede social', 'site_ruim' => 'Site fraco'];

function agora(): string { return date('Y-m-d H:i:s'); }
function entrada(array $src, string $k): string { return is_string($src[$k] ?? null) ? $src[$k] : ''; }
function flash(string $tipo, string $msg): void { $_SESSION['flash'] = [$tipo, $msg]; }
function voltar(): void {
    $p = '/' . ltrim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    $q = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $p . ($q !== '' ? '?' . $q : ''));
    exit;
}
function nomeDe(string $u): string { global $USERS; return $USERS[$u]['nome'] ?? ucfirst($u); }
function registrar(PDO $pdo, int $leadId, string $usuario, string $acao, ?string $detalhe = null): void {
    $pdo->prepare('INSERT INTO lead_log (lead_id, usuario, acao, detalhe, criado) VALUES (?,?,?,?,?)')->execute([$leadId, $usuario, $acao, $detalhe, agora()]);
}
function usoHoje(PDO $pdo, string $u): int {
    $q = $pdo->prepare('SELECT qtd FROM uso_busca WHERE usuario=? AND dia=?'); $q->execute([$u, date('Y-m-d')]);
    return (int)$q->fetchColumn();
}
function somaUso(PDO $pdo, string $u): void {
    $d = date('Y-m-d');
    $up = $pdo->prepare('UPDATE uso_busca SET qtd=qtd+1 WHERE usuario=? AND dia=?'); $up->execute([$u, $d]);
    if ($up->rowCount() === 0) {
        try { $pdo->prepare('INSERT INTO uso_busca (usuario, dia, qtd) VALUES (?,?,1)')->execute([$u, $d]); }
        catch (PDOException $e) { $up->execute([$u, $d]); } // outra requisição inseriu antes
    }
}

// ---------- login ----------
function falhasRecentes(PDO $pdo, string $ip, string $u): int {
    $q = $pdo->prepare('SELECT COUNT(*) FROM login_tentativas WHERE criado > ? AND (ip = ? OR usuario = ?)');
    $q->execute([date('Y-m-d H:i:s', time() - 900), $ip, $u]);
    return (int)$q->fetchColumn();
}
// Senha atual: a que o usuário definiu (tabela senhas) ou, se nunca trocou, a inicial do config.php.
function senhaAtual(PDO $pdo, string $u, array $usr): array {
    $q = $pdo->prepare('SELECT hash FROM senhas WHERE usuario=?'); $q->execute([$u]);
    $h = $q->fetchColumn();
    return $h !== false ? ['hash' => (string)$h, 'trocada' => true] : ['hash' => (string)$usr['hash'], 'trocada' => false];
}
function salvarSenha(PDO $pdo, string $u, string $hash): void {
    $up = $pdo->prepare('UPDATE senhas SET hash=?, atualizado=? WHERE usuario=?'); $up->execute([$hash, agora(), $u]);
    if ($up->rowCount() === 0) {
        try { $pdo->prepare('INSERT INTO senhas (usuario, hash, atualizado) VALUES (?,?,?)')->execute([$u, $hash, agora()]); }
        catch (PDOException $e) { $up->execute([$hash, agora(), $u]); }
    }
}
$erro = '';
if (isset($_POST['login'])) {
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $u = substr(strtolower(trim(entrada($_POST, 'u'))), 0, 40);
    if (!hash_equals($_SESSION['t'], entrada($_POST, 't'))) {
        $erro = 'Sessão expirada. Tente de novo.';
    } elseif (falhasRecentes($pdo, $ip, $u) >= 8) {
        $erro = 'Muitas tentativas. Aguarde 15 minutos.';
    } else {
        $usr = $USERS[$u] ?? null;
        $sa = $usr ? senhaAtual($pdo, $u, $usr) : null;
        $ok = password_verify(substr(entrada($_POST, 'p'), 0, 200), $sa['hash'] ?? DUMMY_HASH);
        if ($usr && $ok) {
            $pdo->prepare('DELETE FROM login_tentativas WHERE ip = ? OR usuario = ?')->execute([$ip, $u]);
            session_regenerate_id(true);
            $_SESSION['user'] = $u;
            $_SESSION['trocar'] = !$sa['trocada']; // primeiro acesso: obriga a escolher a própria senha
            $_SESSION['t'] = bin2hex(random_bytes(16));
            $_SESSION['n'] = bin2hex(random_bytes(8));
            header('Location: ./'); exit;
        }
        $pdo->prepare('INSERT INTO login_tentativas (ip, usuario, criado) VALUES (?,?,?)')->execute([$ip, $u, agora()]);
        if (random_int(1, 20) === 1) $pdo->prepare('DELETE FROM login_tentativas WHERE criado < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
        $erro = 'Usuário ou senha incorretos.';
    }
}
$me = $_SESSION['user'] ?? null;
if ($me !== null && !isset($USERS[$me])) { $me = null; unset($_SESSION['user']); }
if (!$me) { ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Entrar | Prospec</title><link rel="icon" type="image/svg+xml" href="<?= h($base) ?>assets/img/logo-simbolo.svg">
<?= $fontes ?><style><?= $css ?></style></head>
<body class="login"><div class="login__box"><div class="brand"><img class="brand__logo" src="<?= h($base) ?>assets/img/logo.svg" alt="NEMAX Digital" width="437" height="100"><span class="brand__sub">Prospec</span></div>
<span class="eyebrow"><span class="dot"></span>Acesso restrito</span><h1>Entrar no <em>painel</em></h1>
<form method="post"><?php if ($erro) echo '<p class="e">' . h($erro) . '</p>'; ?>
<input type="hidden" name="t" value="<?= h($_SESSION['t']) ?>">
<input name="u" placeholder="Usuário" autocomplete="username" required><input name="p" type="password" placeholder="Senha" autocomplete="current-password" required><button class="btn btn--cta btn--lg" name="login" value="1">Entrar</button></form></div></body></html>
<?php exit; }

// ---------- troca de senha (obrigatória no primeiro acesso) ----------
if (!empty($_SESSION['trocar'])) {
    $erroS = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['t'], entrada($_POST, 't'))) { http_response_code(403); exit('Sessão expirada. Recarregue a página.'); }
        $a = entrada($_POST, 'a');
        if ($a === 'sair') {
            $_SESSION = [];
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
            header('Location: ./'); exit;
        }
        if ($a === 'senha') {
            $n1 = substr(entrada($_POST, 'n1'), 0, 200); $n2 = substr(entrada($_POST, 'n2'), 0, 200);
            $atual = senhaAtual($pdo, $me, $USERS[$me]);
            if (strlen($n1) < 8) $erroS = 'Use pelo menos 8 caracteres.';
            elseif ($n1 !== $n2) $erroS = 'As duas senhas não são iguais.';
            elseif (password_verify($n1, $atual['hash']) || password_verify($n1, (string)$USERS[$me]['hash'])) $erroS = 'Escolha uma senha diferente da atual.';
            else {
                salvarSenha($pdo, $me, password_hash($n1, PASSWORD_DEFAULT));
                session_regenerate_id(true);
                $_SESSION['trocar'] = false;
                flash('info', 'Senha alterada.');
                header('Location: ./'); exit;
            }
        }
    }
    ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Nova senha | Prospec</title><link rel="icon" type="image/svg+xml" href="<?= h($base) ?>assets/img/logo-simbolo.svg">
<?= $fontes ?><style><?= $css ?></style></head>
<body class="login"><div class="login__box"><div class="brand"><img class="brand__logo" src="<?= h($base) ?>assets/img/logo.svg" alt="NEMAX Digital" width="437" height="100"><span class="brand__sub">Prospec</span></div>
<span class="eyebrow"><span class="dot"></span>Olá, <?= h(nomeDe($me)) ?></span><h1>Crie sua <em>senha</em></h1>
<p class="lead" style="margin:-8px 0 18px">Escolha uma senha só sua para continuar. Mínimo de 8 caracteres.</p>
<form method="post"><?php if ($erroS) echo '<p class="e">' . h($erroS) . '</p>'; ?>
<input type="hidden" name="t" value="<?= h($_SESSION['t']) ?>"><input type="hidden" name="a" value="senha">
<input name="n1" type="password" placeholder="Nova senha" autocomplete="new-password" minlength="8" required><input name="n2" type="password" placeholder="Repita a nova senha" autocomplete="new-password" minlength="8" required><button class="btn btn--cta btn--lg">Salvar senha</button></form>
<form method="post" style="margin-top:12px"><input type="hidden" name="t" value="<?= h($_SESSION['t']) ?>"><input type="hidden" name="a" value="sair"><button class="btn btn--ghost btn--sm">Sair</button></form></div></body></html>
<?php exit; }

$ehAdmin = !empty($USERS[$me]['admin']);

// ---------- busca (funil) ----------
function buscarGoogle(array $C, string $nicho, string $cidade, int $page): array {
    $ch = curl_init($C['serper_url'] ?? 'https://google.serper.dev/maps');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_POST => 1, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-KEY: ' . $C['serper_key']],
        CURLOPT_POSTFIELDS => json_encode(['q' => "$nicho em $cidade", 'gl' => 'br', 'hl' => 'pt-br', 'page' => $page])]);
    $raw = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    $out = json_decode((string)$raw, true);
    if (!is_array($out)) return ['error' => 'sem resposta do Serper' . ($err !== '' ? " ($err)" : '')];
    if (!isset($out['places'])) return ['error' => (string)($out['message'] ?? 'a busca não retornou resultados') . ($http >= 400 ? " (HTTP $http)" : '')];
    return $out;
}

function executarBusca(PDO $pdo, array $C, string $me, string $nicho, string $cidade): array {
    global $SOCIAL;
    set_time_limit(150);
    $q = $pdo->prepare('SELECT pagina FROM buscas WHERE nicho=? AND cidade=?'); $q->execute([$nicho, $cidade]);
    $pg = (int)$q->fetchColumn() + 1;
    $res = buscarGoogle($C, $nicho, $cidade, $pg);
    if (isset($res['error'])) return ['erro' => 'Serper: ' . $res['error']];
    somaUso($pdo, $me);

    $places = array_values(array_filter($res['places'], 'is_array'));
    $chaves = array_map('chavePlace', $places);
    $conhecidos = [];
    if ($chaves) {
        $s = $pdo->prepare('SELECT place_id FROM leads WHERE place_id IN (' . implode(',', array_fill(0, count($chaves), '?')) . ')');
        $s->execute($chaves);
        $conhecidos = array_flip($s->fetchAll(PDO::FETCH_COLUMN));
    }
    $desc = []; $aceitos = []; $checar = [];
    $conta = function (string $k) use (&$desc) { $desc[$k] = ($desc[$k] ?? 0) + 1; };
    foreach ($places as $i => $p) {
        if (isset($conhecidos[$chaves[$i]])) { $conta('já conhecidas'); continue; }
        $t = triagem($p, (int)$C['min_avaliacoes'], $SOCIAL);
        if (isset($t['desc'])) { $conta($t['desc']); continue; }
        $aceitos[$i] = $t;
        if (isset($t['checar'])) $checar[$i] = $t['checar'];
    }
    if ($checar) {
        $r = checarSites($checar, ['verificar_ssl' => $C['verificar_ssl'] ?? sslConfiavel(), 'permitir_privado' => (bool)($C['permitir_ip_privado'] ?? false), 'prazo' => 90]);
        foreach ($checar as $i => $_) {
            $av = avaliarSite($r[$i]);
            if ($av['tipo'] === 'inconclusivo') { $conta('site sem resposta conclusiva'); unset($aceitos[$i]); continue; }
            if ($av['tipo'] === 'ok') { $conta('site ok'); unset($aceitos[$i]); continue; }
            $aceitos[$i] = ['perfil' => 'site_ruim', 'motivos' => $av['motivos'], 'score' => $aceitos[$i]['score'] + $av['prob']];
        }
    }
    // A checagem dos sites pode levar mais de um minuto e o MySQL da hospedagem derruba conexões ociosas ("server has gone away"): reconecta.
    global $dsn;
    $pdo = new PDO($dsn, $C['db']['user'] ?? null, $C['db']['pass'] ?? null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $ins = $pdo->prepare('INSERT INTO leads (place_id,nome,endereco,telefone,site,nota,avaliacoes,nicho,cidade,perfil,motivos,score,criado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $novos = 0;
    foreach ($aceitos as $i => $a) {
        $p = $places[$i];
        try {
            $ins->execute([$chaves[$i], mb_substr((string)($p['title'] ?? ''), 0, 200), mb_substr((string)($p['address'] ?? ''), 0, 300),
                mb_substr((string)($p['phoneNumber'] ?? ''), 0, 40), mb_substr((string)($p['website'] ?? ''), 0, 300),
                (float)($p['rating'] ?? 0), (int)($p['ratingCount'] ?? 0), $nicho, $cidade, $a['perfil'],
                mb_substr(implode('; ', $a['motivos']), 0, 300), (int)$a['score'], agora()]);
            $novos++;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e; // 23000 = duplicado (outra busca inseriu antes)
        }
    }
    $up = $pdo->prepare('UPDATE buscas SET pagina=?, atualizado=? WHERE nicho=? AND cidade=?'); $up->execute([$pg, agora(), $nicho, $cidade]);
    if ($up->rowCount() === 0) {
        try { $pdo->prepare('INSERT INTO buscas (nicho, cidade, pagina, atualizado) VALUES (?,?,?,?)')->execute([$nicho, $cidade, $pg, agora()]); }
        catch (PDOException $e) { $up->execute([$pg, agora(), $nicho, $cidade]); }
    }
    $d = []; foreach ($desc as $k => $v) $d[] = "$v $k";
    return ['info' => "Página $pg: " . count($places) . " encontradas, $novos novas no funil" . ($d ? '. Descartadas: ' . implode(', ', $d) : '') . '.', 'ultima' => ['n' => $nicho, 'c' => $cidade]];
}

// ---------- autocomplete de cidades ----------
function semAcento(string $s): string {
    return strtr(mb_strtolower($s, 'UTF-8'), ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n']);
}
// Lista oficial de municípios (IBGE), baixada uma vez e guardada em cidades_ibge.json.
function listaCidades(PDO $pdo): array {
    $arq = __DIR__ . '/cidades_ibge.json';
    if (is_file($arq) && filesize($arq) > 1000) {
        $j = json_decode((string)file_get_contents($arq), true);
        if (is_array($j) && $j) return $j;
    }
    $lista = [];
    // Se o IBGE falhou há menos de 15 minutos, nem tenta de novo (evita travar o servidor a cada tecla digitada).
    $falha = __DIR__ . '/cidades_ibge.falhou';
    $j = null;
    if (!is_file($falha) || filemtime($falha) < time() - 900) {
        $ch = curl_init('https://servicodados.ibge.gov.br/api/v1/localidades/municipios');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => true, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $raw = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $j = ($raw !== false && $http === 200) ? json_decode((string)$raw, true) : null;
        if (!is_array($j)) @file_put_contents($falha, (string)time());
    }
    if (is_array($j)) {
        foreach ($j as $m) {
            $nome = is_string($m['nome'] ?? null) ? $m['nome'] : '';
            $uf = $m['microrregiao']['mesorregiao']['UF']['sigla'] ?? ($m['regiao-imediata']['regiao-intermediaria']['UF']['sigla'] ?? '');
            if ($nome !== '' && is_string($uf) && $uf !== '') $lista[] = [$nome, $uf, semAcento($nome)];
        }
    }
    if (count($lista) > 5000) { @file_put_contents($arq, json_encode($lista, JSON_UNESCAPED_UNICODE), LOCK_EX); return $lista; }
    // Sem acesso ao IBGE: usa só as cidades que já foram pesquisadas.
    $lista = [];
    foreach ($pdo->query('SELECT DISTINCT cidade FROM buscas ORDER BY cidade LIMIT 500')->fetchAll(PDO::FETCH_COLUMN) as $c) $lista[] = [$c, '', semAcento($c)];
    return $lista;
}
if (isset($_GET['cid'])) {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    $t = semAcento(trim(entrada($_GET, 'cid')));
    $t = preg_replace('/\s*[,\-]\s*[a-z]{2}$/', '', $t) ?? $t; // ignora ", SP" no fim
    $ini = []; $meio = [];
    if (mb_strlen($t) >= 2) {
        foreach (listaCidades($pdo) as $m) {
            if (str_starts_with($m[2], $t)) $ini[] = $m; elseif (str_contains($m[2], $t)) $meio[] = $m;
            if (count($ini) >= 8) break;
        }
    }
    $out = [];
    foreach (array_slice(array_merge($ini, $meio), 0, 8) as $m) $out[] = $m[1] !== '' ? $m[0] . ', ' . $m[1] : $m[0];
    echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

// ---------- ações (sempre POST + CSRF, depois redireciona) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['t'], entrada($_POST, 't'))) { http_response_code(403); exit('Sessão expirada. Recarregue a página.'); }
    $a = entrada($_POST, 'a');
    $id = (int)entrada($_POST, 'id');
    $dono = null;
    if ($id > 0) { $q = $pdo->prepare('SELECT dono FROM leads WHERE id=?'); $q->execute([$id]); $dono = $q->fetchColumn(); }
    $meu = ($dono !== false && $dono === $me);
    $pode = $meu || ($ehAdmin && $dono !== false && $dono !== null);

    switch ($a) {
        case 'trocarsenha':
            $_SESSION['trocar'] = true;
            header('Location: ./'); exit;
        case 'sair':
            $_SESSION = [];
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
            header('Location: ./'); exit;
        case 'contatou': // chamado pelo clique no botão do WhatsApp (sendBeacon)
            if ($meu) {
                $pdo->prepare("UPDATE leads SET status='contatado' WHERE id=? AND status='novo'")->execute([$id]);
                registrar($pdo, $id, $me, 'contatou');
            }
            http_response_code(204); exit;
        case 'analisar': // analisa o site informado e guarda o resultado para exibir (PRG)
            if (time() - (int)($_SESSION['t_analise'] ?? 0) < 4) { flash('erro', 'Aguarde alguns segundos entre uma análise e outra.'); header('Location: ./?site=1'); exit; }
            $_SESSION['t_analise'] = time(); set_time_limit(60);
            $emp = mb_substr(trim(entrada($_POST, 'empresa')), 0, 100);
            try { $r = analisarSite(entrada($_POST, 'url'), ['verificar_ssl' => $C['verificar_ssl'] ?? sslConfiavel(), 'permitir_privado' => (bool)($C['permitir_ip_privado'] ?? false)]); }
            catch (Throwable $e) { error_log('prospec analise: ' . $e->getMessage()); $r = ['erro' => 'A análise falhou. Tente de novo em instantes.']; }
            $_SESSION['analise'] = $r + ['empresa' => $emp, 'urlDigitada' => mb_substr(entrada($_POST, 'url'), 0, 300)];
            header('Location: ./?site=1'); exit;
        case 'pular': // fila: joga o lead para o fim desta sessão
            $pul = $_SESSION['pulados'] ?? []; $pul[] = $id; $_SESSION['pulados'] = array_slice(array_unique($pul), -500);
            break;
        case 'pegarlote': // pega os próximos N leads livres com WhatsApp válido, melhores primeiro
            $qtd = max(1, min(50, (int)entrada($_POST, 'qtd')));
            $w2 = ["dono IS NULL", "status='novo'"]; $a2 = [];
            foreach (['cidade', 'nicho'] as $campo) { $v = entrada($_POST, $campo); if ($v !== '') { $w2[] = "$campo=?"; $a2[] = $v; } }
            $pf = entrada($_POST, 'perfil'); if (isset($ROT[$pf])) { $w2[] = 'perfil=?'; $a2[] = $pf; }
            $q2 = $pdo->prepare('SELECT * FROM leads WHERE ' . implode(' AND ', $w2) . ' ORDER BY score DESC, avaliacoes DESC, id DESC LIMIT 400'); $q2->execute($a2);
            $pegos = 0; $claim = $pdo->prepare('UPDATE leads SET dono=? WHERE id=? AND dono IS NULL');
            foreach ($q2 as $cand) {
                if ($pegos >= $qtd) break;
                if (montarMensagem($cand, 'x', 'x') === null) continue; // sem WhatsApp válido
                $claim->execute([$me, (int)$cand['id']]);
                if ($claim->rowCount()) { registrar($pdo, (int)$cand['id'], $me, 'pegou', 'lote'); $pegos++; }
            }
            flash($pegos ? 'info' : 'erro', $pegos ? "$pegos leads na sua fila." : 'Não há leads livres com WhatsApp nesse filtro.');
            header('Location: ./?fila=1'); exit;
        case 'pegar':
            $u = $pdo->prepare('UPDATE leads SET dono=? WHERE id=? AND dono IS NULL'); $u->execute([$me, $id]);
            if ($u->rowCount()) registrar($pdo, $id, $me, 'pegou'); else flash('erro', 'Esse lead já foi pego por outra pessoa.');
            break;
        case 'devolver':
            if ($pode) {
                $pdo->prepare('UPDATE leads SET dono=NULL WHERE id=?')->execute([$id]);
                registrar($pdo, $id, $me, 'devolveu', $dono !== $me ? 'devolvido por admin (era de ' . $dono . ')' : null);
                flash('info', 'Lead devolvido para a fila.');
            }
            break;
        case 'status':
            $novo = entrada($_POST, 'status');
            if ($pode && isset($STATUS[$novo])) {
                $pdo->prepare('UPDATE leads SET status=? WHERE id=?')->execute([$novo, $id]);
                registrar($pdo, $id, $me, 'status', $novo);
            }
            break;
        case 'nota':
            $txt = mb_substr(trim(entrada($_POST, 'texto')), 0, 500);
            if ($pode && $txt !== '') { registrar($pdo, $id, $me, 'nota', $txt); flash('info', 'Nota salva.'); }
            break;
        case 'whatsapp': // número informado à mão (ex.: achado no site, Instagram ou link da bio)
            if ($pode) {
                $zap = telefoneWhatsApp(entrada($_POST, 'zap'));
                if ($zap === null) { flash('erro', 'Número inválido. Use DDD + 9 + número, por exemplo (17) 99104-8833.'); break; }
                $fmt = '+55 ' . substr($zap, 0, 2) . ' ' . substr($zap, 2, 5) . '-' . substr($zap, 7);
                $pdo->prepare('UPDATE leads SET telefone=? WHERE id=?')->execute([$fmt, $id]);
                registrar($pdo, $id, $me, 'telefone', $fmt);
                flash('info', 'WhatsApp salvo. A mensagem já está pronta no card.');
            }
            break;
        case 'responsavel': // número do responsável, passado pela recepção
            if ($pode) {
                $tel = trim(entrada($_POST, 'zap'));
                if ($tel === '') {
                    $pdo->prepare('DELETE FROM leads_resp WHERE lead_id=?')->execute([$id]);
                    registrar($pdo, $id, $me, 'responsavel', 'removido');
                    flash('info', 'Responsável removido.');
                    break;
                }
                $zap = telefoneWhatsApp($tel);
                if ($zap === null) { flash('erro', 'Número inválido. Use DDD + número, por exemplo (17) 99104-8833.'); break; }
                $fmt = '+55 ' . substr($zap, 0, 2) . ' ' . (strlen($zap) === 11 ? substr($zap, 2, 5) . '-' . substr($zap, 7) : substr($zap, 2, 4) . '-' . substr($zap, 6));
                $nomeResp = mb_substr(trim(preg_replace('/\s+/', ' ', entrada($_POST, 'nome'))), 0, 80);
                $up = $pdo->prepare('UPDATE leads_resp SET nome=?, telefone=?, atualizado=? WHERE lead_id=?'); $up->execute([$nomeResp, $fmt, agora(), $id]);
                if ($up->rowCount() === 0) {
                    try { $pdo->prepare('INSERT INTO leads_resp (lead_id, nome, telefone, atualizado) VALUES (?,?,?,?)')->execute([$id, $nomeResp, $fmt, agora()]); }
                    catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
                }
                registrar($pdo, $id, $me, 'responsavel', ($nomeResp !== '' ? $nomeResp . ' ' : '') . $fmt);
                flash('info', 'Responsável salvo. A mensagem para ele já está pronta no card.');
            }
            break;
        case 'buscar':
        case 'proxima':
            if (!hash_equals($_SESSION['n'], entrada($_POST, 'n'))) { flash('erro', 'Essa busca já tinha sido enviada, nada foi cobrado de novo.'); break; }
            $_SESSION['n'] = bin2hex(random_bytes(8));
            if ($a === 'proxima') {
                $ult = $_SESSION['ultima'] ?? null;
                if (!$ult) { flash('erro', 'Faça uma busca primeiro.'); break; }
                $nicho = $ult['n']; $cidade = $ult['c'];
            } else {
                $nicho = mb_strtolower(trim(entrada($_POST, 'nicho')), 'UTF-8');
                $cidade = mb_convert_case(trim(entrada($_POST, 'cidade')), MB_CASE_TITLE, 'UTF-8');
                $cidade = preg_replace_callback('/([,\s-]\s*)([A-Za-z]{2})$/u', fn($m) => $m[1] . strtoupper($m[2]), $cidade) ?? $cidade;
            }
            if ($nicho === '' || $cidade === '' || mb_strlen($nicho) > 100 || mb_strlen($cidade) > 100) { flash('erro', 'Informe nicho e cidade.'); break; }
            $limite = (int)($C['limite_buscas_dia'] ?? 30);
            if (usoHoje($pdo, $me) >= $limite) { flash('erro', "Limite de $limite buscas por dia atingido."); break; }
            try { $r = executarBusca($pdo, $C, $me, $nicho, $cidade); }
            catch (Throwable $e) {
                error_log('prospec busca: ' . $e->getMessage());
                $r = ['erro' => 'A busca falhou' . ($ehAdmin ? ': ' . mb_substr($e->getMessage(), 0, 300) : '. Avise o administrador.')];
            }
            if (isset($r['erro'])) flash('erro', $r['erro']);
            else { flash('info', $r['info']); $_SESSION['ultima'] = $r['ultima']; }
            break;
    }
    voltar();
}

// ---------- filtros ----------
$fStatus = entrada($_GET, 'status') ?: 'todos'; // sem filtro por padrão
if ($fStatus !== 'todos' && !isset($STATUS[$fStatus])) $fStatus = 'todos';
$fc = entrada($_GET, 'cidade'); $fn = entrada($_GET, 'nicho');
$fp = in_array(entrada($_GET, 'perfil'), array_keys($ROT), true) ? entrada($_GET, 'perfil') : '';
$fr = in_array(entrada($_GET, 'resp'), ['meus', 'livres'], true) ? entrada($_GET, 'resp') : 'todos';
$pag = max(1, (int)entrada($_GET, 'pag'));
$porPag = max(10, min(200, (int)($C['por_pagina'] ?? 50)));

$w = []; $args = [];
if ($fStatus !== 'todos') { $w[] = 'l.status=?'; $args[] = $fStatus; }
if ($fc !== '') { $w[] = 'l.cidade=?'; $args[] = $fc; }
if ($fn !== '') { $w[] = 'l.nicho=?'; $args[] = $fn; }
if ($fp !== '') { $w[] = 'l.perfil=?'; $args[] = $fp; }
if ($fr === 'meus') { $w[] = 'l.dono=?'; $args[] = $me; } elseif ($fr === 'livres') { $w[] = 'l.dono IS NULL'; }
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
$ordem = ' ORDER BY l.score DESC, l.avaliacoes DESC, l.id DESC';

// ---------- exportação CSV (admin) ----------
if (isset($_GET['csv']) && $ehAdmin) {
    $q = $pdo->prepare('SELECT l.* FROM leads l' . $where . $ordem . ' LIMIT 5000'); $q->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $cols = ['nome', 'telefone', 'endereco', 'site', 'nota', 'avaliacoes', 'nicho', 'cidade', 'perfil', 'motivos', 'score', 'status', 'dono', 'criado'];
    echo implode(';', array_map('csvCelula', $cols)) . "\r\n";
    foreach ($q as $l) echo implode(';', array_map(fn($c) => csvCelula($l[$c] ?? ''), $cols)) . "\r\n";
    exit;
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$usoHoje = usoHoje($pdo, $me); $limiteDia = (int)($C['limite_buscas_dia'] ?? 30);
$painel = isset($_GET['painel']);
$fila = isset($_GET['fila']) && !$painel;
$analisar = isset($_GET['site']) && !$painel && !$fila;

if (!$painel && !$fila && !$analisar) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM leads l' . $where); $q->execute($args);
    $total = (int)$q->fetchColumn();
    $paginas = max(1, (int)ceil($total / $porPag)); $pag = min($pag, $paginas);
    $q = $pdo->prepare('SELECT l.*,
        (SELECT MAX(g.criado) FROM lead_log g WHERE g.lead_id = l.id AND g.acao = \'contatou\') AS ultimo_contato,
        (SELECT g2.detalhe FROM lead_log g2 WHERE g2.lead_id = l.id AND g2.acao = \'nota\' ORDER BY g2.id DESC LIMIT 1) AS ultima_nota,
        r.nome AS resp_nome, r.telefone AS resp_tel
        FROM leads l LEFT JOIN leads_resp r ON r.lead_id = l.id' . $where . $ordem . ' LIMIT ' . $porPag . ' OFFSET ' . (($pag - 1) * $porPag));
    $q->execute($args);
    $leads = $q->fetchAll();
    $cidades = $pdo->query("SELECT DISTINCT cidade FROM leads WHERE cidade<>'' ORDER BY cidade")->fetchAll(PDO::FETCH_COLUMN);
    $nichos = $pdo->query("SELECT DISTINCT nicho FROM leads WHERE nicho<>'' ORDER BY nicho")->fetchAll(PDO::FETCH_COLUMN);
}
if ($fila) {
    $pul = array_flip($_SESSION['pulados'] ?? []);
    $q = $pdo->prepare("SELECT * FROM leads WHERE dono=? AND status='novo' ORDER BY score DESC, avaliacoes DESC, id DESC LIMIT 300"); $q->execute([$me]);
    $filaLeads = []; $semZap = 0;
    foreach ($q as $cand) {
        $m = montarMensagem($cand, nomeDe($me), (string)$C['agencia']);
        if ($m === null) { $semZap++; continue; }
        if (isset($pul[(int)$cand['id']])) continue;
        $filaLeads[] = [$cand, $m];
    }
    $cidades = $pdo->query("SELECT DISTINCT cidade FROM leads WHERE cidade<>'' ORDER BY cidade")->fetchAll(PDO::FETCH_COLUMN);
    $nichos = $pdo->query("SELECT DISTINCT nicho FROM leads WHERE nicho<>'' ORDER BY nicho")->fetchAll(PDO::FETCH_COLUMN);
}
$tok = h($_SESSION['t']);
function qs(array $extra): string { $g = $_GET; unset($g['csv'], $g['painel']); return '?' . http_build_query(array_filter($extra + $g, fn($v) => $v !== '' && $v !== null)); }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Prospec | NEMAX Digital</title><link rel="icon" type="image/svg+xml" href="<?= h($base) ?>assets/img/logo-simbolo.svg">
<?= $fontes ?><style><?= $css ?></style></head><body data-t="<?= $tok ?>">
<header class="hdr"><div class="wrap hdr__in">
<a class="brand" href="./" aria-label="Prospec, NEMAX Digital"><img class="brand__logo" src="<?= h($base) ?>assets/img/logo.svg" alt="NEMAX Digital" width="437" height="100"><span class="brand__sub">Prospec</span></a>
<nav class="nav"><a href="./"<?= ($painel || $fila || $analisar) ? '' : ' class="is-on"' ?>>Leads</a><a href="?fila=1"<?= $fila ? ' class="is-on"' : '' ?>>Fila</a><a href="?site=1"<?= $analisar ? ' class="is-on"' : '' ?>>Analisar</a><a href="?painel=1"<?= $painel ? ' class="is-on"' : '' ?>>Painel</a><?php if ($ehAdmin) echo '<a href="' . h(qs(['csv' => '1'])) . '">CSV</a>'; ?><form method="post" class="nav__f"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="trocarsenha"><button>Trocar senha</button></form></nav>
<div class="user"><span class="uso">Buscas hoje <?= $usoHoje ?>/<?= $limiteDia ?></span><b><?= h(nomeDe($me)) ?></b>
<form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="sair"><button class="btn btn--ghost btn--sm">Sair</button></form></div>
</div></header>
<main class="wrap">
<?php if ($flash) echo '<div class="msg' . ($flash[0] === 'erro' ? ' e' : '') . '">' . h($flash[1]) . '</div>'; ?>

<?php if ($painel):
    $linhas = $pdo->query("SELECT nicho, perfil, COUNT(*) AS total,
        SUM(CASE WHEN status IN ('contatado','respondeu','reuniao','fechado','perdido') THEN 1 ELSE 0 END) AS abordados,
        SUM(CASE WHEN status IN ('respondeu','reuniao','fechado') THEN 1 ELSE 0 END) AS responderam,
        SUM(CASE WHEN status IN ('reuniao','fechado') THEN 1 ELSE 0 END) AS reunioes,
        SUM(CASE WHEN status = 'fechado' THEN 1 ELSE 0 END) AS fechados
        FROM leads GROUP BY nicho, perfil ORDER BY total DESC")->fetchAll();
    $porStatus = $pdo->query('SELECT status, COUNT(*) AS n FROM leads GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $porDono = $pdo->query("SELECT dono, COUNT(*) AS pegos, SUM(CASE WHEN status <> 'novo' THEN 1 ELSE 0 END) AS trabalhados FROM leads WHERE dono IS NOT NULL GROUP BY dono")->fetchAll();
    $pct = fn($a, $b) => $b > 0 ? round(100 * $a / $b) . '%' : '-';
?>
<span class="eyebrow"><span class="dot"></span>Funil</span><h1 class="pg-title">Painel do <em>funil</em></h1>
<p class="lead" style="margin-bottom:24px">Abordados = contatado, respondeu, reunião, fechado ou perdido. % de resposta = quem respondeu ou avançou, dividido pelos abordados.</p>
<div class="tw"><table><tr><th>Status</th><th>Leads</th></tr><?php foreach ($STATUS as $k => $rot) echo '<tr><td>' . h($rot) . '</td><td>' . (int)($porStatus[$k] ?? 0) . '</td></tr>'; ?></table></div>
<div class="tw"><table><tr><th>Nicho</th><th>Perfil</th><th>Total</th><th>Abordados</th><th>Responderam</th><th>% resp.</th><th>Reuniões</th><th>Fechados</th></tr>
<?php foreach ($linhas as $r) echo '<tr><td>' . h($r['nicho']) . '</td><td>' . h($ROT[$r['perfil']] ?? $r['perfil']) . '</td><td>' . (int)$r['total'] . '</td><td>' . (int)$r['abordados'] . '</td><td>' . (int)$r['responderam'] . '</td><td>' . $pct((int)$r['responderam'], (int)$r['abordados']) . '</td><td>' . (int)$r['reunioes'] . '</td><td>' . (int)$r['fechados'] . '</td></tr>'; ?></table></div>
<div class="tw"><table><tr><th>Pessoa</th><th>Leads pegos</th><th>Já trabalhados</th></tr>
<?php foreach ($porDono as $r) echo '<tr><td>' . h(nomeDe((string)$r['dono'])) . '</td><td>' . (int)$r['pegos'] . '</td><td>' . (int)$r['trabalhados'] . '</td></tr>'; ?></table></div>
<?php elseif ($analisar): $an = $_SESSION['analise'] ?? null; ?>
<div class="rel">
<div class="box noprint"><span class="eyebrow"><span class="dot"></span>Analisar site</span>
<form class="busca" method="post" data-busy><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="analisar">
<input name="url" placeholder="www.empresa.com.br" maxlength="300" required value="<?= h($an['urlDigitada'] ?? '') ?>"><input name="empresa" placeholder="Nome da empresa (opcional)" maxlength="100" value="<?= h($an['empresa'] ?? '') ?>"><button class="btn btn--cta">Analisar</button></form>
<p class="meta">Confere segurança, celular, velocidade, contato e pontos que influenciam o Google. Leva alguns segundos.</p></div>
<?php if ($an && isset($an['erro'])): ?>
<div class="msg e"><?= h($an['erro']) ?></div>
<?php elseif ($an):
    $cont = ['critico' => 0, 'importante' => 0, 'atencao' => 0]; foreach ($an['achados'] as $x) $cont[$x['sev']]++;
    $rot = ['critico' => 'Crítico', 'importante' => 'Importante', 'atencao' => 'Atenção'];
    $resumo = resumoAnalise($an, (string)$an['empresa'], nomeDe($me), (string)$C['agencia']); ?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Relatório do site</span>
<h2><?= h($an['empresa'] !== '' ? $an['empresa'] : $an['host']) ?></h2>
<p class="meta"><?= h($an['url']) ?> · analisado em <?= h($an['quando']) ?> por <?= h((string)$C['agencia']) ?></p>
<div class="tags"><span class="tag"><?= $cont['critico'] ?> <?= $cont['critico'] === 1 ? 'crítico' : 'críticos' ?></span><span class="tag"><?= $cont['importante'] ?> <?= $cont['importante'] === 1 ? 'importante' : 'importantes' ?></span><span class="tag"><?= $cont['atencao'] ?> para melhorar</span><span class="tag"><?= count($an['bons']) ?> <?= count($an['bons']) === 1 ? 'ponto bom' : 'pontos bons' ?></span></div></div>
<?php if (!$an['achados']): ?><div class="box"><p>Não encontrei problemas nos itens verificados. O site está bem nos pontos técnicos básicos.</p></div><?php endif; ?>
<?php foreach ($an['achados'] as $x): ?>
<div class="box ach ach--<?= h($x['sev']) ?>"><span class="badge badge--perfil"><?= h($rot[$x['sev']]) ?></span><h3><?= h($x['titulo']) ?></h3>
<p><b>O que encontrei:</b> <?= h($x['detalhe']) ?></p><p class="meta"><b>Por que importa:</b> <?= h($x['impacto']) ?></p></div>
<?php endforeach; ?>
<?php if ($an['bons']): ?><div class="box"><span class="eyebrow"><span class="dot"></span>O que está bom</span><ul class="ok"><?php foreach ($an['bons'] as $b) echo '<li>' . h($b) . '</li>'; ?></ul></div><?php endif; ?>
<?php if ($an['obs'] || $an['avisos']): ?><p class="meta"><?php foreach (array_merge($an['obs'], $an['avisos']) as $o) echo h($o) . '<br>'; ?></p><?php endif; ?>
<div class="box noprint"><span class="eyebrow"><span class="dot"></span>Mensagem para o dono</span>
<textarea id="resumo-txt" rows="9" readonly><?= h($resumo) ?></textarea>
<div class="acoes"><button type="button" class="btn btn--cta" id="copiar">Copiar mensagem</button><a class="btn btn--wa" target="_blank" rel="noopener" href="<?= h('https://wa.me/?text=' . rawurlencode($resumo)) ?>">Abrir no WhatsApp</a><button type="button" class="btn btn--ghost" id="imprimir">Imprimir ou salvar em PDF</button></div></div>
<?php endif; ?>
</div>
<?php elseif ($fila): ?>
<div class="fila">
<?php $total = count($filaLeads); if ($total): [$l, $msg] = $filaLeads[0]; $lid = (int)$l['id']; ?>
<p class="meta count"><b><?= $total ?></b> na fila<?= $semZap ? ', ' . $semZap . ' sem WhatsApp válido (ligar pela aba Leads)' : '' ?></p>
<div class="card"><div class="card__top"><span class="badge badge--perfil"><?= h($ROT[$l['perfil']] ?? $l['perfil']) ?></span><span class="meta"><?= h($l['cidade']) ?> · <?= h($l['nicho']) ?></span></div>
<h3><?= h($l['nome']) ?></h3>
<div class="meta"><?= h($l['nota']) ?> estrelas, <?= (int)$l['avaliacoes'] ?> avaliações. Pontos: <?= (int)$l['score'] ?></div>
<div class="tags"><?php foreach (explode('; ', (string)$l['motivos']) as $m2) echo '<span class="tag">' . h($m2) . '</span>'; ?></div>
<div class="txt"><?= nl2br(h($msg[1])) ?></div>
<div class="acoes"><a class="btn btn--wa btn--lg fila-send" data-fid="<?= $lid ?>" target="_blank" rel="noopener" href="<?= h($msg[0]) ?>">Enviar no WhatsApp</a></div>
<div class="acoes">
<form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="pular"><input type="hidden" name="id" value="<?= $lid ?>"><button class="btn btn--ghost btn--sm">Pular</button></form>
<form method="post" data-confirm="Descartar este lead?"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="status"><input type="hidden" name="id" value="<?= $lid ?>"><input type="hidden" name="status" value="descartado"><button class="btn btn--ghost btn--sm">Descartar</button></form>
<form method="post" data-confirm="Devolver este lead para a fila geral?"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="devolver"><input type="hidden" name="id" value="<?= $lid ?>"><button class="btn btn--ghost btn--sm">Devolver</button></form>
</div></div>
<?php else: ?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Fila vazia</span><p class="meta">Pegue um lote para começar. Vêm primeiro os leads com mais pontos e WhatsApp válido.<?= $semZap ? ' Você tem ' . $semZap . ' sem WhatsApp válido (aba Leads).' : '' ?></p></div>
<?php endif; ?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Pegar mais leads</span>
<form class="lote" method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="pegarlote">
<select name="qtd"><?php foreach ([10, 20, 30, 50] as $n) echo '<option value="' . $n . '"' . ($n === 20 ? ' selected' : '') . '>' . $n . ' leads</option>'; ?></select>
<select name="cidade"><option value="">Todas as cidades</option><?php foreach ($cidades as $c) echo '<option>' . h($c) . '</option>'; ?></select>
<select name="nicho"><option value="">Todos os nichos</option><?php foreach ($nichos as $c) echo '<option>' . h($c) . '</option>'; ?></select>
<select name="perfil"><option value="">Todos os perfis</option><?php foreach ($ROT as $k => $r) echo '<option value="' . h($k) . '">' . h($r) . '</option>'; ?></select>
<button class="btn btn--cta">Pegar lote</button></form></div>
</div>
<?php else: ?>

<div class="box"><span class="eyebrow"><span class="dot"></span>Nova busca</span>
<form class="busca" method="post" data-busy><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="n" value="<?= h($_SESSION['n']) ?>"><input type="hidden" name="a" value="buscar">
<input name="nicho" placeholder="Nicho (ex: clínica veterinária)" maxlength="100" required><div class="ac"><input name="cidade" placeholder="Cidade (comece a digitar)" maxlength="100" required autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="ac-lista"><ul id="ac-lista" role="listbox" hidden></ul></div><button class="btn btn--cta">Buscar e filtrar</button></form></div>
<?php if (isset($_SESSION['ultima'])): $u = $_SESSION['ultima']; ?>
<form class="prox" method="post" data-busy><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="n" value="<?= h($_SESSION['n']) ?>"><input type="hidden" name="a" value="proxima"><button class="btn btn--ghost">Próxima página de <?= h($u['n']) ?> em <?= h($u['c']) ?></button></form>
<?php endif; ?>

<form class="filtro" method="get">
<select name="status" data-auto><?php foreach ($STATUS + ['todos' => 'Todos'] as $k => $rot) echo '<option value="' . h($k) . '"' . ($k === $fStatus ? ' selected' : '') . '>' . h($rot) . '</option>'; ?></select>
<select name="resp" data-auto><?php foreach (['todos' => 'De todos', 'meus' => 'Só meus', 'livres' => 'Sem dono'] as $k => $rot) echo '<option value="' . $k . '"' . ($k === $fr ? ' selected' : '') . '>' . $rot . '</option>'; ?></select>
<select name="perfil" data-auto><option value="">Todos os perfis</option><?php foreach ($ROT as $k => $rot) echo '<option value="' . $k . '"' . ($k === $fp ? ' selected' : '') . '>' . h($rot) . '</option>'; ?></select>
<select name="cidade" data-auto><option value="">Todas as cidades</option><?php foreach ($cidades as $c) echo '<option' . ($c === $fc ? ' selected' : '') . '>' . h($c) . '</option>'; ?></select>
<select name="nicho" data-auto><option value="">Todos os nichos</option><?php foreach ($nichos as $c) echo '<option' . ($c === $fn ? ' selected' : '') . '>' . h($c) . '</option>'; ?></select></form>
<?php if (!$leads) echo '<p class="meta">Nenhum lead com esses filtros. Faça uma busca ou mude os filtros.</p>'; else echo '<p class="meta count"><b>' . $total . '</b> leads, página ' . $pag . ' de ' . $paginas . '</p>';
echo '<div class="leads">'; foreach ($leads as $l):
    $lid = (int)$l['id'];
    $dono = $l['dono']; $meu = ($dono === $me); $pode = $meu || ($ehAdmin && $dono !== null);
    $msg = $meu ? montarMensagem($l, nomeDe($me), (string)$C['agencia']) : null;
    $temResp = !empty($l['resp_tel']);
    $resp = $temResp ? ['nome' => (string)$l['resp_nome'], 'telefone' => (string)$l['resp_tel']] : null;
    $msg2 = $meu ? montarSegunda($l, nomeDe($me), (string)$C['agencia']) : null;
    $msgR = ($meu && $resp) ? montarSegunda($l, nomeDe($me), (string)$C['agencia'], $resp) : null; ?>
<div class="card"><div class="card__top"><span class="badge badge--perfil"><?= h($ROT[$l['perfil']] ?? $l['perfil']) ?></span><span class="badge st-<?= h($l['status']) ?>"><?= h($STATUS[$l['status']] ?? $l['status']) ?></span></div>
<h3><?= h($l['nome']) ?></h3>
<div class="meta"><?= h($l['nota']) ?> estrelas, <?= (int)$l['avaliacoes'] ?> avaliações. Pontos: <?= (int)$l['score'] ?></div>
<div class="tags"><?php foreach (explode('; ', (string)$l['motivos']) as $m) echo '<span class="tag">' . h($m) . '</span>'; ?></div><div class="meta"><?= h($l['endereco']) ?></div>
<?php if ($l['ultimo_contato']) echo '<div class="meta">Último contato: ' . h(date('d/m H:i', strtotime((string)$l['ultimo_contato']))) . '</div>'; ?>
<?php if ($l['ultima_nota']) echo '<div class="meta">Nota: ' . h($l['ultima_nota']) . '</div>'; ?>
<div class="acoes">
<?php if ($dono === null): ?>
<form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="pegar"><input type="hidden" name="id" value="<?= $lid ?>"><button class="btn btn--cta">Pegar este lead</button></form>
<?php else: ?>
<?php if ($meu) {
    if ($msg) {
        echo '<a class="btn btn--wa wa" data-id="' . $lid . '" target="_blank" rel="noopener" href="' . h($msg[0]) . '">Abrir WhatsApp' . (strlen((string)telefoneWhatsApp((string)$l['telefone'])) === 10 ? ' (fixo)' : '') . '</a>';
        if ($msg2) echo '<a class="btn btn--ghost btn--sm wa" data-id="' . $lid . '" target="_blank" rel="noopener" href="' . h($msg2[0]) . '">2ª mensagem (link)</a>';
        if ($msgR) echo '<a class="btn btn--wa wa" data-id="' . $lid . '" target="_blank" rel="noopener" href="' . h($msgR[0]) . '">WhatsApp do responsável</a>';
    } else echo '<a class="tel" href="tel:' . h(preg_replace('/\D/', '', (string)$l['telefone'])) . '">Ligar: ' . h($l['telefone']) . '</a>';
} else echo '<span class="meta">Com ' . h(nomeDe((string)$dono)) . ' (' . h($STATUS[$l['status']] ?? $l['status']) . ')</span>'; ?>
<?php if ($pode): ?>
<form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="status"><input type="hidden" name="id" value="<?= $lid ?>">
<select name="status" data-auto><?php foreach ($STATUS as $k => $rot) echo '<option value="' . h($k) . '"' . ($k === $l['status'] ? ' selected' : '') . '>' . h($rot) . '</option>'; ?></select></form>
<form method="post" data-confirm="Devolver este lead para a fila?"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="devolver"><input type="hidden" name="id" value="<?= $lid ?>"><button class="btn btn--ghost btn--sm">Devolver</button></form>
<?php if ($l['perfil'] === 'site_ruim' && (string)$l['site'] !== ''): ?><form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="analisar"><input type="hidden" name="url" value="<?= h($l['site']) ?>"><input type="hidden" name="empresa" value="<?= h($l['nome']) ?>"><button class="btn btn--ghost btn--sm">Analisar site</button></form><?php endif; ?>
<?php endif; endif; ?>
</div>
<?php if ($msg) { echo '<details><summary>Ver as mensagens</summary><div class="txt"><b>1ª mensagem</b><br>' . nl2br(h($msg[1])) . '</div>';
    if ($msg2) echo '<div class="txt"><b>2ª mensagem (mesmo número, sem saudação)</b><br>' . nl2br(h($msg2[1])) . '</div>';
    if ($msgR) echo '<div class="txt"><b>Mensagem para o responsável</b><br>' . nl2br(h($msgR[1])) . '</div>';
    echo '</details>'; } ?>
<?php if ($pode): ?><details<?= $temResp ? ' open' : '' ?>><summary>Responsável<?= $temResp ? ': ' . h(trim((string)$l['resp_nome']) . ' ' . $l['resp_tel']) : '' ?></summary><form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="responsavel"><input type="hidden" name="id" value="<?= $lid ?>">
<input name="nome" maxlength="80" placeholder="Nome (ex: Dra. Ana), opcional" value="<?= h($l['resp_nome'] ?? '') ?>"><input type="tel" name="zap" maxlength="25" placeholder="(17) 99999-9999" inputmode="tel" value="<?= h($l['resp_tel'] ?? '') ?>"><button class="btn btn--ghost btn--sm">Salvar</button></form><p class="meta">Número que a recepção passou. Deixe o campo do número vazio e salve para remover.</p></details><?php endif; ?>
<?php if ($pode): ?><details><summary>Informar WhatsApp manualmente</summary><form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="whatsapp"><input type="hidden" name="id" value="<?= $lid ?>">
<input type="tel" name="zap" maxlength="25" placeholder="(17) 99999-9999" inputmode="tel" required><button class="btn btn--ghost btn--sm">Salvar número</button></form></details><?php endif; ?>
<?php if ($pode): ?><details><summary>Anotar</summary><form method="post"><input type="hidden" name="t" value="<?= $tok ?>"><input type="hidden" name="a" value="nota"><input type="hidden" name="id" value="<?= $lid ?>">
<textarea name="texto" rows="2" maxlength="500" placeholder="O que foi falado, quando retornar, motivo de perda..."></textarea><button class="btn btn--ghost btn--sm">Salvar nota</button></form></details><?php endif; ?></div>
<?php endforeach; echo '</div>'; ?>
<?php if ($paginas > 1): ?><div class="pg"><span><?php if ($pag > 1) echo '<a href="' . h(qs(['pag' => $pag - 1])) . '" class="btn btn--ghost btn--sm">&larr; Anterior</a>'; ?></span><span><?php if ($pag < $paginas) echo '<a href="' . h(qs(['pag' => $pag + 1])) . '" class="btn btn--ghost btn--sm">Próxima &rarr;</a>'; ?></span></div><?php endif; ?>
<?php endif; ?>
</main>
<script nonce="<?= $nonce ?>">
document.querySelectorAll('select[data-auto]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
document.querySelectorAll('form[data-busy]').forEach(function (f) { f.addEventListener('submit', function () {
  var b = f.querySelector('button'); if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Analisando os sites... pode levar até 1 minuto'; }, 0); } }); });
(function () {
  var inp = document.querySelector('.ac input'); if (!inp) return;
  var ul = document.getElementById('ac-lista'), tm, seq = 0, sel = -1;
  function fecha() { ul.hidden = true; inp.setAttribute('aria-expanded', 'false'); sel = -1; }
  function marca(i) { var li = ul.children; if (!li.length) return; sel = (i + li.length) % li.length;
    for (var k = 0; k < li.length; k++) li[k].setAttribute('aria-selected', k === sel ? 'true' : 'false'); }
  function escolhe(t) { inp.value = t; fecha(); inp.focus(); }
  function mostra(lista) { ul.textContent = '';
    if (!lista.length) return fecha();
    lista.forEach(function (t) { var li = document.createElement('li'); li.setAttribute('role', 'option'); li.textContent = t;
      li.addEventListener('mousedown', function (e) { e.preventDefault(); escolhe(t); }); ul.appendChild(li); });
    ul.hidden = false; inp.setAttribute('aria-expanded', 'true'); sel = -1; }
  inp.addEventListener('input', function () { clearTimeout(tm); var v = inp.value.trim();
    if (v.length < 2) return fecha();
    tm = setTimeout(function () { var my = ++seq;
      fetch('?cid=' + encodeURIComponent(v), { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : []; })
        .then(function (l) { if (my === seq) mostra(Array.isArray(l) ? l : []); }).catch(fecha); }, 200); });
  inp.addEventListener('keydown', function (e) {
    if (ul.hidden) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); marca(sel + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); marca(sel - 1); }
    else if (e.key === 'Enter' && sel >= 0) { e.preventDefault(); escolhe(ul.children[sel].textContent); }
    else if (e.key === 'Escape') fecha(); });
  inp.addEventListener('blur', fecha);
})();
document.querySelectorAll('a.fila-send').forEach(function (a) { a.addEventListener('click', function () {
  var fd = new FormData(); fd.append('t', document.body.dataset.t); fd.append('a', 'contatou'); fd.append('id', a.dataset.fid);
  fetch('', { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin' }).catch(function () {}).then(function () { setTimeout(function () { location.reload(); }, 300); }); }); });
(function () {
  var c = document.getElementById('copiar'), t = document.getElementById('resumo-txt'), i = document.getElementById('imprimir');
  if (c && t) c.addEventListener('click', function () {
    var ok = function () { c.textContent = 'Copiado'; setTimeout(function () { c.textContent = 'Copiar mensagem'; }, 1800); };
    if (navigator.clipboard) navigator.clipboard.writeText(t.value).then(ok).catch(function () { t.select(); document.execCommand('copy'); ok(); });
    else { t.select(); document.execCommand('copy'); ok(); } });
  if (i) i.addEventListener('click', function () { window.print(); });
})();
document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });
document.querySelectorAll('a.wa[data-id]').forEach(function (a) { a.addEventListener('click', function () {
  var fd = new FormData(); fd.append('t', document.body.dataset.t); fd.append('a', 'contatou'); fd.append('id', a.dataset.id);
  if (navigator.sendBeacon) navigator.sendBeacon('', fd); }); });
</script>
</body></html>
