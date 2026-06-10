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
use Horde\Pack\Driver\Serialize;
use Horde\Pack\PackException;
use Horde\Pack\Test\Modern\Fixture\Sample;
use PHPUnit\Framework\Attributes\CoversClass;
use __PHP_Incomplete_Class;

#[CoversClass(Serialize::class)]
final class SerializeTest extends DriverTestCase
{
    protected function driver(): Driver
    {
        return new Serialize();
    }

    public function testReportsId1(): void
    {
        $this->assertSame(1, (new Serialize())->id());
    }

    public function testIsAlwaysSupported(): void
    {
        $this->assertTrue((new Serialize())->supported());
    }

    public function testAdvertisesPhpObjectSupport(): void
    {
        $this->assertTrue((new Serialize())->supportsPhpObjects());
    }

    public function testCorruptPayloadRaisesPackException(): void
    {
        $this->expectException(PackException::class);
        (new Serialize())->unpack('not-a-serialized-string');
    }

    public function testAllowedClassesNullPermitsObjectReconstruction(): void
    {
        $driver = new Serialize();
        $packed = $driver->pack(new Sample('x', [1]));

        $result = $driver->unpack($packed, null);

        $this->assertInstanceOf(Sample::class, $result);
        $this->assertSame('x', $result->label);
    }

    public function testEmptyAllowedClassesProducesIncompleteClass(): void
    {
        $driver = new Serialize();
        $packed = $driver->pack(new Sample('x', [1]));

        $result = $driver->unpack($packed, []);

        // With allowed_classes=[], unserialize replaces the class with
        // __PHP_Incomplete_Class - a deliberate downgrade so callers
        // detecting the type can reject the payload.
        $this->assertInstanceOf(__PHP_Incomplete_Class::class, $result);
    }

    public function testAllowedClassesWhitelistPermitsListedClassesOnly(): void
    {
        $driver = new Serialize();
        $packed = $driver->pack(new Sample('x', [1]));

        $result = $driver->unpack($packed, [Sample::class]);

        $this->assertInstanceOf(Sample::class, $result);
    }

    public function testFalseRoundTripsCorrectly(): void
    {
        // Serialize::unpack has special-cased the false-vs-failure ambiguity
        // since 1.x; protect that behaviour with an explicit test.
        $driver = new Serialize();
        $this->assertSame(false, $driver->unpack($driver->pack(false)));
    }
}
