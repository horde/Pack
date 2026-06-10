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
use Horde\Pack\Driver\Json;
use Horde\Pack\PackException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Json::class)]
final class JsonTest extends DriverTestCase
{
    protected function driver(): Driver
    {
        return new Json();
    }

    public function testReportsId2(): void
    {
        $this->assertSame(2, (new Json())->id());
    }

    public function testDoesNotAdvertisePhpObjectSupport(): void
    {
        $this->assertFalse((new Json())->supportsPhpObjects());
    }

    public function testInvalidUtf8RaisesPackException(): void
    {
        // ISO-8859-1 byte sequence; not valid UTF-8 → JSON encoder rejects.
        $this->expectException(PackException::class);
        (new Json())->pack(base64_decode('VORzdA=='));
    }

    public function testCorruptPayloadRaisesPackException(): void
    {
        $this->expectException(PackException::class);
        // Driver requires its one-byte type prefix (0/1) plus valid JSON.
        (new Json())->unpack('0not-json');
    }

    public function testEmptyPayloadRaisesPackException(): void
    {
        $this->expectException(PackException::class);
        (new Json())->unpack('');
    }

    public function testArrayShapePreservedAcrossRoundTrip(): void
    {
        $driver = new Json();

        // Without the array/non-array prefix byte, json_decode alone would
        // not know whether to return an array or stdClass for an empty {}.
        $array = ['key' => 'value'];
        $this->assertEquals($array, $driver->unpack($driver->pack($array)));
    }
}
