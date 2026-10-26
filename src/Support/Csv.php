<?php

declare(strict_types=1);

namespace Prospec\Support;

final class Csv
{
    /**
     * Quotes a cell for a semicolon-separated CSV opened in Excel. Values starting with
     * = + - @ tab or CR are prefixed with an apostrophe to block formula injection.
     */
    public static function cell(mixed $value): string
    {
        $s = (string)$value;
        if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    /**
     * @param list<mixed> $cells
     */
    public static function row(array $cells): string
    {
        return implode(';', array_map([self::class, 'cell'], $cells)) . "\r\n";
    }
}
