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

namespace Horde\Pack;

use stdClass;

/**
 * The modern packer facade.
 *
 * A drop-in replacement for the legacy {@see \Horde_Pack} that produces
 * the same on-disk wire format - values packed by either implementation
 * unpack under the other when they share a driver set. Three structural
 * differences from the legacy class:
 *
 *   - No static state. Drivers and the compressor are injected. Two
 *     instances do not share anything; tests can run in parallel.
 *   - The driver list comes from a {@see DriverRegistry}, not from
 *     `FilesystemIterator` discovery. Third parties register their own
 *     drivers without dropping files into the package directory.
 *   - Per-call configuration is a typed {@see PackOptions} value object,
 *     not an untyped associative array.
 *
 * The wire format is documented in `doc/wire-format.md`. Briefly: every
 * packed value begins with a one-byte header carrying the driver id and
 * the compress flag; the remaining bytes are the (optionally compressed)
 * driver payload.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
final class Packer
{
    /**
     * Drivers indexed by id, materialised once at construction time so
     * unpack() does not re-iterate the registry on every call.
     *
     * @var array<int, Driver>
     */
    private readonly array $driversById;

    /**
     * Drivers in priority order (highest id first), materialised once.
     *
     * @var list<Driver>
     */
    private readonly array $driversByPriority;

    public function __construct(
        DriverRegistry $registry = new DefaultDriverRegistry(),
        private readonly Compressor $compressor = new DefaultCompressor(),
    ) {
        $byId = [];
        $byPriority = [];
        foreach ($registry->drivers() as $driver) {
            $id = $driver->id();
            if (isset($byId[$id])) {
                throw new PackException(sprintf(
                    'Driver id %d is registered by both %s and %s.',
                    $id,
                    $byId[$id]::class,
                    $driver::class,
                ));
            }
            $byId[$id] = $driver;
            $byPriority[] = $driver;
        }
        $this->driversById = $byId;
        $this->driversByPriority = $byPriority;
    }

    /**
     * Pack a value into a byte string.
     *
     * Iterates the registered drivers in priority order, skipping any that
     * cannot handle PHP objects when the input requires it, and stops on
     * the first driver whose pack() succeeds. The resulting payload is
     * prefixed with a one-byte header recording the driver id and an
     * optional compress flag.
     *
     * @throws PackException If no registered driver can pack the input.
     */
    public function pack(mixed $data, ?PackOptions $options = null): string
    {
        $options ??= new PackOptions();

        $hasPhpObjects = $options->hasPhpObjects ?? self::containsPhpObjects($data);
        $allowDrivers = $options->allowDrivers;

        foreach ($this->driversByPriority as $driver) {
            if ($hasPhpObjects && !$driver->supportsPhpObjects()) {
                continue;
            }

            if ($allowDrivers !== null && !in_array($driver::class, $allowDrivers, true)) {
                continue;
            }

            try {
                $packed = $driver->pack($data);
            } catch (PackException) {
                // The driver rejected the input (e.g. JSON refusing a
                // non-UTF-8 string). Try the next one.
                continue;
            }

            $header = $driver->id();

            if ($options->compressThreshold !== null) {
                $shouldCompress = $options->compressThreshold === 0
                    || strlen($packed) > $options->compressThreshold;

                if ($shouldCompress) {
                    $packed = $this->compressor->compress($packed);
                    $header |= WireFormat::COMPRESS_MASK;
                }
            }

            return pack('C', $header) . $packed;
        }

        throw new PackException('No registered driver could pack the input.');
    }

    /**
     * Unpack a byte string previously produced by {@see self::pack()} or
     * by a wire-format-compatible legacy {@see \Horde_Pack}.
     *
     * Empty input returns the input unchanged - matches legacy behaviour
     * where consumers commonly call unpack() on whatever they pulled from
     * a cache, including the empty string for a missing entry.
     *
     * @throws PackException If the payload's header references a driver
     *   not registered with this Packer instance, or the driver itself
     *   rejects the payload.
     */
    public function unpack(string $data, ?PackOptions $options = null): mixed
    {
        if ($data === '') {
            return $data;
        }

        $header = ord($data[0]);
        $payload = substr($data, 1);

        if (($header & WireFormat::COMPRESS_MASK) !== 0) {
            $payload = $this->compressor->decompress($payload);
            $header &= ~WireFormat::COMPRESS_MASK;
        }

        if (($header & WireFormat::EXTENSION_MASK) !== 0) {
            // Reserved for a future format that extends the header into a
            // second byte. No driver shipped today emits this, so until
            // the layout is specified we reject it loudly rather than
            // mis-route to a present-day driver id.
            throw new PackException(
                'Wire-format extension bit is set; no driver in this build understands it.',
            );
        }

        if (!isset($this->driversById[$header])) {
            throw new PackException(sprintf(
                'No driver registered for wire-format id %d.',
                $header,
            ));
        }

        return $this->driversById[$header]->unpack(
            $payload,
            $options?->allowedClasses,
        );
    }

    /**
     * True if $data is or contains a real PHP object (anything other than
     * a stdClass with stdClass-or-array contents).
     *
     * Used to pick a driver capable of round-tripping objects via PHP's
     * serialize() representation when the caller has not asserted the
     * answer through {@see PackOptions::withPhpObjects()}.
     */
    private static function containsPhpObjects(mixed $data): bool
    {
        if (is_object($data)) {
            return $data instanceof stdClass
                ? self::containsPhpObjects((array) $data)
                : true;
        }

        if (is_array($data)) {
            foreach ($data as $value) {
                if (self::containsPhpObjects($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
