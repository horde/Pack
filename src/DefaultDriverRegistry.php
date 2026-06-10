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
 * The default {@see DriverRegistry} shipped with horde/Pack.
 *
 * Constructed without arguments, it offers the canonical set of drivers
 * shipped with this package, ordered by descending id (highest priority
 * first). Drivers whose {@see Driver::supported()} returns false on the
 * current host are filtered out at iteration time - extension absence is
 * resolved late so the same registry instance is portable across hosts
 * with different module footprints.
 *
 * Constructed with explicit {@see Driver} arguments, it overrides the
 * default set entirely. This is the extension point: callers who want to
 * add a custom driver, restrict the set, or change priorities pass their
 * own variadic list.
 *
 * Replacing the {@see FilesystemIterator}-based discovery the legacy 1.x
 * implementation used: that approach made auto-discovery a side effect of
 * `new Horde_Pack()`, broke under classmap optimisation, and offered no
 * way for third parties to extend the set without dropping a file into
 * the package directory.
 */
final class DefaultDriverRegistry implements DriverRegistry
{
    /**
     * Drivers ordered by descending id, prior to supported() filtering.
     *
     * @var list<Driver>
     */
    private readonly array $drivers;

    /**
     * @param Driver ...$drivers Optional override list. If omitted, the
     *   canonical set is used; if supplied, replaces the canonical set
     *   entirely (no auto-merging - composing default and custom drivers
     *   is up to the caller).
     */
    public function __construct(Driver ...$drivers)
    {
        $list = $drivers !== [] ? array_values($drivers) : [
            new Driver\Serialize(),
            new Driver\Json(),
            new Driver\Igbinary(),
            new Driver\MsgpackSerialize(),
            new Driver\Msgpack(),
        ];

        // Highest id first: Packer iterates this list during pack() and the
        // first driver that succeeds wins. Sorting here means custom
        // registrations cannot accidentally insert at a lower priority
        // than they advertise.
        usort($list, static fn(Driver $a, Driver $b) => $b->id() <=> $a->id());

        $this->drivers = $list;
    }

    public function drivers(): iterable
    {
        foreach ($this->drivers as $driver) {
            if ($driver->supported()) {
                yield $driver;
            }
        }
    }
}
