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

use Horde_Pack_Autodetermine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Horde_Pack_Autodetermine::class)]
class AutodetermineTest extends TestCase
{
    private array $types;

    protected function setUp(): void
    {
        $this->types = [
            true,
            1,
            1.234,
            'foo',
            null,
            [],
            new stdClass(),
        ];
    }

    public function testNegativeResults(): void
    {
        foreach ($this->types as $val) {
            $this->runAutodetect($val, false);
        }

        $this->runAutodetect($this->types, false);
    }

    public function testPositiveResults(): void
    {
        $this->runAutodetect($this, true);
        $this->runAutodetect(array_merge($this->types, [$this]), true);

        $a = new stdClass();
        $a->a = $this;
        $b = new stdClass();
        $b->b = [$a];
        $c = new stdClass();
        $c->c = [$b];
        $this->runAutodetect([$c], true);
    }

    private function runAutodetect(mixed $data, bool $expected): void
    {
        $ob = new Horde_Pack_Autodetermine($data);
        if ($expected) {
            $this->assertTrue($ob->phpob);
        } else {
            $this->assertFalse($ob->phpob);
        }
    }
}
