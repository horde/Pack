<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern;

use Horde\Pack\DefaultDriverRegistry;
use Horde\Pack\Driver\Json;
use Horde\Pack\Driver\Serialize;
use Horde\Pack\Test\Modern\Fixture\FakeDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultDriverRegistry::class)]
final class DefaultDriverRegistryTest extends TestCase
{
    public function testNoArgsExposesCanonicalDriverSet(): void
    {
        $registry = new DefaultDriverRegistry();

        $ids = [];
        foreach ($registry->drivers() as $driver) {
            $ids[] = $driver->id();
        }

        // The exact id set depends on which extensions are loaded on the
        // test host. The two unconditionally-supported drivers (Serialize,
        // Json) must always appear; the others are best-effort.
        $this->assertContains(1, $ids, 'Serialize driver should always be present.');
        $this->assertContains(2, $ids, 'Json driver should always be present.');
    }

    public function testYieldsDriversInDescendingPriorityOrder(): void
    {
        // Use fakes so the test is independent of extension availability.
        $registry = new DefaultDriverRegistry(
            new FakeDriver(id: 1),
            new FakeDriver(id: 32),
            new FakeDriver(id: 8),
            new FakeDriver(id: 16),
        );

        $ids = [];
        foreach ($registry->drivers() as $driver) {
            $ids[] = $driver->id();
        }

        $this->assertSame([32, 16, 8, 1], $ids);
    }

    public function testFiltersOutUnsupportedDrivers(): void
    {
        $registry = new DefaultDriverRegistry(
            new FakeDriver(id: 16, supported: true),
            new FakeDriver(id: 8, supported: false),
            new FakeDriver(id: 4, supported: true),
        );

        $ids = [];
        foreach ($registry->drivers() as $driver) {
            $ids[] = $driver->id();
        }

        $this->assertSame([16, 4], $ids);
    }

    public function testVariadicOverrideReplacesCanonicalSetEntirely(): void
    {
        // If the user supplies any driver, none of the canonical drivers
        // are silently merged in - registration is explicit.
        $registry = new DefaultDriverRegistry(new FakeDriver(id: 32));

        $drivers = [];
        foreach ($registry->drivers() as $driver) {
            $drivers[] = $driver;
        }

        $this->assertCount(1, $drivers);
        $this->assertSame(32, $drivers[0]->id());
    }

    public function testCanComposeCanonicalDriversWithCustomOnes(): void
    {
        // Pattern test: callers who want to extend rather than replace
        // pass the canonical drivers explicitly alongside their own.
        $registry = new DefaultDriverRegistry(
            new Serialize(),
            new Json(),
            new FakeDriver(id: 32),
        );

        $ids = [];
        foreach ($registry->drivers() as $driver) {
            $ids[] = $driver->id();
        }

        $this->assertSame([32, 2, 1], $ids);
    }

    public function testIterableIsRepeatable(): void
    {
        // The interface returns iterable, not Iterator - callers may
        // iterate twice. Generator implementation must withstand that.
        $registry = new DefaultDriverRegistry(
            new FakeDriver(id: 4),
            new FakeDriver(id: 16),
        );

        $first = iterator_to_array($registry->drivers(), false);
        $second = iterator_to_array($registry->drivers(), false);

        $this->assertSame(
            array_map(static fn($d) => $d->id(), $first),
            array_map(static fn($d) => $d->id(), $second),
        );
    }
}
