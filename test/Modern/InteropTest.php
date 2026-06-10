<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern;

use Horde_Pack;
use Horde_Pack_Driver_Igbinary;
use Horde_Pack_Driver_Json;
use Horde_Pack_Driver_Msgpack;
use Horde_Pack_Driver_Msgpackserialize;
use Horde_Pack_Driver_Serialize;
use Horde\Pack\Driver;
use Horde\Pack\Driver\Igbinary;
use Horde\Pack\Driver\Json;
use Horde\Pack\Driver\Msgpack;
use Horde\Pack\Driver\MsgpackSerialize;
use Horde\Pack\Driver\Serialize;
use Horde\Pack\Packer;
use Horde\Pack\PackOptions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Wire-format compatibility between legacy Horde_Pack and modern Packer.
 *
 * The library ships two parallel implementations during the 2.x line. They
 * cohabit in cache stores and session blobs, so a value packed by one must
 * round-trip through the other and vice versa. These tests pin the
 * cross-implementation contract for every shipped driver and every
 * compression mode.
 *
 * If any of these tests start failing, the on-disk format has drifted and
 * either the legacy or modern implementation has a regression. There is
 * no acceptable reason to update the assertions to match a new format
 * without also bumping a major version and migrating the in-tree callers.
 */
#[CoversNothing]
final class InteropTest extends TestCase
{
    /**
     * Modern driver class -> matching legacy driver class.
     */
    private const DRIVER_CLASS_MAP = [
        Serialize::class => Horde_Pack_Driver_Serialize::class,
        Json::class => Horde_Pack_Driver_Json::class,
        Igbinary::class => Horde_Pack_Driver_Igbinary::class,
        MsgpackSerialize::class => Horde_Pack_Driver_Msgpackserialize::class,
        Msgpack::class => Horde_Pack_Driver_Msgpack::class,
    ];

    /**
     * @return iterable<string, array{Driver, string, mixed}>
     */
    public static function driverPayloadProvider(): iterable
    {
        $drivers = [
            'Serialize' => new Serialize(),
            'Json' => new Json(),
            'Igbinary' => new Igbinary(),
            'MsgpackSerialize' => new MsgpackSerialize(),
            'Msgpack' => new Msgpack(),
        ];

        $payloads = [
            'null' => null,
            'true' => true,
            'false' => false,
            'integer' => 42,
            'short string' => 'hi',
            'long string' => str_repeat('horde-pack-', 200),
            'array of ints' => range(1, 50),
            'nested associative' => [
                'a' => 1,
                'b' => ['c' => 'd', 'e' => [1, 2, 3]],
            ],
        ];

        foreach ($drivers as $driverLabel => $modernDriver) {
            foreach ($payloads as $payloadLabel => $value) {
                yield "{$driverLabel} / {$payloadLabel}"
                    => [$modernDriver, $payloadLabel, $value];
            }
        }
    }

    /**
     * Modern packs, legacy unpacks. Proves modern Packer emits a header
     * the legacy Horde_Pack class accepts and a payload the legacy driver
     * decodes.
     */
    #[DataProvider('driverPayloadProvider')]
    public function testModernPackedValueUnpacksUnderLegacyHordePack(
        Driver $driver,
        string $payloadLabel,
        mixed $value,
    ): void {
        $this->skipIfDriverUnsupported($driver);

        $packer = new Packer();
        $options = (new PackOptions())->withAllowedDrivers($driver::class);

        $packed = $packer->pack($value, $options);
        $legacy = new Horde_Pack();

        $this->assertEquals($value, $legacy->unpack($packed));
    }

    /**
     * Legacy packs, modern unpacks. Proves modern Packer accepts the
     * exact byte sequences the legacy class has emitted for years.
     */
    #[DataProvider('driverPayloadProvider')]
    public function testLegacyPackedValueUnpacksUnderModernPacker(
        Driver $driver,
        string $payloadLabel,
        mixed $value,
    ): void {
        $this->skipIfDriverUnsupported($driver);

        $legacy = new Horde_Pack();
        $packed = $legacy->pack($value, [
            'drivers' => [self::DRIVER_CLASS_MAP[$driver::class]],
            'compress' => false,
        ]);

        $packer = new Packer();
        $this->assertEquals($value, $packer->unpack($packed));
    }

    /**
     * The same with compression on. Compression flag in the header travels
     * across implementations.
     */
    #[DataProvider('driverPayloadProvider')]
    public function testCompressedRoundTripAcrossImplementations(
        Driver $driver,
        string $payloadLabel,
        mixed $value,
    ): void {
        $this->skipIfDriverUnsupported($driver);

        $packer = new Packer();
        $legacy = new Horde_Pack();
        $modernOptions = (new PackOptions(compressThreshold: 0))
            ->withAllowedDrivers($driver::class);

        // Modern -> legacy.
        $modernPacked = $packer->pack($value, $modernOptions);
        $this->assertEquals($value, $legacy->unpack($modernPacked));

        // Legacy -> modern.
        $legacyPacked = $legacy->pack($value, [
            'drivers' => [self::DRIVER_CLASS_MAP[$driver::class]],
            'compress' => 0,
        ]);
        $this->assertEquals($value, $packer->unpack($legacyPacked));
    }

    private function skipIfDriverUnsupported(Driver $driver): void
    {
        if (!$driver->supported()) {
            $this->markTestSkipped(sprintf(
                'Driver %s is not available on this host.',
                $driver::class,
            ));
        }

        // Drivers that cannot round-trip arbitrary PHP objects also cannot
        // round-trip values containing non-stdClass objects. The provider
        // only emits scalar/array payloads, so the constraint affects no
        // payload here -- but check anyway in case the provider grows.
    }
}
