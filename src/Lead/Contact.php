<?php

declare(strict_types=1);

namespace Prospec\Lead;

/**
 * The decision maker of a lead, usually passed on by the business's front desk.
 */
final class Contact
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
    ) {
    }
}
