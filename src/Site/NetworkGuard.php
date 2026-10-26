<?php

declare(strict_types=1);

namespace Prospec\Site;

/**
 * SSRF protection: only public IPv4 addresses may be fetched.
 */
final class NetworkGuard
{
    /**
     * @param bool|list<string> $allowPrivate true allows everything, or a list of allowed IPs (tests only)
     */
    public function __construct(private readonly bool|array $allowPrivate = false)
    {
    }

    public function isPublic(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        if ($this->allowPrivate === true || (is_array($this->allowPrivate) && in_array($ip, $this->allowPrivate, true))) {
            return true;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $n = ip2long($ip);
        return !($n !== false && ($n & 0xFFC00000) === 0x64400000); // 100.64.0.0/10 (carrier-grade NAT)
    }

    /**
     * Resolves a host and pins it to a public IP.
     *
     * @return array{ip: string|null, reason: null|'dns'|'private'}
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublic($host) ? ['ip' => $host, 'reason' => null] : ['ip' => null, 'reason' => 'private'];
        }
        $ips = @gethostbynamel($host);
        if (!$ips) {
            return ['ip' => null, 'reason' => 'dns'];
        }
        foreach ($ips as $ip) {
            if (!$this->isPublic($ip)) {
                return ['ip' => null, 'reason' => 'private']; // DNS rebinding: any private answer blocks the host
            }
        }
        return ['ip' => $ips[0], 'reason' => null];
    }
}
