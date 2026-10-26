<?php

declare(strict_types=1);

namespace Prospec\Site;

/**
 * Fast verdict used while qualifying search results: is this website weak enough to be a sales opportunity?
 */
final class QuickRating
{
    public const INCONCLUSIVE = 'inconclusive';
    public const GOOD = 'good';
    public const WEAK = 'weak';

    /**
     * @param list<string> $reasons issues found (Portuguese, stored in `leads.motivos`)
     * @param int          $points  weight of the issues; 2 or more means "weak"
     */
    private function __construct(
        public readonly string $verdict,
        public readonly array $reasons,
        public readonly int $points,
    ) {
    }

    public static function rate(FetchResult $result, ?int $currentYear = null): self
    {
        $currentYear ??= (int)date('Y');
        if (in_array($result->status, [FetchResult::BLOCKED, FetchResult::INCONCLUSIVE, FetchResult::INVALID], true)) {
            return new self(self::INCONCLUSIVE, [], 0);
        }
        if ($result->status === FetchResult::DOWN) {
            return new self(self::WEAK, ['site não abriu'], 4);
        }

        $reasons = [];
        $points = 0;
        $html = $result->html;
        if ($result->invalidCertificate) {
            $reasons[] = 'certificado inválido';
            $points += 3;
        } elseif (!str_starts_with($result->finalUrl, 'https://')) {
            $reasons[] = 'sem HTTPS';
            $points += 2;
        }
        if (!HtmlInspector::hasViewport($html)) {
            $reasons[] = 'não adaptado ao celular';
            $points += 2;
        }
        if ($result->elapsed > 4) {
            $reasons[] = 'carrega devagar';
            $points += 1;
        }
        if (!HtmlInspector::hasWhatsApp($html)) {
            $reasons[] = 'sem botão de WhatsApp';
            $points += 1;
        }
        $year = HtmlInspector::copyrightYear($html);
        if ($year !== null && $year < $currentYear - 3) {
            $reasons[] = 'rodapé desatualizado';
            $points += 1;
        }
        return new self($points >= 2 ? self::WEAK : self::GOOD, $reasons, $points);
    }
}
