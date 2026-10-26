<?php

declare(strict_types=1);

namespace Prospec\Tests\Support;

use Prospec\Support\Clock;

final class FixedClock implements Clock
{
    public function __construct(private int $now)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
