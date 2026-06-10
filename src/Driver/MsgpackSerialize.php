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
 * Driver backed by the {@link https://pecl.php.net/package/msgpack msgpack}
 * extension's serialize-compatible mode ({@see \msgpack_serialize()}).
 *
 * Sister driver to {@see Msgpack}: both wrap the same extension, but this
 * one uses the serialize-compatible API while {@see Msgpack} uses the
 * primitive pack/unpack API. The serialize-compatible path is intended to
 * support PHP objects in principle, but in practice this has not been
 * verified end-to-end - see the long-standing TODO inherited from the
 * legacy driver. The driver therefore reports
 * {@see self::supportsPhpObjects()} as false today; revisit if and when the
 * msgpack extension's behaviour for object types is conclusively confirmed.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
final class MsgpackSerialize implements Driver
{
    public function id(): int
    {
        return 8;
    }

    /**
     * @todo Inherited from the legacy driver: this theoretically should
     *   support PHP objects, but the original author was unable to make it
     *   work reliably. Investigate and resolve in a future release.
     */
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
        return msgpack_serialize($data);
    }

    public function unpack(string $data, ?array $allowedClasses = null): mixed
    {
        // msgpack_unserialize has no allowed_classes equivalent; the
        // parameter is accepted for interface compatibility but ignored.
        // It also has no equivalent of unserialize()'s false-or-failure
        // ambiguity - failures surface as exceptions in modern msgpack.
        return msgpack_unserialize($data);
    }
}
