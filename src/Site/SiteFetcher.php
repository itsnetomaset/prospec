<?php

declare(strict_types=1);

namespace Prospec\Site;

use CurlHandle;

/**
 * Fetches many websites in parallel with curl_multi, safely:
 * - redirects are followed manually (max. 5) so every hop goes through the SSRF guard;
 * - only http/https on standard ports, only public IPs (the resolved IP is pinned with CURLOPT_RESOLVE);
 * - body capped at 600 KB, two attempts on network failures, global deadline.
 */
final class SiteFetcher implements SiteChecker
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
    private const MAX_BYTES = 600000;
    private const MAX_REDIRECTS = 5;
    private const MAX_ROUNDS = 14;
    private const ALLOWED_PORTS = [80, 443, 8080, 8443];
    private const SSL_ERRORS = [51, 58, 60, 83];
    private const CURLE_WRITE_ERROR = 23; // raised when we stop reading at MAX_BYTES; the body is still usable

    public function __construct(
        private readonly NetworkGuard $guard,
        private readonly bool $verifySsl = true,
    ) {
    }

    /**
     * Checks whether this server can validate TLS certificates at all, so a broken CA bundle
     * on the host is not reported as "invalid certificate" for every prospect.
     */
    public static function serverTrustsCertificates(string $probeUrl = 'https://www.google.com/'): bool
    {
        $ch = curl_init($probeUrl);
        curl_setopt_array($ch, [CURLOPT_NOBODY => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        return !in_array($errno, [51, 58, 60, 77, 83], true);
    }

    public static function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('~^https?://~i', $location)) {
            return $location;
        }
        $p = parse_url($base);
        $scheme = $p['scheme'] ?? 'http';
        $origin = $scheme . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $dir = (string)preg_replace('~[^/]*$~', '', $p['path'] ?? '/');
        return $origin . ($dir === '' ? '/' : $dir) . $location;
    }

    public function check(array $urls, float $deadlineSeconds = 60.0): array
    {
        $deadline = microtime(true) + $deadlineSeconds;
        $state = [];
        foreach ($urls as $key => $url) {
            $state[$key] = ['url' => $url, 'final' => $url, 'hops' => 0, 'attempts' => 0, 'verify' => $this->verifySsl,
                'invalidCert' => false, 'elapsed' => 0.0, 'done' => false, 'status' => FetchResult::INCONCLUSIVE, 'code' => 0, 'html' => ''];
        }

        for ($round = 0; $round < self::MAX_ROUNDS && microtime(true) <= $deadline; $round++) {
            $multi = curl_multi_init();
            $handles = [];
            $buffers = [];
            foreach ($state as $key => &$s) {
                if ($s['done']) {
                    continue;
                }
                $handle = $this->prepare($s, $key, $buffers);
                if ($handle !== null) {
                    curl_multi_add_handle($multi, $handle);
                    $handles[$key] = $handle;
                }
            }
            unset($s);

            if ($handles) {
                $running = 0;
                do {
                    $status = curl_multi_exec($multi, $running);
                    if ($running > 0) {
                        curl_multi_select($multi, 0.5);
                    }
                } while ($running > 0 && $status === CURLM_OK);
            }
            $errors = []; // with curl_multi the error code comes from here, not from curl_errno()
            while ($info = curl_multi_info_read($multi)) {
                if ($info['msg'] === CURLMSG_DONE) {
                    $errors[spl_object_id($info['handle'])] = (int)$info['result'];
                }
            }
            foreach ($handles as $key => $handle) {
                $errno = $errors[spl_object_id($handle)] ?? curl_errno($handle);
                $code = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $location = (string)curl_getinfo($handle, CURLINFO_REDIRECT_URL);
                $state[$key]['elapsed'] += (float)curl_getinfo($handle, CURLINFO_TOTAL_TIME);
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
                $this->handleResponse($state[$key], $errno, $code, $location, $buffers[$key]);
            }
            curl_multi_close($multi);
            if (!array_filter($state, fn (array $s): bool => !$s['done'])) {
                break;
            }
        }

        $results = [];
        foreach ($state as $key => $s) {
            $results[$key] = new FetchResult($s['status'], $s['code'], $s['final'], $s['elapsed'], $s['html'], $s['invalidCert']);
        }
        return $results;
    }

    /**
     * @param array<string, mixed> $s
     * @param array<array-key, string> $buffers
     */
    private function prepare(array &$s, int|string $key, array &$buffers): ?CurlHandle
    {
        $p = parse_url($s['url']);
        $scheme = strtolower((string)($p['scheme'] ?? ''));
        $host = (string)($p['host'] ?? '');
        $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || preg_match('/[^\x21-\x7e]/', $host)
            || !in_array($port, self::ALLOWED_PORTS, true)) {
            $s['done'] = true;
            $s['status'] = FetchResult::INVALID;
            return null;
        }
        $resolved = $this->guard->resolve($host);
        if ($resolved['reason'] === 'private') {
            $s['done'] = true;
            $s['status'] = FetchResult::INVALID;
            return null;
        }
        if ($resolved['reason'] === 'dns') {
            if (++$s['attempts'] >= 2) {
                $s['done'] = true;
                $s['status'] = FetchResult::DOWN;
            }
            return null;
        }

        $buffers[$key] = '';
        $ch = curl_init($s['url']);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => self::USER_AGENT, CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8', 'Accept-Language: pt-BR,pt;q=0.9'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $resolved['ip']],
            CURLOPT_SSL_VERIFYPEER => $s['verify'], CURLOPT_SSL_VERIFYHOST => $s['verify'] ? 2 : 0,
            CURLOPT_WRITEFUNCTION => function ($handle, string $data) use (&$buffers, $key): int {
                $buffers[$key] .= $data;
                return strlen($buffers[$key]) > self::MAX_BYTES ? 0 : strlen($data);
            },
        ]);
        return $ch;
    }

    /**
     * @param array<string, mixed> $s
     */
    private function handleResponse(array &$s, int $errno, int $code, string $location, string $body): void
    {
        $failed = $errno !== 0 && $errno !== self::CURLE_WRITE_ERROR;
        if ($failed && $s['verify'] && in_array($errno, self::SSL_ERRORS, true)) {
            // Record the bad certificate, then retry without verification so the page can still be audited.
            $s['invalidCert'] = true;
            $s['verify'] = false;
        } elseif ($failed || $code === 0) {
            $this->retryOrFinish($s, FetchResult::DOWN);
        } elseif (in_array($code, [301, 302, 303, 307, 308], true)) {
            if ($location === '') {
                $this->finish($s, FetchResult::INCONCLUSIVE);
            } elseif (++$s['hops'] > self::MAX_REDIRECTS) {
                $this->finish($s, FetchResult::DOWN); // redirect loop
            } else {
                $s['url'] = self::absoluteUrl($s['url'], $location);
                $s['final'] = $s['url'];
            }
        } elseif ($code >= 200 && $code < 300) {
            $s['code'] = $code;
            $s['html'] = $body;
            $this->finish($s, $body === '' ? FetchResult::INCONCLUSIVE : FetchResult::OK);
        } elseif ($code === 404 || $code === 410) {
            $s['code'] = $code;
            $this->finish($s, FetchResult::DOWN);
        } elseif ($code >= 500 && $code !== 503) {
            $this->retryOrFinish($s, FetchResult::DOWN, $code);
        } else {
            // 401, 403, 429, 503 and others: most likely bot protection, proves nothing about the site.
            $this->retryOrFinish($s, FetchResult::BLOCKED, $code);
        }
    }

    /**
     * @param array<string, mixed> $s
     */
    private function retryOrFinish(array &$s, string $status, ?int $code = null): void
    {
        if (++$s['attempts'] >= 2) {
            if ($code !== null) {
                $s['code'] = $code;
            }
            $this->finish($s, $status);
        }
    }

    /**
     * @param array<string, mixed> $s
     */
    private function finish(array &$s, string $status): void
    {
        $s['done'] = true;
        $s['status'] = $status;
    }
}
