<?php
// Funções do Prospec que não dependem de sessão nem de banco (por isso são testáveis).

// Portfólio citado na mensagem quando o nicho do lead contém uma das palavras (sem diferenciar maiúsculas).
const UA_NAVEGADOR = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- telefone e mensagem ----------

// Devolve o número nacional para o WhatsApp: 11 dígitos (celular) ou 10 (fixo, muitas empresas usam WhatsApp no fixo).
// Rejeita 0800/0300 e números sem DDD válido.
function telefoneWhatsApp(string $tel): ?string {
    $d = preg_replace('/\D/', '', $tel);
    if (preg_match('/^0?(800|300|500|900)/', $d)) return null;
    $d = ltrim($d, '0');
    if (strlen($d) > 11 && str_starts_with($d, '55')) $d = substr($d, 2);
    if (!preg_match('/^[1-9][1-9]/', $d)) return null; // DDD válido (11 a 99)
    if (strlen($d) === 11 && $d[2] === '9') return $d;
    if (strlen($d) === 10 && $d[2] >= '2' && $d[2] <= '5') return $d;
    return null;
}

function saudacao(?int $hora = null): string {
    $hora ??= (int)(new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('G');
    if ($hora >= 5 && $hora < 12) return 'bom dia';
    if ($hora >= 12 && $hora < 18) return 'boa tarde';
    return 'boa noite';
}

function linkZap(string $d, string $msg): array {
    return ['https://wa.me/55' . $d . '?text=' . rawurlencode($msg), $msg];
}

// Frase curta para cada problema que o painel detecta no site.
const PROBLEMA_CURTO = [
    'certificado inválido' => 'aparece como "não seguro" no navegador',
    'sem HTTPS' => 'aparece como "não seguro" no navegador',
    'não adaptado ao celular' => 'não se ajusta bem ao celular',
    'carrega devagar' => 'demora para abrir',
    'sem botão de WhatsApp' => 'não tem botão de WhatsApp para o cliente chamar',
    'rodapé desatualizado' => 'está com o ano do rodapé desatualizado',
    'site não abriu' => 'não abriu quando tentei acessar',
    'site fora do ar' => 'não abriu quando tentei acessar',
];

function gancho(array $l): string {
    return 'Quando o cliente da ' . $l['nome'] . ' pesquisa no Google, ele fecha com vocês ou com o concorrente que aparece melhor?';
}

function blocoSite(): string {
    return "Veja os projetos que já entregamos, com prints reais e o que cada site faz:\nhttps://nemaxdigital.com.br\n\n"
        . 'Leva 1 minuto e abre bem no celular. Entra lá e me diz qual deles mais combina com vocês.';
}

// 1ª mensagem: curta, só abre a conversa e pergunta com quem falar. Retorna [link do WhatsApp, texto] ou null.
function montarMensagem(array $l, string $nome, string $agencia, ?int $hora = null): ?array {
    $d = telefoneWhatsApp((string)$l['telefone']);
    if ($d === null) return null;
    $v = ((int)$l['id']) % 3; // variação estável: a prévia é igual ao que será enviado
    $sau = saudacao($hora);
    $abertura = [
        ucfirst($sau) . '! Aqui é ' . $nome . ', da ' . $agencia . '.',
        'Oi, ' . $sau . '! Sou ' . $nome . ', da ' . $agencia . '.',
        'Olá, ' . $sau . '! Aqui é ' . $nome . ', da ' . $agencia . '.',
    ][$v];
    $empresa = (string)$l['nome'];
    if ($l['perfil'] === 'sem_site') {
        $corpo = 'Procurei a ' . $empresa . ' no Google e vi que o perfil não tem site. Com quem posso falar sobre isso?';
    } elseif ($l['perfil'] === 'so_rede') {
        $corpo = 'Vi que o perfil da ' . $empresa . ' no Google leva direto para as redes sociais. Quem cuida disso aí? Queria mostrar uma ideia rápida.';
    } elseif ($l['perfil'] === 'site_ruim') {
        $frase = null;
        foreach (explode('; ', (string)$l['motivos']) as $m) { if (isset(PROBLEMA_CURTO[$m])) { $frase = PROBLEMA_CURTO[$m]; break; } }
        $corpo = $frase
            ? 'Abri o site da ' . $empresa . ' e ele ' . $frase . '. Com quem posso falar sobre isso? Mando um print do que vi.'
            : 'Abri o site da ' . $empresa . ' e vi alguns pontos que podem estar custando clientes. Com quem posso falar sobre isso?';
    } else {
        return null;
    }
    return linkZap($d, $abertura . ' ' . $corpo);
}

// 2ª mensagem. Sem $resp: continuação da conversa no mesmo número, sem repetir a saudação.
// Com $resp (['nome'=>..., 'telefone'=>...]): primeiro contato com o responsável, com saudação e contexto.
function montarSegunda(array $l, string $nome, string $agencia, ?array $resp = null, ?int $hora = null): ?array {
    if ($resp !== null) {
        $d = telefoneWhatsApp((string)($resp['telefone'] ?? ''));
        if ($d === null) return null;
        $quem = trim((string)($resp['nome'] ?? ''));
        $ctx = [
            'sem_site' => 'Vi que o perfil não tem site no Google.',
            'so_rede' => 'Vi que o perfil no Google leva direto para as redes sociais.',
            'site_ruim' => 'Vi alguns pontos no site que podem estar custando clientes.',
        ][$l['perfil']] ?? '';
        $msg = ucfirst(saudacao($hora)) . ($quem !== '' ? ', ' . $quem : '') . '! Aqui é ' . $nome . ', da ' . $agencia . '. '
            . 'Falei com o pessoal da ' . $l['nome'] . ' e me passaram seu contato. ' . $ctx . "\n\n"
            . gancho($l) . "\n\n" . blocoSite();
        return linkZap($d, $msg);
    }
    $d = telefoneWhatsApp((string)$l['telefone']);
    if ($d === null) return null;
    return linkZap($d, gancho($l) . "\n\n" . blocoSite());
}

// ---------- detecção em HTML ----------

// Maior ano encontrado perto de © / copyright (pega "2015-2025" corretamente).
function anoRodape(string $html): ?int {
    if (!mb_check_encoding($html, 'UTF-8')) $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
    $max = null;
    if (preg_match_all('/(?:©|&copy;|&#169;|&#xa9;|copyright)[^<]{0,80}/iu', $html, $m)) {
        foreach ($m[0] as $trecho) {
            if (preg_match_all('/\b(?:19|20)\d\d\b/', $trecho, $anos)) {
                foreach ($anos[0] as $a) $max = max($max ?? 0, (int)$a);
            }
        }
    }
    return $max;
}

function temWhatsApp(string $html): bool { return (bool)preg_match('/whatsapp|wa\.me/i', $html); }
function temViewport(string $html): bool { return (bool)preg_match('/<meta[^>]+name=["\']viewport/i', $html); }

// ---------- rede segura ----------

// $permitirPrivado: true libera tudo (só para testes) ou lista de IPs liberados (também só para testes).
function ipPublico(string $ip, bool|array $permitirPrivado = false): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
    if ($permitirPrivado === true || (is_array($permitirPrivado) && in_array($ip, $permitirPrivado, true))) return true;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    $n = ip2long($ip);
    return !($n !== false && ($n & 0xFFC00000) === 0x64400000); // 100.64.0.0/10 (CGNAT)
}

// ['ip' => string|null, 'motivo' => null|'dns'|'privado']
function resolverIp(string $host, bool|array $permitirPrivado = false): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return ipPublico($host, $permitirPrivado) ? ['ip' => $host, 'motivo' => null] : ['ip' => null, 'motivo' => 'privado'];
    }
    $ips = @gethostbynamel($host);
    if (!$ips) return ['ip' => null, 'motivo' => 'dns'];
    foreach ($ips as $ip) if (!ipPublico($ip, $permitirPrivado)) return ['ip' => null, 'motivo' => 'privado'];
    return ['ip' => $ips[0], 'motivo' => null];
}

function urlAbsoluta(string $base, string $loc): string {
    if (preg_match('~^https?://~i', $loc)) return $loc;
    $p = parse_url($base);
    $origem = ($p['scheme'] ?? 'http') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($loc, '//')) return ($p['scheme'] ?? 'http') . ':' . $loc;
    if (str_starts_with($loc, '/')) return $origem . $loc;
    $dir = preg_replace('~[^/]*$~', '', $p['path'] ?? '/');
    return $origem . ($dir === '' ? '/' : $dir) . $loc;
}

// Testa se o servidor consegue validar certificados (evita acusar "certificado inválido" por falha do próprio servidor).
function sslConfiavel(string $url = 'https://www.google.com/'): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2]);
    curl_exec($ch);
    $e = curl_errno($ch);
    curl_close($ch);
    return !in_array($e, [51, 58, 60, 77, 83], true);
}

/**
 * Acessa vários sites em paralelo. Segue redirecionamentos manualmente (máx. 5), só http/https,
 * só IPs públicos (anti-SSRF), corpo limitado a 600 KB, 2 tentativas em falhas de rede.
 * Retorna, por chave: status (ok|fora|bloqueado|inconclusivo|invalido), code, final, tempo, html, ssl_invalido.
 * Opções: permitir_privado (só para testes), verificar_ssl, prazo (segundos).
 */
function checarSites(array $urls, array $op = []): array {
    $permitirPrivado = $op['permitir_privado'] ?? false;
    $verificarSsl = (bool)($op['verificar_ssl'] ?? true);
    $prazo = microtime(true) + (float)($op['prazo'] ?? 60);
    $maxBytes = 600000;
    $est = [];
    foreach ($urls as $k => $u) {
        $est[$k] = ['url' => $u, 'final' => $u, 'hops' => 0, 'tent' => 0, 'verificar' => $verificarSsl, 'ssl_invalido' => false,
            'tempo' => 0.0, 'fim' => false, 'status' => 'inconclusivo', 'code' => 0, 'html' => ''];
    }
    for ($rodada = 0; $rodada < 14; $rodada++) {
        if (microtime(true) > $prazo) break;
        $mh = curl_multi_init();
        $hs = []; $buf = [];
        foreach ($est as $k => &$e) {
            if ($e['fim']) continue;
            $p = parse_url($e['url']);
            $esq = strtolower((string)($p['scheme'] ?? ''));
            $host = (string)($p['host'] ?? '');
            $porta = (int)($p['port'] ?? ($esq === 'https' ? 443 : 80));
            if (!in_array($esq, ['http', 'https'], true) || $host === '' || preg_match('/[^\x21-\x7e]/', $host) || !in_array($porta, [80, 443, 8080, 8443], true)) {
                $e['fim'] = true; $e['status'] = 'invalido'; continue;
            }
            $r = resolverIp($host, $permitirPrivado);
            if ($r['motivo'] === 'privado') { $e['fim'] = true; $e['status'] = 'invalido'; continue; }
            if ($r['motivo'] === 'dns') {
                if (++$e['tent'] >= 2) { $e['fim'] = true; $e['status'] = 'fora'; }
                continue;
            }
            $buf[$k] = '';
            $ch = curl_init($e['url']);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT => UA_NAVEGADOR, CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8', 'Accept-Language: pt-BR,pt;q=0.9'],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_RESOLVE => [$host . ':' . $porta . ':' . $r['ip']],
                CURLOPT_SSL_VERIFYPEER => $e['verificar'], CURLOPT_SSL_VERIFYHOST => $e['verificar'] ? 2 : 0,
                CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$buf, $k, $maxBytes) {
                    $buf[$k] .= $d;
                    return strlen($buf[$k]) > $maxBytes ? 0 : strlen($d);
                },
            ]);
            curl_multi_add_handle($mh, $ch);
            $hs[$k] = $ch;
        }
        unset($e);
        if ($hs) {
            $run = 0;
            do {
                $st = curl_multi_exec($mh, $run);
                if ($run > 0) curl_multi_select($mh, 0.5);
            } while ($run > 0 && $st === CURLM_OK);
        }
        $resultado = []; // com curl_multi o código de erro vem daqui, não de curl_errno()
        while ($info = curl_multi_info_read($mh)) {
            if ($info['msg'] === CURLMSG_DONE) $resultado[spl_object_id($info['handle'])] = (int)$info['result'];
        }
        foreach ($hs as $k => $ch) {
            $errno = $resultado[spl_object_id($ch)] ?? curl_errno($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $loc = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $est[$k]['tempo'] += (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $e = &$est[$k];
            if ($errno !== 0 && $errno !== 23 && $e['verificar'] && in_array($errno, [51, 58, 60, 83], true)) {
                $e['ssl_invalido'] = true; $e['verificar'] = false; // repete sem validar para ainda analisar a página
            } elseif (($errno !== 0 && $errno !== 23) || $code === 0) {
                if (++$e['tent'] >= 2) { $e['fim'] = true; $e['status'] = 'fora'; }
            } elseif (in_array($code, [301, 302, 303, 307, 308], true)) {
                if ($loc === '') { $e['fim'] = true; $e['status'] = 'inconclusivo'; }
                elseif (++$e['hops'] > 5) { $e['fim'] = true; $e['status'] = 'fora'; } // loop de redirecionamento
                else { $e['url'] = urlAbsoluta($e['url'], $loc); $e['final'] = $e['url']; }
            } elseif ($code >= 200 && $code < 300) {
                $e['fim'] = true; $e['code'] = $code; $e['html'] = $buf[$k];
                $e['status'] = $buf[$k] === '' ? 'inconclusivo' : 'ok';
            } elseif ($code === 404 || $code === 410) {
                $e['fim'] = true; $e['code'] = $code; $e['status'] = 'fora';
            } elseif ($code >= 500 && !in_array($code, [503], true)) {
                if (++$e['tent'] >= 2) { $e['fim'] = true; $e['code'] = $code; $e['status'] = 'fora'; }
            } else { // 401, 403, 429, 503 e outros: provável bloqueio do robô, não prova nada sobre o site
                if (++$e['tent'] >= 2) { $e['fim'] = true; $e['code'] = $code; $e['status'] = 'bloqueado'; }
            }
            unset($e);
        }
        curl_multi_close($mh);
        if (!array_filter($est, fn($x) => !$x['fim'])) break;
    }
    $out = [];
    foreach ($est as $k => $e) {
        $out[$k] = ['status' => $e['status'], 'code' => $e['code'], 'final' => $e['final'], 'tempo' => $e['tempo'],
            'html' => $e['html'], 'ssl_invalido' => $e['ssl_invalido']];
    }
    return $out;
}

// ---------- avaliação ----------

// Retorna ['tipo' => 'inconclusivo'|'ok'|'ruim', 'motivos' => [], 'prob' => int]
function avaliarSite(array $r, ?int $anoAtual = null): array {
    $anoAtual ??= (int)date('Y');
    if (in_array($r['status'], ['bloqueado', 'inconclusivo', 'invalido'], true)) return ['tipo' => 'inconclusivo', 'motivos' => [], 'prob' => 0];
    if ($r['status'] === 'fora') return ['tipo' => 'ruim', 'motivos' => ['site não abriu'], 'prob' => 4];
    $m = []; $prob = 0; $html = (string)$r['html'];
    if (!empty($r['ssl_invalido'])) { $m[] = 'certificado inválido'; $prob += 3; }
    elseif (!str_starts_with((string)$r['final'], 'https://')) { $m[] = 'sem HTTPS'; $prob += 2; }
    if (!temViewport($html)) { $m[] = 'não adaptado ao celular'; $prob += 2; }
    if ((float)$r['tempo'] > 4) { $m[] = 'carrega devagar'; $prob += 1; }
    if (!temWhatsApp($html)) { $m[] = 'sem botão de WhatsApp'; $prob += 1; }
    $ano = anoRodape($html);
    if ($ano !== null && $ano < $anoAtual - 3) { $m[] = 'rodapé desatualizado'; $prob += 1; }
    return ['tipo' => $prob >= 2 ? 'ruim' : 'ok', 'motivos' => $m, 'prob' => $prob];
}

// Primeira triagem sem rede. Retorna ['desc' => motivo] | ['perfil','motivos','score'] | ['checar' => url, 'score' => int]
// ---------- análise de um site (relatório para mostrar ao dono) ----------

function normalizarUrl(string $u): ?string {
    $u = trim($u);
    if ($u === '' || preg_match('/\s/', $u)) return null;
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    $p = parse_url($u);
    if (!$p || empty($p['host']) || !str_contains($p['host'], '.') || mb_strlen($u) > 300) return null;
    return $u;
}

// Analisa o site de forma segura (mesma proteção SSRF da triagem) e devolve a avaliação.
function analisarSite(string $urlEntrada, array $op = []): array {
    $u = normalizarUrl($urlEntrada);
    if ($u === null) return ['erro' => 'Endereço inválido. Digite algo como www.empresa.com.br'];
    $p = parse_url($u);
    $base = strtolower($p['scheme']) . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $r = checarSites([0 => $u, 1 => $base . '/sitemap.xml'], $op + ['prazo' => 25]);
    return avaliarPagina($r[0], $r[1] ?? null, $u);
}

function achado(string $sev, string $titulo, string $detalhe, string $impacto): array {
    return ['sev' => $sev, 'titulo' => $titulo, 'detalhe' => $detalhe, 'impacto' => $impacto];
}

// Parte pura (testável sem rede): recebe o resultado do download da página e do sitemap.
function avaliarPagina(array $r, ?array $sm, string $urlPedida, ?int $anoAtual = null): array {
    $anoAtual ??= (int)date('Y');
    $host = (string)parse_url($urlPedida, PHP_URL_HOST);
    $base = ['url' => $urlPedida, 'host' => $host, 'achados' => [], 'bons' => [], 'obs' => [], 'avisos' => [], 'tempo' => (float)$r['tempo'], 'quando' => date('d/m/Y H:i')];
    if ($r['status'] === 'fora') return $base + ['erro' => 'O site não abriu quando tentei acessar. Se ele realmente está fora do ar, esse já é o principal ponto para mostrar ao dono.'];
    if ($r['status'] === 'bloqueado') return $base + ['erro' => 'O site bloqueia análise automática (resposta ' . (int)$r['code'] . '). Não dá para concluir nada por aqui.'];
    if (in_array($r['status'], ['invalido', 'inconclusivo'], true)) return $base + ['erro' => 'Não consegui ler o site (endereço inválido ou página vazia).'];

    $html = (string)$r['html'];
    if (!mb_check_encoding($html, 'UTF-8')) $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
    $A = []; $B = [];
    $final = (string)$r['final'];

    // segurança
    if (!empty($r['ssl_invalido'])) {
        $A[] = achado('critico', 'Certificado de segurança inválido ou vencido', 'O navegador não confia no certificado do site.', 'O visitante vê um aviso de "site não seguro" antes de entrar e muita gente desiste ali.');
    } elseif (!str_starts_with($final, 'https://')) {
        $A[] = achado('critico', 'Site sem HTTPS', 'O endereço abre em http, sem criptografia.', 'O navegador marca o site como "não seguro" e o Google dá preferência a sites seguros.');
    } else {
        $B[] = 'O site usa HTTPS (conexão segura).';
        if (preg_match_all('/<(?:img|script|iframe|link)\b[^>]*\b(?:src|href)=["\']http:\/\//i', $html, $mm) && count($mm[0]) > 0) {
            $A[] = achado('atencao', 'Elementos carregados sem segurança', count($mm[0]) . ' recurso(s) da página usam http em vez de https.', 'Pode fazer o navegador mostrar o cadeado com alerta ou bloquear partes da página.');
        }
    }
    // celular
    if (!temViewport($html)) $A[] = achado('critico', 'Site não adaptado ao celular', 'Não encontrei a configuração de tela para celular na página.', 'A maioria dos clientes acessa pelo celular. Sem adaptação, o texto fica minúsculo e o visitante sai.');
    else $B[] = 'O site declara adaptação ao celular.';
    // velocidade (tempo para baixar a página inicial, medido do nosso servidor)
    $t = (float)$r['tempo'];
    if ($t > 6) $A[] = achado('critico', 'Site muito lento para abrir', 'A página inicial levou ' . number_format($t, 1, ',', '') . ' s para responder.', 'Visitantes costumam desistir de sites que demoram. Lentidão também prejudica a posição no Google.');
    elseif ($t > 3) $A[] = achado('importante', 'Site lento para abrir', 'A página inicial levou ' . number_format($t, 1, ',', '') . ' s para responder.', 'Quanto mais demora, maior a chance de o visitante desistir.');
    else $B[] = 'A página inicial respondeu rápido (' . number_format($t, 1, ',', '') . ' s).';
    // contato
    $zap = temWhatsApp($html); $tel = (bool)preg_match('/href=["\']tel:/i', $html); $mail = (bool)preg_match('/href=["\']mailto:/i', $html); $form = (bool)preg_match('/<form\b/i', $html);
    if (!$zap && !$tel && !$mail && !$form) $A[] = achado('critico', 'Sem forma clara de contato na página inicial', 'Não encontrei WhatsApp, telefone clicável, e-mail nem formulário.', 'Quem quer comprar não sabe como falar com a empresa e acaba procurando o concorrente.');
    else {
        if (!$zap) $A[] = achado('importante', 'Sem botão de WhatsApp', 'Não encontrei link ou botão de WhatsApp.', 'O WhatsApp é o canal mais usado para pedir orçamento. Sem ele, cada contato exige mais esforço do cliente.');
        else $B[] = 'Há WhatsApp na página.';
        if (!$tel) $A[] = achado('atencao', 'Telefone não clicável', 'Não encontrei um link de telefone para ligar com um toque.', 'No celular, o cliente precisaria copiar e colar o número para ligar.');
    }
    // buscas (SEO)
    $title = null; if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $mt)) $title = trim(html_entity_decode(preg_replace('/\s+/', ' ', strip_tags($mt[1])), ENT_QUOTES, 'UTF-8'));
    if ($title === null || $title === '') $A[] = achado('importante', 'Página sem título', 'Não encontrei o título da página (aparece na aba e no Google).', 'O título é o que aparece como link azul nas buscas. Sem ele, o Google escolhe um texto qualquer.');
    elseif (mb_strlen($title) < 12 || preg_match('/^(home|inicio|início|página inicial|pagina inicial|document|untitled|site)$/iu', $title)) $A[] = achado('atencao', 'Título genérico', 'O título da página é "' . mb_substr($title, 0, 60) . '".', 'Um título que diga o que a empresa faz e onde ajuda a aparecer nas buscas certas.');
    elseif (mb_strlen($title) > 65) $A[] = achado('atencao', 'Título longo demais', 'O título tem ' . mb_strlen($title) . ' caracteres.', 'O Google corta títulos longos nos resultados.');
    else $B[] = 'A página tem um título adequado.';
    if (!preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\'][^"\']{20,}/is', $html) && !preg_match('/<meta[^>]+content=["\'][^"\']{20,}["\'][^>]*name=["\']description["\']/is', $html)) $A[] = achado('importante', 'Sem descrição para o Google', 'Não encontrei a descrição da página (texto que aparece abaixo do título nas buscas).', 'Sem descrição, o Google monta um trecho aleatório e o resultado fica menos atrativo.');
    else $B[] = 'A página tem descrição para o Google.';
    if (!preg_match('/<h1\b/i', $html)) $A[] = achado('atencao', 'Sem título principal (H1) visível', 'Não encontrei um título principal no texto da página.', 'O título principal ajuda o Google e o visitante a entender do que se trata a página.');
    if (preg_match_all('/<img\b[^>]*>/i', $html, $im) && count($im[0]) >= 3) {
        $sem = 0; foreach ($im[0] as $tag) if (!preg_match('/\balt\s*=/i', $tag)) $sem++;
        if ($sem >= 3) $A[] = achado('atencao', 'Imagens sem descrição', $sem . ' de ' . count($im[0]) . ' imagens não têm texto alternativo.', 'O Google não entende imagens sem descrição, e leitores de tela para deficientes visuais ficam sem informação.');
    }
    if (!preg_match('/property=["\']og:image["\']/i', $html)) $A[] = achado('atencao', 'Link compartilhado sem foto e título', 'Não encontrei as marcações de compartilhamento (Open Graph).', 'Ao enviar o link no WhatsApp ou Instagram, não aparece imagem nem título, e o link parece menos confiável.');
    else $B[] = 'O link tem prévia para compartilhar (foto e título).';
    if ($sm !== null && $sm['status'] === 'ok' && preg_match('/<urlset|<sitemapindex/i', (string)$sm['html'])) $B[] = 'O site tem sitemap (mapa para o Google).';
    else $A[] = achado('atencao', 'Sem mapa do site (sitemap.xml)', 'Não encontrei o arquivo sitemap.xml.', 'O sitemap ajuda o Google a encontrar todas as páginas do site.');
    // confiança
    $ano = anoRodape($html);
    if ($ano !== null && $ano < $anoAtual - 1) $A[] = achado($ano < $anoAtual - 3 ? 'importante' : 'atencao', 'Rodapé com ano antigo', 'O rodapé mostra © ' . $ano . '.', 'Passa a impressão de site abandonado, mesmo que a empresa esteja ativa.');
    if (preg_match('/lorem ipsum|em constru[cç][aã]o|coming soon|site em breve/i', strip_tags($html))) $A[] = achado('critico', 'Texto de exemplo ou "em construção"', 'Encontrei texto provisório na página.', 'Passa a impressão de empresa sem cuidado ou site inacabado.');
    if (!preg_match('/gtag\(|googletagmanager\.com|google-analytics\.com|fbq\(|connect\.facebook\.net|clarity\.ms|plausible\.io|matomo|hotjar/i', $html)) $A[] = achado('atencao', 'Sem medição de visitas no código', 'Não encontrei Google Analytics, Tag Manager ou pixel.', 'Sem medir, o dono não sabe quantas pessoas visitam o site nem de onde vêm.');
    // observações informativas (não são defeitos)
    foreach (['wixstatic.com|wix\.com' => 'Wix', '\/wp-content\/|wp-json' => 'WordPress', 'webnode' => 'Webnode', 'sites\.google\.com' => 'Google Sites', 'squarespace' => 'Squarespace', 'cdn\.shopify' => 'Shopify', 'blogspot|blogger\.com' => 'Blogger'] as $re => $nome) {
        if (preg_match('/' . $re . '/i', $html)) { $base['obs'][] = 'O site parece ter sido feito em ' . $nome . ' (informação, não é defeito).'; break; }
    }
    // limitações
    $texto = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html))));
    if (mb_strlen($texto) < 300 && preg_match_all('/<script\b/i', $html) >= 2) $base['avisos'][] = 'A página parece ser montada por JavaScript. Alguns itens podem existir no site mesmo sem aparecer nesta análise.';
    $base['avisos'][] = 'Análise automática do código da página inicial. A velocidade foi medida do nosso servidor e pode variar.';

    $ordem = ['critico' => 0, 'importante' => 1, 'atencao' => 2];
    usort($A, fn($x, $y) => $ordem[$x['sev']] <=> $ordem[$y['sev']]);
    return ['achados' => $A, 'bons' => $B] + $base;
}

// Texto curto para mandar ao dono pelo WhatsApp (3 pontos mais fortes).
function resumoAnalise(array $a, string $empresa, string $nome, string $agencia): string {
    $pts = [];
    foreach (array_slice($a['achados'] ?? [], 0, 3) as $x) $pts[] = '- ' . $x['titulo'] . ': ' . $x['impacto'];
    if (!$pts) return 'Analisei o site' . ($empresa !== '' ? ' da ' . $empresa : '') . ' (' . ($a['host'] ?? '') . ') e não encontrei problemas relevantes nos itens que verifico. Se quiser, posso mostrar outros pontos de melhoria.';
    return 'Analisei o site' . ($empresa !== '' ? ' da ' . $empresa : '') . ' (' . ($a['host'] ?? '') . ') e separei os pontos principais:' . "\n" . implode("\n", $pts)
        . "\n\nTenho o relatório completo com o que encontrei e como corrigir. Posso te enviar? " . $nome . ', ' . $agencia . '.';
}

function triagem(array $p, int $minAvaliacoes, array $social): array {
    $tel = trim((string)($p['phoneNumber'] ?? ''));
    $site = trim((string)($p['website'] ?? ''));
    $n = (int)($p['ratingCount'] ?? 0);
    $nota = (float)($p['rating'] ?? 0);
    if ($tel === '') return ['desc' => 'sem telefone'];
    if ($n < $minAvaliacoes) return ['desc' => 'poucas avaliacoes'];
    $score = ($n >= 100 ? 3 : ($n >= 40 ? 2 : 1)) + ($nota >= 4.3 ? 1 : 0);
    if ($site === '') return ['perfil' => 'sem_site', 'motivos' => ['sem site no Google'], 'score' => $score + 5];
    if (!preg_match('~^https?://~i', $site)) $site = 'http://' . $site;
    $host = strtolower(preg_replace('/^www\./', '', (string)(parse_url($site, PHP_URL_HOST) ?: '')));
    foreach ($social as $s) {
        if ($host === $s || str_ends_with($host, '.' . $s)) return ['perfil' => 'so_rede', 'motivos' => ['o link do Google leva só para rede social'], 'score' => $score + 4];
    }
    return ['checar' => $site, 'score' => $score];
}

function chavePlace(array $p): string {
    return substr((string)($p['placeId'] ?? $p['cid'] ?? md5(($p['title'] ?? '') . ($p['address'] ?? ''))), 0, 120);
}

function csvCelula($v): string {
    $s = (string)$v;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) $s = "'" . $s; // evita fórmula no Excel
    return '"' . str_replace('"', '""', $s) . '"';
}
