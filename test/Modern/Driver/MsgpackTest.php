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
use Horde\Pack\Driver\Msgpack;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Msgpack::class)]
final class MsgpackTest extends DriverTestCase
{
    protected function driver(): Driver
    {
        return new Msgpack();
    }

    public function testReportsId16(): void
    {
        $this->assertSame(16, (new Msgpack())->id());
    }

    public function testDoesNotAdvertisePhpObjectSupport(): void
    {
        $this->assertFalse((new Msgpack())->supportsPhpObjects());
    }

    public function testSupportedReflectsExtensionAvailability(): void
    {
        $this->assertSame(
            extension_loaded('msgpack'),
            (new Msgpack())->supported(),
        );
    }
}
