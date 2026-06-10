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
use JsonException;

/**
 * Driver backed by PHP's built-in {@see \json_encode()} / {@see \json_decode()}.
 *
 * Faster and more compact than serialize() for plain data (scalars, arrays,
 * stdClass). Cannot round-trip arbitrary PHP objects: anything more
 * structured than stdClass loses its class identity through the JSON
 * round-trip, so {@see self::supportsPhpObjects()} returns false.
 *
 * One quirk: JSON does not distinguish between an object and an
 * associative array on decode. To preserve the original shape, the payload
 * is prefixed with a single ASCII byte - '0' if the original input was a
 * non-array, '1' if it was an array. The unpack path uses that byte to
 * pick the `assoc` argument to json_decode().
 *
 * Errors surface as {@see PackException}; the underlying {@see JsonException}
 * is wrapped as the previous exception so callers retain access to the
 * specific JSON error code.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
final class Json implements Driver
{
    public function id(): int
    {
        return 2;
    }

    public function supportsPhpObjects(): bool
    {
        return false;
    }

    public function supported(): bool
    {
        return extension_loaded('json');
    }

    public function pack(mixed $data): string
    {
        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new PackException(
                'Data cannot be JSON packed: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        // Prefix with a single byte recording whether the original input
        // was an array, so unpack() can pick the right `assoc` argument.
        return (is_array($data) ? '1' : '0') . $encoded;
    }

    public function unpack(string $data, ?array $allowedClasses = null): mixed
    {
        if ($data === '') {
            throw new PackException('Empty JSON payload.');
        }

        $assoc = $data[0] === '1';
        $payload = substr($data, 1);

        try {
            return json_decode($payload, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new PackException(
                'Error when unpacking JSON data: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
