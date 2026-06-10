<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern\Driver;

use Horde\Pack\Driver;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Shared driver round-trip cases.
 *
 * Each concrete subclass nominates a driver via {@see self::driver()} and
 * inherits the standard battery of inputs (null, booleans, strings, arrays,
 * stdClass, and - for object-capable drivers - a real PHP object). Drivers
 * whose runtime requirement is missing on the host are skipped via
 * {@see self::setUp()} so the suite stays green on minimal images.
 *
 * Drivers are tested in isolation here - without a {@see \Horde\Pack\Packer}
 * facade and without a compressor. The full pipeline lives under
 * {@see \Horde\Pack\Test\Modern\PackerTest}.
 */
abstract class DriverTestCase extends TestCase
{
    /**
     * The driver under test. Concrete subclasses construct their driver here.
     */
    abstract protected function driver(): Driver;

    protected function setUp(): void
    {
        if (!$this->driver()->supported()) {
            $this->markTestSkipped(
                sprintf(
                    'Driver %s is not available on this host.',
                    $this->driver()::class,
                ),
            );
        }
    }

    public function testIdIsAValidFormatBit(): void
    {
        $id = $this->driver()->id();
        $this->assertContains(
            $id,
            \Horde\Pack\WireFormat::FORMAT_BITS,
            'Driver id must occupy one of the reserved single-bit slots.',
        );
    }

    public function testNull(): void
    {
        $this->assertRoundTrip(null);
    }

    public function testTrue(): void
    {
        $this->assertRoundTrip(true);
    }

    public function testFalse(): void
    {
        $this->assertRoundTrip(false);
    }

    public function testInteger(): void
    {
        $this->assertRoundTrip(42);
    }

    public function testFloat(): void
    {
        $this->assertRoundTrip(1.234);
    }

    public function testString(): void
    {
        $this->assertRoundTrip(str_repeat('foo', 1000));
    }

    public function testEmptyArray(): void
    {
        $this->assertRoundTrip([]);
    }

    public function testNumericArray(): void
    {
        $this->assertRoundTrip(range(1, 100));
    }

    public function testNestedAssociativeArray(): void
    {
        $this->assertRoundTrip([
            '1' => 'foo',
            'bar' => 'baz',
            'nested' => ['a', 'b', ['c', 'd']],
        ]);
    }

    public function testStdClass(): void
    {
        $ob = new stdClass();
        $ob->foo = 'bar';
        $ob->foo2 = [1, 2, 3];
        $ob->foo3 = 4;
        $ob->foo4 = true;
        $ob->foo5 = null;
        $this->assertRoundTrip($ob);
    }

    public function testRealPhpObject(): void
    {
        if (!$this->driver()->supportsPhpObjects()) {
            $this->markTestSkipped(
                'Driver does not advertise PHP-object support.',
            );
        }
        // A non-stdClass object with state worth round-tripping.
        $this->assertRoundTrip(new \Horde\Pack\Test\Modern\Fixture\Sample(
            'hello',
            [1, 2, 3],
        ));
    }

    /**
     * Pack the value, unpack, assert structural equality.
     *
     * Equality is by value (assertEquals): drivers that round-trip an object
     * via serialize() reconstruct equivalent instances rather than the
     * exact original reference, which is the contract callers expect.
     */
    private function assertRoundTrip(mixed $value): void
    {
        $driver = $this->driver();
        $packed = $driver->pack($value);
        $this->assertIsString($packed);

        $unpacked = $driver->unpack($packed);
        $this->assertEquals($value, $unpacked);
    }
}
