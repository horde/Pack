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
use Horde\Pack\Driver\Igbinary;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Igbinary::class)]
final class IgbinaryTest extends DriverTestCase
{
    protected function driver(): Driver
    {
        return new Igbinary();
    }

    public function testReportsId4(): void
    {
        $this->assertSame(4, (new Igbinary())->id());
    }

    public function testAdvertisesPhpObjectSupport(): void
    {
        $this->assertTrue((new Igbinary())->supportsPhpObjects());
    }

    public function testSupportedReflectsExtensionAvailability(): void
    {
        $this->assertSame(
            extension_loaded('igbinary'),
            (new Igbinary())->supported(),
        );
    }
}
