<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use Prospec\Geo\CityDirectory;

final class CityDirectoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/prospec-cities-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testSuggestsPrefixMatchesFirst(): void
    {
        $cities = [['Preto', 'XX', 'preto'], ['Ribeirão Preto', 'SP', 'ribeirao preto'], ['Ribeirão Pires', 'SP', 'ribeirao pires']];
        // Padding makes the cache look complete (the directory ignores tiny cache files).
        for ($i = 0; $i < 60; $i++) {
            $cities[] = ['Cidade ' . $i, 'MG', 'cidade ' . $i];
        }
        file_put_contents($this->dir . '/cities.json', json_encode($cities, JSON_UNESCAPED_UNICODE));
        $directory = new CityDirectory($this->dir . '/cities.json', fn (): array => []);

        self::assertSame(['Ribeirão Preto, SP', 'Ribeirão Pires, SP'], $directory->suggest('RIBEIRÃO'));
        self::assertSame(['Preto, XX', 'Ribeirão Preto, SP'], $directory->suggest('preto, sp'));
        self::assertSame([], $directory->suggest('r'));
    }

    public function testFallsBackToSearchedCitiesWhenIbgeIsUnavailable(): void
    {
        touch($this->dir . '/cities.json.failed'); // recent failure: no download attempt
        $directory = new CityDirectory($this->dir . '/cities.json', fn (): array => ['Leme, SP', 'Pirassununga, SP']);

        self::assertSame(['Leme, SP'], $directory->suggest('lem'));
    }

    public function testNormalizesAccents(): void
    {
        self::assertSame('sao joao da boa vista', CityDirectory::normalize('São João da Boa Vista'));
    }
}
