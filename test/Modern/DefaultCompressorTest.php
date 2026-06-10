<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern;

use Horde\Pack\DefaultCompressor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultCompressor::class)]
final class DefaultCompressorTest extends TestCase
{
    public function testRoundTripsPayload(): void
    {
        $compressor = new DefaultCompressor();

        // Compressible payload: lots of repetition so the compressor has
        // something to chew on. The exact compressed form is up to the
        // selected backend - only the round-trip is contract.
        $payload = str_repeat('horde-pack-modernization ', 200);

        $compressed = $compressor->compress($payload);
        $this->assertSame($payload, $compressor->decompress($compressed));
    }

    public function testEmptyStringRoundTrips(): void
    {
        $compressor = new DefaultCompressor();
        $this->assertSame('', $compressor->decompress($compressor->compress('')));
    }
}
