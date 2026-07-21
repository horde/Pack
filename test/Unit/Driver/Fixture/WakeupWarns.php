<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Unit\Driver\Fixture;

/**
 * Fixture: a class whose reconstruction emits an E_USER_WARNING.
 *
 * Models the real IMP_Imap / Horde_Imap_Client_Base pattern where
 * __wakeup / __unserialize touches a resource (e.g. `@fopen($debug, 'a')`
 * on a non-writable path) and PHP raises a warning during unpack. The
 * unpack driver must not treat that warning as a decoder failure — the
 * object is correctly reconstructed regardless. Only warnings from the
 * pack extension itself (identified by message prefix) indicate a real
 * decode failure.
 */
class WakeupWarns
{
    public string $value = 'ok';

    public function __wakeup(): void
    {
        @trigger_error('simulated resource attach warning', E_USER_WARNING);
    }
}
