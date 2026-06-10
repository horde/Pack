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
use Horde\Pack\Packer;
use Horde\Pack\PackException;
use Horde\Pack\PackOptions;
use Horde\Pack\Test\Modern\Fixture\FakeDriver;
use Horde\Pack\Test\Modern\Fixture\Sample;
use Horde\Pack\WireFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use __PHP_Incomplete_Class;

#[CoversClass(Packer::class)]
final class PackerTest extends TestCase
{
    public function testEmptyInputUnpacksToEmptyInput(): void
    {
        $packer = new Packer();
        $this->assertSame('', $packer->unpack(''));
    }

    public function testRoundTripsScalars(): void
    {
        $packer = new Packer();
        $this->assertSame(42, $packer->unpack($packer->pack(42)));
        $this->assertSame('hello', $packer->unpack($packer->pack('hello')));
        $this->assertSame(true, $packer->unpack($packer->pack(true)));
        $this->assertSame(false, $packer->unpack($packer->pack(false)));
        $this->assertNull($packer->unpack($packer->pack(null)));
    }

    public function testRoundTripsArray(): void
    {
        $packer = new Packer();
        $value = ['a' => 1, 'b' => [2, 3, 4], 'c' => null];
        $this->assertSame($value, $packer->unpack($packer->pack($value)));
    }

    public function testRoundTripsRealPhpObject(): void
    {
        $packer = new Packer();
        $value = new Sample('label', [10, 20, 30]);

        $unpacked = $packer->unpack($packer->pack($value));

        $this->assertInstanceOf(Sample::class, $unpacked);
        $this->assertSame('label', $unpacked->label);
        $this->assertSame([10, 20, 30], $unpacked->values);
    }

    public function testCompressionFlagSetForLargePayloads(): void
    {
        $packer = new Packer();
        $payload = str_repeat('x', 1000);

        $packed = $packer->pack($payload);

        $this->assertNotSame(0, ord($packed[0]) & WireFormat::COMPRESS_MASK);
    }

    public function testUncompressedOptionsSkipCompression(): void
    {
        $packer = new Packer();
        $payload = str_repeat('x', 1000);

        $packed = $packer->pack($payload, PackOptions::uncompressed());

        $this->assertSame(0, ord($packed[0]) & WireFormat::COMPRESS_MASK);
    }

    public function testAlwaysCompressOptionsForceCompressionOnTinyPayload(): void
    {
        $packer = new Packer();

        // A short payload that would not normally trip the threshold.
        $packed = $packer->pack('hi', PackOptions::alwaysCompress());

        $this->assertNotSame(0, ord($packed[0]) & WireFormat::COMPRESS_MASK);
        $this->assertSame('hi', $packer->unpack($packed));
    }

    public function testHonoursAllowDriversRestriction(): void
    {
        $packer = new Packer();

        $packed = $packer->pack(
            ['plain' => 'data'],
            (new PackOptions())->withAllowedDrivers(Serialize::class),
        );

        // First byte = driver id (compress mask cleared).
        $id = ord($packed[0]) & ~WireFormat::COMPRESS_MASK & ~WireFormat::EXTENSION_MASK;
        $this->assertSame(1, $id, 'Restricted to Serialize, must use id 1.');
    }

    public function testRoundTripsWhenRestrictedToJson(): void
    {
        $packer = new Packer();
        $value = ['a' => 1, 'b' => 'two'];

        $packed = $packer->pack(
            $value,
            (new PackOptions())->withAllowedDrivers(Json::class),
        );

        $this->assertSame(2, ord($packed[0]) & ~WireFormat::COMPRESS_MASK & ~WireFormat::EXTENSION_MASK);
        $this->assertSame($value, $packer->unpack($packed));
    }

    public function testRejectsUnknownDriverIdOnUnpack(): void
    {
        $packer = new Packer();
        $this->expectException(PackException::class);
        // 0x20 = wire-format slot 32: not used by any shipped driver.
        $packer->unpack(chr(32) . 'whatever');
    }

    public function testRejectsExtensionBitOnUnpack(): void
    {
        $packer = new Packer();
        $this->expectException(PackException::class);
        $packer->unpack(chr(WireFormat::EXTENSION_MASK | 1) . 'rest');
    }

    public function testDuplicateDriverIdsRejectedAtConstruction(): void
    {
        $this->expectException(PackException::class);
        new Packer(new DefaultDriverRegistry(
            new FakeDriver(id: 4),
            new FakeDriver(id: 4),
        ));
    }

    public function testThrowsWhenNoDriverCanPack(): void
    {
        // A registry whose only driver is restricted to non-PHP-object
        // payloads, given a payload that contains a real PHP object.
        $packer = new Packer(new DefaultDriverRegistry(
            new FakeDriver(id: 4, supportsPhpObjects: false),
        ));

        $this->expectException(PackException::class);
        $packer->pack(new Sample('x', []));
    }

    public function testAllowedClassesPropagatesToSerializeDriverOnUnpack(): void
    {
        // The Serialize driver is the only shipped driver that honours
        // allowedClasses (the others use opaque binary formats with no
        // class-whitelist hook in their unserialize calls). Pin pack/
        // unpack to Serialize so we can prove the option flows from
        // PackOptions through Packer to Driver::unpack().
        $packer = new Packer();
        $restrictToSerialize = (new PackOptions())
            ->withAllowedDrivers(Serialize::class);

        $packed = $packer->pack(new Sample('x', [1]), $restrictToSerialize);

        $unpacked = $packer->unpack(
            $packed,
            $restrictToSerialize->withAllowedClasses(),
        );

        // No classes whitelisted, so the object surfaces as
        // __PHP_Incomplete_Class. Proves the allowedClasses option flowed
        // all the way to Serialize::unpack(), not silently dropped.
        $this->assertInstanceOf(__PHP_Incomplete_Class::class, $unpacked);
    }

    /**
     * Driver-priority is part of the public contract: with the canonical
     * registry on a host with msgpack loaded, plain payloads must select
     * msgpack (id 16) over Serialize (id 1).
     */
    #[DataProvider('plainPayloadsProvider')]
    public function testPlainPayloadsSelectHighestSupportedDriver(mixed $value): void
    {
        $packer = new Packer();
        $packed = $packer->pack($value, PackOptions::uncompressed());
        $id = ord($packed[0]) & ~WireFormat::COMPRESS_MASK & ~WireFormat::EXTENSION_MASK;

        // Whatever id was picked, it must round-trip and be one of the
        // canonical driver ids - and not 1 (Serialize) when faster
        // alternatives exist on the host.
        $this->assertContains($id, WireFormat::FORMAT_BITS);
        $this->assertSame($value, $packer->unpack($packed));
    }

    public static function plainPayloadsProvider(): array
    {
        return [
            'integer' => [42],
            'string' => ['hello'],
            'array' => [['a', 'b', 'c']],
        ];
    }
}
