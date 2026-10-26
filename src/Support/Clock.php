<?php

declare(strict_types=1);

namespace Prospec\Support;

interface Clock
{
    public function now(): int;
}
