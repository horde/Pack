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

/**
 * Contract for a single packing backend.
 *
 * Each driver knows how to serialize a PHP value into a byte string and
 * deserialize a byte string back into the equivalent PHP value. Drivers are
 * pluggable: third parties may ship their own implementations and register
 * them through a {@see DriverRegistry}.
 *
 * Identification on the wire happens through {@see self::id()}: the value is
 * one of {@see WireFormat::FORMAT_BITS} and is written as the first byte of
 * the packed payload (possibly OR'ed with the compress mask). No two
 * drivers registered with the same Packer instance may share an id.
 *
 * The runtime preconditions for a driver (extensions loaded, library
 * available) are reported by {@see self::supported()}. A registry must skip
 * unsupported drivers when offering candidates.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Pack
 */
interface Driver
{
    /**
     * The single-byte wire-format identifier for this driver.
     *
     * Must be one of {@see WireFormat::FORMAT_BITS}. Used both as the
     * priority ranking (higher id = preferred) and as the marker byte in
     * the packed output.
     */
    public function id(): int;

    /**
     * Whether this driver can round-trip arbitrary PHP objects, including
     * those whose state survives only through PHP's serialize() format
     * (private properties, __sleep/__wakeup hooks, custom serialization).
     *
     * Drivers that can only handle scalars, arrays, and stdClass return
     * false here; callers packing real objects must select a driver that
     * returns true.
     */
    public function supportsPhpObjects(): bool;

    /**
     * Whether this driver's runtime requirements are satisfied on the
     * current host (e.g. required extension is loaded).
     *
     * A registry will not offer drivers for which this returns false.
     */
    public function supported(): bool;

    /**
     * Serialize a value into a byte string.
     *
     * @throws PackException If the value cannot be packed.
     */
    public function pack(mixed $data): string;

    /**
     * Deserialize a byte string previously produced by {@see self::pack()}.
     *
     * @param list<class-string>|null $allowedClasses Optional class whitelist
     *   for object deserialization. `null` means unrestricted (the legacy
     *   default); an empty list disables object deserialization entirely
     *   (objects become {@see \__PHP_Incomplete_Class} or arrays); a
     *   non-empty list permits only those classes. Drivers that do not
     *   deserialize PHP objects ignore this parameter.
     *
     * @throws PackException If the payload cannot be unpacked.
     */
    public function unpack(string $data, ?array $allowedClasses = null): mixed;
}
