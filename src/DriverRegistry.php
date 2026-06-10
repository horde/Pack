<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack;

/**
 * Source of the {@see Driver} candidates a {@see Packer} instance considers.
 *
 * Implementations decide which drivers exist, in what order they are
 * preferred, and which subset is offered on the current host. The default
 * shipped implementation is {@see DefaultDriverRegistry}; applications with
 * stricter requirements (e.g. forbid Serialize entirely, or add a
 * proprietary driver) write their own.
 *
 * The contract is intentionally narrow: a single iteration in the order
 * the Packer should attempt during pack(). On unpack the registry is
 * not consulted by id - Packer indexes the iterable internally so duplicate
 * lookups are cheap.
 */
interface DriverRegistry
{
    /**
     * Yield the drivers Packer should consider, in priority order.
     *
     * Higher-id drivers come first by convention: Packer tries the head of
     * the iterable, falls through on failure, and stops on the first
     * successful pack(). Implementations must filter out drivers whose
     * {@see Driver::supported()} is false; Packer assumes everything yielded
     * here is actually usable.
     *
     * @return iterable<Driver>
     */
    public function drivers(): iterable;
}
