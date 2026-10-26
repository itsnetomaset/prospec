<?php

declare(strict_types=1);

namespace Prospec\Site;

/**
 * Full website audit shown to the salesperson and shared with the business owner.
 * Report copy is in Portuguese because it is read by Brazilian business owners.
 */
final class SiteAuditor
{
    public const CRITICAL = 'critico';
    public const IMPORTANT = 'importante';
    public const ATTENTION = 'atencao';

    private const SEVERITY_ORDER = [self::CRITICAL => 0, self::IMPORTANT => 1, self::ATTENTION => 2];

    private const BUILDERS = [
        'wixstatic.com|wix\.com' => 'Wix',
        '\/wp-content\/|wp-json' => 'WordPress',
        'webnode' => 'Webnode',
        'sites\.google\.com' => 'Google Sites',
        'squarespace' => 'Squarespace',
        'cdn\.shopify' => 'Shopify',
        'blogspot|blogger\.com' => 'Blogger',
    ];

    public function __construct(private readonly SiteChecker $checker)
    {
    }

    public static function normalizeUrl(string $input): ?string
    {
        $url = trim($input);
        if ($url === '' || preg_match('/\s/', $url)) {
            return null;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $p = parse_url($url);
        if (!$p || empty($p['host']) || !str_contains($p['host'], '.') || mb_strlen($url) > 300) {
            return null;
        }
        return $url;
    }

    /**
     * Downloads the home page and sitemap (with the same SSRF protection used during qualification) and audits them.
     *
     * @return array<string, mixed>
     */
    public function audit(string $input): array
    {
        $url = self::normalizeUrl($input);
        if ($url === null) {
            return ['error' => 'Endereço inválido. Digite algo como www.empresa.com.br'];
        }
        $p = parse_url($url);
        $origin = strtolower((string)$p['scheme']) . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $results = $this->checker->check([0 => $url, 1 => $origin . '/sitemap.xml'], 25);
        return self::evaluate($results[0], $results[1] ?? null, $url);
    }

    /**
     * Pure part of the audit (no network), so it can be unit tested.
     *
     * @return array{url: string, host: string, findings: list<array{severity: string, title: string, detail: string, impact: string}>,
     *               good: list<string>, notes: list<string>, warnings: list<string>, elapsed: float, checkedAt: string, error?: string}
     */
    public static function evaluate(FetchResult $page, ?FetchResult $sitemap, string $url, ?int $currentYear = null): array
    {
        $currentYear ??= (int)date('Y');
        $report = ['url' => $url, 'host' => (string)parse_url($url, PHP_URL_HOST), 'findings' => [], 'good' => [], 'notes' => [], 'warnings' => [],
            'elapsed' => $page->elapsed, 'checkedAt' => date('d/m/Y H:i')];
        if ($page->status === FetchResult::DOWN) {
            return $report + ['error' => 'O site não abriu quando tentei acessar. Se ele realmente está fora do ar, esse já é o principal ponto para mostrar ao dono.'];
        }
        if ($page->status === FetchResult::BLOCKED) {
            return $report + ['error' => 'O site bloqueia análise automática (resposta ' . $page->httpCode . '). Não dá para concluir nada por aqui.'];
        }
        if (in_array($page->status, [FetchResult::INVALID, FetchResult::INCONCLUSIVE], true)) {
            return $report + ['error' => 'Não consegui ler o site (endereço inválido ou página vazia).'];
        }

        $html = HtmlInspector::toUtf8($page->html);
        $findings = [];
        $good = [];

        self::checkSecurity($page, $html, $findings, $good);
        self::checkMobile($html, $findings, $good);
        self::checkSpeed($page->elapsed, $findings, $good);
        self::checkContact($html, $findings, $good);
        self::checkSearchVisibility($html, $sitemap, $findings, $good);
        self::checkTrust($html, $currentYear, $findings);

        foreach (self::BUILDERS as $pattern => $name) {
            if (preg_match('/' . $pattern . '/i', $html)) {
                $report['notes'][] = 'O site parece ter sido feito em ' . $name . ' (informação, não é defeito).';
                break;
            }
        }
        $text = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html))));
        if (mb_strlen($text) < 300 && preg_match_all('/<script\b/i', $html) >= 2) {
            $report['warnings'][] = 'A página parece ser montada por JavaScript. Alguns itens podem existir no site mesmo sem aparecer nesta análise.';
        }
        $report['warnings'][] = 'Análise automática do código da página inicial. A velocidade foi medida do nosso servidor e pode variar.';

        usort($findings, fn (array $a, array $b): int => self::SEVERITY_ORDER[$a['severity']] <=> self::SEVERITY_ORDER[$b['severity']]);
        return ['findings' => $findings, 'good' => $good] + $report;
    }

    /**
     * @return array{severity: string, title: string, detail: string, impact: string}
     */
    private static function finding(string $severity, string $title, string $detail, string $impact): array
    {
        return ['severity' => $severity, 'title' => $title, 'detail' => $detail, 'impact' => $impact];
    }

    private static function checkSecurity(FetchResult $page, string $html, array &$findings, array &$good): void
    {
        if ($page->invalidCertificate) {
            $findings[] = self::finding(self::CRITICAL, 'Certificado de segurança inválido ou vencido', 'O navegador não confia no certificado do site.', 'O visitante vê um aviso de "site não seguro" antes de entrar e muita gente desiste ali.');
        } elseif (!str_starts_with($page->finalUrl, 'https://')) {
            $findings[] = self::finding(self::CRITICAL, 'Site sem HTTPS', 'O endereço abre em http, sem criptografia.', 'O navegador marca o site como "não seguro" e o Google dá preferência a sites seguros.');
        } else {
            $good[] = 'O site usa HTTPS (conexão segura).';
            if (preg_match_all('/<(?:img|script|iframe|link)\b[^>]*\b(?:src|href)=["\']http:\/\//i', $html, $m) && count($m[0]) > 0) {
                $findings[] = self::finding(self::ATTENTION, 'Elementos carregados sem segurança', count($m[0]) . ' recurso(s) da página usam http em vez de https.', 'Pode fazer o navegador mostrar o cadeado com alerta ou bloquear partes da página.');
            }
        }
    }

    private static function checkMobile(string $html, array &$findings, array &$good): void
    {
        if (!HtmlInspector::hasViewport($html)) {
            $findings[] = self::finding(self::CRITICAL, 'Site não adaptado ao celular', 'Não encontrei a configuração de tela para celular na página.', 'A maioria dos clientes acessa pelo celular. Sem adaptação, o texto fica minúsculo e o visitante sai.');
        } else {
            $good[] = 'O site declara adaptação ao celular.';
        }
    }

    /** Time to download the home page, measured from our server. */
    private static function checkSpeed(float $seconds, array &$findings, array &$good): void
    {
        $shown = number_format($seconds, 1, ',', '');
        if ($seconds > 6) {
            $findings[] = self::finding(self::CRITICAL, 'Site muito lento para abrir', 'A página inicial levou ' . $shown . ' s para responder.', 'Visitantes costumam desistir de sites que demoram. Lentidão também prejudica a posição no Google.');
        } elseif ($seconds > 3) {
            $findings[] = self::finding(self::IMPORTANT, 'Site lento para abrir', 'A página inicial levou ' . $shown . ' s para responder.', 'Quanto mais demora, maior a chance de o visitante desistir.');
        } else {
            $good[] = 'A página inicial respondeu rápido (' . $shown . ' s).';
        }
    }

    private static function checkContact(string $html, array &$findings, array &$good): void
    {
        $whatsApp = HtmlInspector::hasWhatsApp($html);
        $phone = (bool)preg_match('/href=["\']tel:/i', $html);
        $email = (bool)preg_match('/href=["\']mailto:/i', $html);
        $form = (bool)preg_match('/<form\b/i', $html);
        if (!$whatsApp && !$phone && !$email && !$form) {
            $findings[] = self::finding(self::CRITICAL, 'Sem forma clara de contato na página inicial', 'Não encontrei WhatsApp, telefone clicável, e-mail nem formulário.', 'Quem quer comprar não sabe como falar com a empresa e acaba procurando o concorrente.');
            return;
        }
        if (!$whatsApp) {
            $findings[] = self::finding(self::IMPORTANT, 'Sem botão de WhatsApp', 'Não encontrei link ou botão de WhatsApp.', 'O WhatsApp é o canal mais usado para pedir orçamento. Sem ele, cada contato exige mais esforço do cliente.');
        } else {
            $good[] = 'Há WhatsApp na página.';
        }
        if (!$phone) {
            $findings[] = self::finding(self::ATTENTION, 'Telefone não clicável', 'Não encontrei um link de telefone para ligar com um toque.', 'No celular, o cliente precisaria copiar e colar o número para ligar.');
        }
    }

    private static function checkSearchVisibility(string $html, ?FetchResult $sitemap, array &$findings, array &$good): void
    {
        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $mt)) {
            $title = trim(html_entity_decode((string)preg_replace('/\s+/', ' ', strip_tags($mt[1])), ENT_QUOTES, 'UTF-8'));
        }
        if ($title === null || $title === '') {
            $findings[] = self::finding(self::IMPORTANT, 'Página sem título', 'Não encontrei o título da página (aparece na aba e no Google).', 'O título é o que aparece como link azul nas buscas. Sem ele, o Google escolhe um texto qualquer.');
        } elseif (mb_strlen($title) < 12 || preg_match('/^(home|inicio|início|página inicial|pagina inicial|document|untitled|site)$/iu', $title)) {
            $findings[] = self::finding(self::ATTENTION, 'Título genérico', 'O título da página é "' . mb_substr($title, 0, 60) . '".', 'Um título que diga o que a empresa faz e onde ajuda a aparecer nas buscas certas.');
        } elseif (mb_strlen($title) > 65) {
            $findings[] = self::finding(self::ATTENTION, 'Título longo demais', 'O título tem ' . mb_strlen($title) . ' caracteres.', 'O Google corta títulos longos nos resultados.');
        } else {
            $good[] = 'A página tem um título adequado.';
        }

        $hasDescription = preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\'][^"\']{20,}/is', $html)
            || preg_match('/<meta[^>]+content=["\'][^"\']{20,}["\'][^>]*name=["\']description["\']/is', $html);
        if (!$hasDescription) {
            $findings[] = self::finding(self::IMPORTANT, 'Sem descrição para o Google', 'Não encontrei a descrição da página (texto que aparece abaixo do título nas buscas).', 'Sem descrição, o Google monta um trecho aleatório e o resultado fica menos atrativo.');
        } else {
            $good[] = 'A página tem descrição para o Google.';
        }

        if (!preg_match('/<h1\b/i', $html)) {
            $findings[] = self::finding(self::ATTENTION, 'Sem título principal (H1) visível', 'Não encontrei um título principal no texto da página.', 'O título principal ajuda o Google e o visitante a entender do que se trata a página.');
        }
        if (preg_match_all('/<img\b[^>]*>/i', $html, $images) && count($images[0]) >= 3) {
            $withoutAlt = 0;
            foreach ($images[0] as $tag) {
                if (!preg_match('/\balt\s*=/i', $tag)) {
                    $withoutAlt++;
                }
            }
            if ($withoutAlt >= 3) {
                $findings[] = self::finding(self::ATTENTION, 'Imagens sem descrição', $withoutAlt . ' de ' . count($images[0]) . ' imagens não têm texto alternativo.', 'O Google não entende imagens sem descrição, e leitores de tela para deficientes visuais ficam sem informação.');
            }
        }
        if (!preg_match('/property=["\']og:image["\']/i', $html)) {
            $findings[] = self::finding(self::ATTENTION, 'Link compartilhado sem foto e título', 'Não encontrei as marcações de compartilhamento (Open Graph).', 'Ao enviar o link no WhatsApp ou Instagram, não aparece imagem nem título, e o link parece menos confiável.');
        } else {
            $good[] = 'O link tem prévia para compartilhar (foto e título).';
        }
        if ($sitemap !== null && $sitemap->status === FetchResult::OK && preg_match('/<urlset|<sitemapindex/i', $sitemap->html)) {
            $good[] = 'O site tem sitemap (mapa para o Google).';
        } else {
            $findings[] = self::finding(self::ATTENTION, 'Sem mapa do site (sitemap.xml)', 'Não encontrei o arquivo sitemap.xml.', 'O sitemap ajuda o Google a encontrar todas as páginas do site.');
        }
    }

    private static function checkTrust(string $html, int $currentYear, array &$findings): void
    {
        $year = HtmlInspector::copyrightYear($html);
        if ($year !== null && $year < $currentYear - 1) {
            $findings[] = self::finding($year < $currentYear - 3 ? self::IMPORTANT : self::ATTENTION, 'Rodapé com ano antigo', 'O rodapé mostra © ' . $year . '.', 'Passa a impressão de site abandonado, mesmo que a empresa esteja ativa.');
        }
        if (preg_match('/lorem ipsum|em constru[cç][aã]o|coming soon|site em breve/i', strip_tags($html))) {
            $findings[] = self::finding(self::CRITICAL, 'Texto de exemplo ou "em construção"', 'Encontrei texto provisório na página.', 'Passa a impressão de empresa sem cuidado ou site inacabado.');
        }
        if (!preg_match('/gtag\(|googletagmanager\.com|google-analytics\.com|fbq\(|connect\.facebook\.net|clarity\.ms|plausible\.io|matomo|hotjar/i', $html)) {
            $findings[] = self::finding(self::ATTENTION, 'Sem medição de visitas no código', 'Não encontrei Google Analytics, Tag Manager ou pixel.', 'Sem medir, o dono não sabe quantas pessoas visitam o site nem de onde vêm.');
        }
    }
}
