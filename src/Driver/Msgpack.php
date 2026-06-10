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

/**
 * Driver backed by the {@link https://pecl.php.net/package/msgpack msgpack}
 * extension's primitive pack API ({@see \msgpack_pack()}).
 *
 * Pure msgpack encoding without the serialize-compatibility layer used by
 * {@see MsgpackSerialize}. Faster and smaller for plain data (scalars,
 * arrays, stdClass) but cannot round-trip arbitrary PHP objects, so
 * {@see self::supportsPhpObjects()} returns false. This is the highest-
 * priority driver in the default set: when available, plain payloads
 * prefer it over JSON or PHP serialize.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
final class Msgpack implements Driver
{
    public function id(): int
    {
        return 16;
    }

    public function supportsPhpObjects(): bool
    {
        return false;
    }

    public function supported(): bool
    {
        return extension_loaded('msgpack');
    }

    public function pack(mixed $data): string
    {
        return msgpack_pack($data);
    }

    public function unpack(string $data, ?array $allowedClasses = null): mixed
    {
        // msgpack_unpack has no allowed_classes equivalent; the parameter
        // is accepted for interface compatibility but ignored. Failures
        // from the extension surface directly.
        return msgpack_unpack($data);
    }
}
