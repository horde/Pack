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

use Horde_Pack_Driver_Igbinary;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Horde_Pack_Driver_Igbinary::class)]
class IgbinaryTest extends TestBase
{
    protected string $drivername = 'Horde_Pack_Driver_Igbinary';
}
