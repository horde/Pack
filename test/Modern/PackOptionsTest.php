<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern;

use Horde\Pack\Driver\Json;
use Horde\Pack\Driver\Serialize;
use Horde\Pack\PackOptions;
use Horde\Pack\WireFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PackOptions::class)]
final class PackOptionsTest extends TestCase
{
    public function testDefaultsMatchLegacyBehaviour(): void
    {
        $options = new PackOptions();

        $this->assertSame(WireFormat::DEFAULT_COMPRESS_THRESHOLD, $options->compressThreshold);
        $this->assertNull($options->allowDrivers);
        $this->assertNull($options->hasPhpObjects);
        $this->assertNull(
            $options->allowedClasses,
            'allowed_classes default must be null (unrestricted) for BC.',
        );
    }

    public function testCompressedFactoryUsesDefaultThreshold(): void
    {
        $options = PackOptions::compressed();
        $this->assertSame(WireFormat::DEFAULT_COMPRESS_THRESHOLD, $options->compressThreshold);
    }

    public function testCompressedFactoryAcceptsCustomThreshold(): void
    {
        $options = PackOptions::compressed(64);
        $this->assertSame(64, $options->compressThreshold);
    }

    public function testUncompressedFactoryDisablesCompression(): void
    {
        $options = PackOptions::uncompressed();
        $this->assertNull($options->compressThreshold);
    }

    public function testAlwaysCompressFactorySetsThresholdToZero(): void
    {
        $options = PackOptions::alwaysCompress();
        $this->assertSame(0, $options->compressThreshold);
    }

    public function testWithAllowedDriversReturnsNewInstance(): void
    {
        $original = new PackOptions();
        $modified = $original->withAllowedDrivers(Json::class, Serialize::class);

        $this->assertNotSame($original, $modified);
        $this->assertNull($original->allowDrivers, 'Original must remain unchanged.');
        $this->assertSame([Json::class, Serialize::class], $modified->allowDrivers);
    }

    public function testWithAllowedClassesReturnsNewInstance(): void
    {
        $original = new PackOptions();
        $modified = $original->withAllowedClasses(stdClass::class);

        $this->assertNotSame($original, $modified);
        $this->assertNull($original->allowedClasses);
        $this->assertSame([stdClass::class], $modified->allowedClasses);
    }

    public function testWithAllowedClassesEmptyArgListDisablesObjects(): void
    {
        $options = (new PackOptions())->withAllowedClasses();
        $this->assertSame([], $options->allowedClasses);
    }

    public function testWithPhpObjectsReturnsNewInstance(): void
    {
        $original = new PackOptions();
        $modified = $original->withPhpObjects(true);

        $this->assertNotSame($original, $modified);
        $this->assertNull($original->hasPhpObjects);
        $this->assertTrue($modified->hasPhpObjects);
    }

    public function testBuilderChainComposes(): void
    {
        $options = PackOptions::compressed(256)
            ->withAllowedDrivers(Json::class)
            ->withPhpObjects(false)
            ->withAllowedClasses(stdClass::class);

        $this->assertSame(256, $options->compressThreshold);
        $this->assertSame([Json::class], $options->allowDrivers);
        $this->assertFalse($options->hasPhpObjects);
        $this->assertSame([stdClass::class], $options->allowedClasses);
    }
}
