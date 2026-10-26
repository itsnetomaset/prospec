<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Prospec\Controller\SearchController;

final class SearchControllerTest extends TestCase
{
    public function testNormalizesTypedCity(): void
    {
        self::assertSame('Ribeirão Preto, SP', SearchController::normalizeCity(' ribeirão preto, sp '));
        self::assertSame('São Paulo - SP', SearchController::normalizeCity('SÃO PAULO - sp'));
        self::assertSame('Leme', SearchController::normalizeCity('leme'));
    }
}
