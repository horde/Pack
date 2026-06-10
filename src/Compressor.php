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
 * Contract for a {@see Packer}-side compressor.
 *
 * Packer delegates compression to an instance of this interface so the
 * library is not coupled to the concrete `horde/compress_fast` type in
 * its public surface. The default shipped implementation is
 * {@see DefaultCompressor}, which wraps {@see \Horde\Compress\Fast\CompressFast}.
 *
 * The contract is symmetric: {@see self::decompress()} must return the
 * original input of {@see self::compress()} byte for byte. Both are
 * called with raw payload bytes - neither is responsible for the
 * Packer header byte that records the {@see WireFormat::COMPRESS_MASK} bit.
 */
interface Compressor
{
    /**
     * Compress payload bytes.
     */
    public function compress(string $data): string;

    /**
     * Decompress payload bytes previously produced by {@see self::compress()}.
     */
    public function decompress(string $data): string;
}
