<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Pack\Test\Unit\Driver;

use Horde\Pack\Test\Unit\Driver\Fixture\WakeupNotices;
use Horde\Pack\Test\Unit\Driver\Fixture\WakeupWarns;
use Horde_Pack;
use Horde_Pack_Autodetermine;
use Horde_Pack_Exception;
use PHPUnit\Framework\TestCase;
use stdClass;

abstract class TestBase extends TestCase
{
    protected static Horde_Pack $pack;
    protected static Horde_Pack_Autodetermine $sampleob;

    protected string $drivername;

    public static function setUpBeforeClass(): void
    {
        self::$pack = new Horde_Pack();
        self::$sampleob = new Horde_Pack_Autodetermine(true);
    }

    protected function setUp(): void
    {
        if (!call_user_func([$this->drivername, 'supported'])) {
            $this->markTestSkipped(
                sprintf('Driver %s is not available.', $this->drivername)
            );
        }
    }

    public function testNull(): void
    {
        $this->runPackUnpack(null);
    }

    public function testNullWithCompression(): void
    {
        $this->runPackUnpack(null, true);
    }

    public function testBoolean(): void
    {
        $this->runPackUnpack(true);
        $this->runPackUnpack(false);
    }

    public function testBooleanWithCompression(): void
    {
        $this->runPackUnpack(true, true);
        $this->runPackUnpack(false, true);
    }

    public function testString(): void
    {
        $this->runPackUnpack(str_repeat('foo', 1000));
    }

    public function testStringWithCompression(): void
    {
        $this->runPackUnpack(str_repeat('foo', 1000), true);
    }

    public function testSimpleArray(): void
    {
        $this->runPackUnpack([]);
        $this->runPackUnpack(range(1, 1000));
    }

    public function testSimpleArrayWithCompression(): void
    {
        $this->runPackUnpack([], true);
        $this->runPackUnpack(range(1, 1000), true);
    }

    public function testNestedArray(): void
    {
        $tmp = [
            '1' => 'foo',
            'bar' => 'baz',
        ];
        $this->runPackUnpack(array_fill(0, 1, $tmp));
    }

    public function testNestedArrayWithCompression(): void
    {
        $tmp = [
            '1' => 'foo',
            'bar' => 'baz',
        ];
        $this->runPackUnpack(array_fill(0, 1, $tmp), true);
    }

    public function testObject(): void
    {
        $ob = new stdClass();
        $ob->foo = 'bar';
        $ob->foo2 = [1, 2, 3];
        $ob->foo3 = 4;
        $ob->foo4 = true;
        $ob->foo5 = null;
        $this->runPackUnpack($ob);
    }

    public function testObjectWithCompression(): void
    {
        $ob = new stdClass();
        $ob->foo = 'bar';
        $ob->foo2 = [1, 2, 3];
        $ob->foo3 = 4;
        $ob->foo4 = true;
        $ob->foo5 = null;
        $this->runPackUnpack($ob, true);
    }

    public function testPhpObject(): void
    {
        $driver = new $this->drivername();
        if (!$driver->phpob) {
            $this->markTestSkipped('Driver does not support PHP objects.');
        }
        $this->runPackUnpack(self::$sampleob);
    }

    public function testPhpObjectWithCompression(): void
    {
        $driver = new $this->drivername();
        if (!$driver->phpob) {
            $this->markTestSkipped('Driver does not support PHP objects.');
        }
        $this->runPackUnpack(self::$sampleob, true);
    }

    public function testExpectedExceptionOnBadUnpack(): void
    {
        $this->expectException(Horde_Pack_Exception::class);
        $packed = $this->packData(true, false);
        self::$pack->unpack($packed[0] . "A{{}");
    }

    /**
     * A warning emitted from within __wakeup / __unserialize (mirrors
     * the real IMP_Imap / Horde_Imap_Client_Base failure mode where
     * @fopen on a non-writable debug path raises E_WARNING during
     * unpack) must NOT trip the driver's local error handler. Only
     * warnings from the pack extension itself (identified by their
     * message prefix) indicate an actual decode failure; user-space
     * emissions from an object's own wakeup logic are semantically
     * unrelated.
     *
     * Regression cover for the "IMP is marked as authenticated, but no
     * credentials can be found in the session" symptom traced back to
     * horde/pack v2.0.0 discarding valid unpacked objects on any
     * warning during unpack.
     */
    public function testUserWarningDuringWakeupDoesNotFailUnpack(): void
    {
        $driver = new $this->drivername();
        if (!$driver->phpob) {
            $this->markTestSkipped('Driver does not support PHP objects.');
        }
        $ob = new WakeupWarns();
        $this->runPackUnpack($ob);
    }

    /**
     * A plain E_USER_NOTICE from within __wakeup / __unserialize must
     * not discard a correctly reconstructed object either — notices
     * are a routine emission from legacy object graphs and are
     * semantically unrelated to whether the payload decoded.
     */
    public function testUserNoticeDuringWakeupDoesNotFailUnpack(): void
    {
        $driver = new $this->drivername();
        if (!$driver->phpob) {
            $this->markTestSkipped('Driver does not support PHP objects.');
        }
        $ob = new WakeupNotices();
        // Silence the notice at the phpunit level so the test framework
        // doesn't itself convert it to a failure. The invariant under
        // test is that the driver survives, not that PHP is silent.
        $previous = error_reporting();
        error_reporting($previous & ~E_USER_NOTICE);
        try {
            $this->runPackUnpack($ob);
        } finally {
            error_reporting($previous);
        }
    }

    protected function runPackUnpack(mixed $data, bool $compress = false): void
    {
        $packed = $this->packData($data, $compress);

        $this->assertNotSame(
            $packed,
            $data
        );

        $unpacked = self::$pack->unpack($packed);

        $this->assertEquals(
            $data,
            $unpacked
        );
    }

    private function packData(mixed $data, bool $compress): string
    {
        return self::$pack->pack(
            $data,
            [
                'compress' => $compress ? 0 : false,
                'drivers' => [
                    $this->drivername,
                ],
            ]
        );
    }
}
