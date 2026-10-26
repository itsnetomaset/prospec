<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;
use Prospec\Site\HtmlInspector;

final class HtmlInspectorTest extends TestCase
{
    public function testFindsTheMostRecentCopyrightYear(): void
    {
        self::assertSame(2025, HtmlInspector::copyrightYear('<footer>© 2015-2025 Empresa</footer>'));
        self::assertSame(2019, HtmlInspector::copyrightYear('<p>Copyright 2019</p>'));
        self::assertSame(2021, HtmlInspector::copyrightYear('<p>&copy; 2021</p>'));
        self::assertNull(HtmlInspector::copyrightYear('<p>Fundada em 1998</p>'));
    }

    public function testHandlesLatin1Pages(): void
    {
        self::assertSame(2018, HtmlInspector::copyrightYear("<p>\xA9 2018 Empresa</p>"));
    }

    public function testDetectsViewportAndWhatsApp(): void
    {
        self::assertTrue(HtmlInspector::hasViewport('<meta name="viewport" content="width=device-width">'));
        self::assertFalse(HtmlInspector::hasViewport('<meta name="description" content="x">'));
        self::assertTrue(HtmlInspector::hasWhatsApp('<a href="https://wa.me/5516">'));
        self::assertFalse(HtmlInspector::hasWhatsApp('<a href="tel:16">'));
    }
}
