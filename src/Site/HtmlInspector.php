<?php

declare(strict_types=1);

namespace Prospec\Site;

/**
 * Lightweight checks over raw HTML (no DOM parsing, so broken markup never throws).
 */
final class HtmlInspector
{
    /**
     * Most recent year found near a copyright sign ("© 2015-2025" returns 2025).
     */
    public static function copyrightYear(string $html): ?int
    {
        $html = self::toUtf8($html);
        $max = null;
        if (preg_match_all('/(?:©|&copy;|&#169;|&#xa9;|copyright)[^<]{0,80}/iu', $html, $matches)) {
            foreach ($matches[0] as $snippet) {
                if (preg_match_all('/\b(?:19|20)\d\d\b/', $snippet, $years)) {
                    foreach ($years[0] as $year) {
                        $max = max($max ?? 0, (int)$year);
                    }
                }
            }
        }
        return $max;
    }

    public static function hasWhatsApp(string $html): bool
    {
        return (bool)preg_match('/whatsapp|wa\.me/i', $html);
    }

    public static function hasViewport(string $html): bool
    {
        return (bool)preg_match('/<meta[^>]+name=["\']viewport/i', $html);
    }

    public static function toUtf8(string $html): string
    {
        return mb_check_encoding($html, 'UTF-8') ? $html : mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
    }
}
