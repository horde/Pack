<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern\Fixture;

/**
 * Concrete object fixture for driver tests.
 *
 * Used to exercise drivers that advertise PHP-object support. A bespoke
 * class is required because anonymous classes and closures cannot be
 * serialized, and stdClass exercises the object-but-not-real-class branch
 * that several drivers special-case.
 */
final class Sample
{
    public function __construct(
        public readonly string $label,
        /** @var list<int> */
        public readonly array $values,
    ) {}
}
