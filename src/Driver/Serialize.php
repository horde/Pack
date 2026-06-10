<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */

namespace Horde\Pack\Driver;

use Horde\Pack\Driver;
use Horde\Pack\PackException;

/**
 * Driver backed by PHP's built-in {@see \serialize()} / {@see \unserialize()}.
 *
 * Always available (no extension required) and supports the full PHP type
 * system, including objects relying on __sleep / __wakeup / __serialize hooks.
 * The lowest-priority driver: every host can deserialize what it produces,
 * so it acts as the universal fallback when no faster format is supported.
 *
 * Honours the `allowed_classes` whitelist on unpack - see
 * {@see Driver::unpack()} for semantics. Tightening this whitelist at the
 * call site closes a deserialization-gadget primitive that an attacker who
 * controls the cache contents can otherwise exploit.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
final class Serialize implements Driver
{
    public function id(): int
    {
        return 1;
    }

    public function supportsPhpObjects(): bool
    {
        return true;
    }

    public function supported(): bool
    {
        return true;
    }

    public function pack(mixed $data): string
    {
        return serialize($data);
    }

    public function unpack(string $data, ?array $allowedClasses = null): mixed
    {
        $options = $allowedClasses === null
            ? []
            : ['allowed_classes' => $allowedClasses];

        $out = @unserialize($data, $options);

        // unserialize() returns false on failure, but the bare value `false`
        // is a legitimate input. Disambiguate by re-packing false and
        // comparing - the input is genuinely false only if it matches.
        if ($out !== false || $data === serialize(false)) {
            return $out;
        }

        throw new PackException('Error when unpacking serialized data.');
    }
}
