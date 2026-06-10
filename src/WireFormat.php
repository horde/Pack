<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack;

/**
 * Constants describing the Pack wire format.
 *
 * Every packed value begins with a one-byte header. The bits of that header
 * carry three pieces of information:
 *
 *   - One of {@see self::FORMAT_BITS} identifies which {@see Driver} produced
 *     the payload. Each driver owns exactly one bit; new drivers added by
 *     third parties must claim an unused slot from this list.
 *   - {@see self::COMPRESS_MASK} (bit 64), if set, indicates the payload was
 *     run through a {@see Compressor} after the driver packed it.
 *   - {@see self::EXTENSION_MASK} (bit 128) is reserved. If a future format
 *     revision needs to encode more than the six driver slots and one
 *     compress flag fit in a single byte, this bit signals that the header
 *     extends into a second byte. No driver currently emits this.
 *
 * The wire format is part of the public contract: any value packed by a
 * Pack 1.x or 2.x release must round-trip through any other release of the
 * same major version that ships the same driver set.
 *
 * See `doc/wire-format.md` for the full specification.
 */
final class WireFormat
{
    /**
     * Driver-id slot bits. A driver's id() must be one of these values.
     *
     * @var list<int>
     */
    public const FORMAT_BITS = [1, 2, 4, 8, 16, 32];

    /**
     * Header bit indicating the payload bytes are compressed.
     */
    public const COMPRESS_MASK = 64;

    /**
     * Header bit reserved for a future header-extension format. If set, the
     * id occupies more than one byte. No current driver uses this.
     */
    public const EXTENSION_MASK = 128;

    /**
     * Default compression threshold in bytes. Payloads at or below this size
     * are not worth the CPU cost of compression.
     */
    public const DEFAULT_COMPRESS_THRESHOLD = 128;

    /**
     * Not instantiable.
     */
    private function __construct() {}
}
