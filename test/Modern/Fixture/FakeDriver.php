<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Pack\Test\Modern\Fixture;

use Horde\Pack\Driver;

/**
 * In-memory driver fixture.
 *
 * Provides a {@see Driver} whose id, support flag, and PHP-object support
 * are all set at construction time. Lets tests cover priority ordering
 * and supported() filtering in {@see \Horde\Pack\DefaultDriverRegistry}
 * without relying on the host's extension footprint.
 *
 * The pack/unpack methods round-trip via base64 of the PHP-serialise
 * representation - sufficient for fixture purposes; not intended for
 * real-world use.
 */
final class FakeDriver implements Driver
{
    public function __construct(
        private readonly int $id,
        private readonly bool $supported = true,
        private readonly bool $supportsPhpObjects = false,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function supportsPhpObjects(): bool
    {
        return $this->supportsPhpObjects;
    }

    public function supported(): bool
    {
        return $this->supported;
    }

    public function pack(mixed $data): string
    {
        return base64_encode(serialize($data));
    }

    public function unpack(string $data, ?array $allowedClasses = null): mixed
    {
        $decoded = base64_decode($data, true);
        return $decoded === false ? null : unserialize($decoded, ['allowed_classes' => $allowedClasses ?? true]);
    }
}
