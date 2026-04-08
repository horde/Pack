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

namespace Horde\Pack\Test\Unit;

use Horde_Pack;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Pack::class)]
class PackTest extends TestCase
{
    public function testExpectedExceptionOnSerialize(): void
    {
        $this->expectException(LogicException::class);
        $pack = new Horde_Pack();
        serialize($pack);
    }

    #[DataProvider('buggyDriverBackendsProvider')]
    public function testBuggyDriverBackends(mixed $data): void
    {
        $pack = new Horde_Pack();

        $p = $pack->pack($data, [
            'drivers' => [
                'Horde_Pack_Driver_Json',
                'Horde_Pack_Driver_Serialize',
            ],
        ]);

        $this->assertEquals(
            $data,
            $pack->unpack($p)
        );
    }

    public static function buggyDriverBackendsProvider(): array
    {
        return [
            // Bug #13275
            // ISO-8859-1 string
            [base64_decode('VORzdA==')],
            // JSON-C does not correctly handle null characters
            [["A\0B" => "A\0B"]],
        ];
    }
}
