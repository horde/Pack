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
 * Per-call configuration for {@see Packer::pack()} and {@see Packer::unpack()}.
 *
 * Replaces the legacy `$opts` array, whose `compress` key alone meant four
 * different things depending on its type. Here each concern is a separate
 * named field with explicit semantics:
 *
 *   - {@see self::$compressThreshold}: when, if ever, to compress the
 *     packed payload (`null` = never; `0` = always; `>0` = only if the
 *     packed payload exceeds this many bytes).
 *   - {@see self::$allowDrivers}: restrict which drivers Packer may use.
 *     `null` lets it consider every driver yielded by the registry.
 *   - {@see self::$hasPhpObjects}: bypass the input scan that decides
 *     whether the payload may contain real PHP objects. Useful when the
 *     caller already knows.
 *   - {@see self::$allowedClasses}: passed through to drivers on unpack.
 *     Tightening this whitelist closes a deserialization-gadget primitive
 *     that an attacker who controls the cache otherwise has access to.
 *     `null` (the default) preserves legacy unrestricted behaviour; the
 *     factory wrapping `Packer` for a given application is the right place
 *     to apply default-deny.
 *
 * Instances are immutable; the named constructors and `with*()` helpers
 * return new instances.
 */
final class PackOptions
{
    /**
     * @param int|null $compressThreshold null = never compress, 0 = always
     *   compress, >0 = compress only if packed payload exceeds N bytes.
     * @param list<class-string>|null $allowDrivers null = any registered
     *   driver; non-null = restrict to listed driver classes.
     * @param bool|null $hasPhpObjects null = autodetect by scanning input;
     *   true|false = caller asserts.
     * @param list<class-string>|null $allowedClasses null = unrestricted
     *   (legacy behaviour); empty list = no classes deserialised; non-empty
     *   list = whitelist only.
     */
    public function __construct(
        public readonly ?int $compressThreshold = WireFormat::DEFAULT_COMPRESS_THRESHOLD,
        public readonly ?array $allowDrivers = null,
        public readonly ?bool $hasPhpObjects = null,
        public readonly ?array $allowedClasses = null,
    ) {}

    /**
     * Compress only if the packed payload exceeds the given size.
     *
     * The default threshold ({@see WireFormat::DEFAULT_COMPRESS_THRESHOLD})
     * is the breakeven point below which compression overhead exceeds the
     * size win for typical packed payloads.
     */
    public static function compressed(
        int $threshold = WireFormat::DEFAULT_COMPRESS_THRESHOLD,
    ): self {
        return new self(compressThreshold: $threshold);
    }

    /**
     * Skip compression unconditionally.
     */
    public static function uncompressed(): self
    {
        return new self(compressThreshold: null);
    }

    /**
     * Compress every payload regardless of size.
     */
    public static function alwaysCompress(): self
    {
        return new self(compressThreshold: 0);
    }

    /**
     * Restrict pack() to the given drivers (by class name).
     *
     * @param class-string ...$drivers
     * @return self New instance, original unchanged.
     */
    public function withAllowedDrivers(string ...$drivers): self
    {
        return new self(
            compressThreshold: $this->compressThreshold,
            allowDrivers: array_values($drivers),
            hasPhpObjects: $this->hasPhpObjects,
            allowedClasses: $this->allowedClasses,
        );
    }

    /**
     * Restrict object deserialization to the given class whitelist.
     *
     * Pass an empty argument list (`withAllowedClasses()`) to disable
     * object deserialization entirely (objects become
     * {@see \__PHP_Incomplete_Class}).
     *
     * @param class-string ...$classes
     * @return self New instance, original unchanged.
     */
    public function withAllowedClasses(string ...$classes): self
    {
        return new self(
            compressThreshold: $this->compressThreshold,
            allowDrivers: $this->allowDrivers,
            hasPhpObjects: $this->hasPhpObjects,
            allowedClasses: array_values($classes),
        );
    }

    /**
     * Assert whether the input contains real PHP objects.
     *
     * Skips the scan-the-payload autodetect step, which can be a
     * meaningful saving for large or deeply-nested inputs the caller
     * already knows the shape of.
     *
     * @return self New instance, original unchanged.
     */
    public function withPhpObjects(bool $hasPhpObjects): self
    {
        return new self(
            compressThreshold: $this->compressThreshold,
            allowDrivers: $this->allowDrivers,
            hasPhpObjects: $hasPhpObjects,
            allowedClasses: $this->allowedClasses,
        );
    }
}
