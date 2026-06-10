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
use Horde\Pack\Driver\MsgpackSerialize;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MsgpackSerialize::class)]
final class MsgpackSerializeTest extends DriverTestCase
{
    protected function driver(): Driver
    {
        return new MsgpackSerialize();
    }

    public function testReportsId8(): void
    {
        $this->assertSame(8, (new MsgpackSerialize())->id());
    }

    /**
     * The legacy driver had a long-standing TODO about object support that
     * never panned out. The modern driver carries the same answer until
     * the underlying msgpack behaviour is conclusively confirmed; pin the
     * current state with an explicit test so any future change is
     * deliberate.
     */
    public function testCurrentlyDoesNotAdvertisePhpObjectSupport(): void
    {
        $this->assertFalse((new MsgpackSerialize())->supportsPhpObjects());
    }

    public function testSupportedReflectsExtensionAvailability(): void
    {
        $this->assertSame(
            extension_loaded('msgpack'),
            (new MsgpackSerialize())->supported(),
        );
    }
}
