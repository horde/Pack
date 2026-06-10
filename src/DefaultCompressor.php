<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack;

use Horde\Compress\Fast\CompressFast;

/**
 * Default {@see Compressor} backed by {@see CompressFast}.
 *
 * Wraps the `horde/compress_fast` library. The wrapper exists for two
 * reasons: it keeps Packer's public surface free of the compress_fast type
 * (so a future replacement is purely an implementation detail), and it
 * lets tests substitute a no-op or fault-injecting compressor without
 * monkey-patching extension internals.
 *
 * The constructor accepts an optional {@see CompressFast} instance, so
 * callers who already configure the underlying library (e.g. with explicit
 * driver options) can hand it in pre-built. The default constructor lets
 * compress_fast pick the best driver available on the current host.
 */
final class DefaultCompressor implements Compressor
{
    public function __construct(
        private readonly CompressFast $inner = new CompressFast(),
    ) {}

    public function compress(string $data): string
    {
        return $this->inner->compress($data);
    }

    public function decompress(string $data): string
    {
        return $this->inner->decompress($data);
    }
}
