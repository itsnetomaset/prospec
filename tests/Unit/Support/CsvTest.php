<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Prospec\Support\Csv;

final class CsvTest extends TestCase
{
    public function testQuotesCells(): void
    {
        self::assertSame('"Site ""Antigo"""', Csv::cell('Site "Antigo"'));
        self::assertSame('"4.5"', Csv::cell(4.5));
        self::assertSame('""', Csv::cell(null));
    }

    public function testNeutralizesSpreadsheetFormulas(): void
    {
        foreach (['=HYPERLINK("x")', '+55 16 9999', '-1', '@SUM(A1)', "\tx"] as $value) {
            self::assertStringStartsWith("\"'", Csv::cell($value), $value);
        }
    }

    public function testBuildsSemicolonSeparatedRows(): void
    {
        self::assertSame("\"a\";\"b\"\r\n", Csv::row(['a', 'b']));
    }
}
