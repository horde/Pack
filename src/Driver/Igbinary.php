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
 * Driver backed by the {@link https://pecl.php.net/package/igbinary igbinary}
 * extension.
 *
 * Igbinary produces compact binary output and round-trips arbitrary PHP
 * objects (it implements the same contract as serialize() but with a more
 * efficient encoding). Available only when the `igbinary` extension is
 * loaded; this driver reports {@see self::supported()} accordingly.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
final class Igbinary implements Driver
{
    public function id(): int
    {
        return 4;
    }

    public function supportsPhpObjects(): bool
    {
        return true;
    }

    public function supported(): bool
    {
        return extension_loaded('igbinary');
    }

    public function pack(mixed $data): string
    {
        return igbinary_serialize($data);
    }

    public function unpack(string $data, ?array $allowedClasses = null): mixed
    {
        // igbinary_unserialize has no allowed_classes equivalent; the
        // parameter is accepted for interface compatibility but ignored.
        $out = igbinary_unserialize($data);

        // igbinary returns null on failure as well as for a legitimately
        // packed null. Disambiguate by re-packing null and comparing.
        if ($out !== null || $data === igbinary_serialize(null)) {
            return $out;
        }

        throw new PackException('Error when unpacking igbinary data.');
    }
}
