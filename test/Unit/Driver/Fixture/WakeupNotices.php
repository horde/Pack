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
 * Fixture: a class whose reconstruction emits an unsuppressed E_NOTICE.
 *
 * Notices routinely leak out of real __wakeup / __unserialize paths
 * (deprecated config key access, uninitialised property warnings on
 * recent PHP versions, etc.). They must not discard a correctly
 * reconstructed object.
 */
class WakeupNotices
{
    public string $value = 'ok';

    public function __wakeup(): void
    {
        trigger_error('simulated notice', E_USER_NOTICE);
    }
}
