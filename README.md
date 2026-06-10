# horde/Pack

Data packing library that picks the fastest serialization format your
PHP installation supports for each value, then optionally compresses
the result.

A drop-in replacement for `serialize()`/`json_encode()` for cache,
session and inter-process payloads. Pack autodetects whether the
input contains real PHP objects and chooses a driver that can round-
trip it. Compression is on by default for payloads above a configurable
size threshold.

## Installation

```sh
composer require horde/pack
```

Optional extensions, in priority order (Pack works without any of them
but smaller and faster ones come from native code):

- `ext-msgpack`. Highest priority for plain data (scalars, arrays).
- `ext-igbinary`. High priority for payloads containing real PHP
  objects.
- `ext-json`. Always available with PHP since 8.0. Second-priority
  for plain data.
- PHP `serialize()`. Always available. Universal fallback.

## Two implementations side by side

The `2.x` line ships **two parallel implementations** in one Composer
package:

- **Modern PSR-4** under `Horde\Pack\` in `src/`. Use this for new code.
- **Legacy PSR-0** under `Horde_Pack` in `lib/`. Existing callers keep
  working unchanged.

Both implementations share one wire format: a value packed by either
class unpacks under the other. See [`doc/wire-format.md`](doc/wire-format.md)
for the on-disk specification. They cohabit in caches and session
blobs without coordination.

The legacy classes are in maintenance mode: bug fixes land if reported,
but no new features. New work should target `Horde\Pack\Packer`.

## Quick start (modern API)

```php
use Horde\Pack\Packer;
use Horde\Pack\PackOptions;

$packer = new Packer();

// Round-trip a value.
$blob = $packer->pack(['cache_key' => 'some data']);
$value = $packer->unpack($blob);

// Disable compression for short or already-compressed inputs.
$blob = $packer->pack($payload, PackOptions::uncompressed());

// Restrict object deserialization to a class whitelist (security).
$value = $packer->unpack(
    $blob,
    (new PackOptions())->withAllowedClasses(MyDto::class),
);
```

## Configuring the packer

Constructor parameters are typed and have sensible defaults:

```php
use Horde\Pack\DefaultDriverRegistry;
use Horde\Pack\DefaultCompressor;
use Horde\Pack\Driver\Json;
use Horde\Pack\Driver\Serialize;
use Horde\Pack\Packer;

// Default: every shipped driver supported by the host, fast compressor.
$packer = new Packer();

// Restricted driver set: only Json and Serialize.
$packer = new Packer(
    new DefaultDriverRegistry(new Json(), new Serialize()),
);

// Custom compressor (must implement Horde\Pack\Compressor).
$packer = new Packer(
    new DefaultDriverRegistry(),
    $myCompressor,
);
```

## Per-call options

`PackOptions` is an immutable value object with named constructors and
`with*()` builders:

| Factory / builder | Effect |
|---|---|
| `PackOptions::compressed($threshold = 128)` | Compress payloads larger than threshold (the default). |
| `PackOptions::uncompressed()` | Never compress. |
| `PackOptions::alwaysCompress()` | Compress every payload regardless of size. |
| `withAllowedDrivers(string ...$classes)` | Restrict to listed driver classes. |
| `withAllowedClasses(string ...$classes)` | Restrict object deserialization to listed classes. Empty list disables object deserialization entirely. |
| `withPhpObjects(bool)` | Skip the autodetect scan and assert whether input contains real PHP objects. |

## Security: `allowedClasses`

`Driver\Serialize::unpack()` honours an `allowed_classes` whitelist when
deserializing PHP objects. Tightening this whitelist closes a
deserialization-gadget primitive that an attacker who can corrupt the
cache otherwise has access to.

The library default is `null` (unrestricted) for backwards compatibility
with the legacy `Horde_Pack`. Application factories that wrap `Packer`
are the right layer to apply default-deny:

```php
final class HardenedPackerFactory
{
    public static function create(): Packer
    {
        return new Packer();
    }

    public static function safelyUnpack(Packer $packer, string $blob): mixed
    {
        return $packer->unpack(
            $blob,
            (new PackOptions())->withAllowedClasses(MyAllowedDto::class),
        );
    }
}
```

The other shipped drivers (Json, Igbinary, Msgpack, MsgpackSerialize) do
not honour `allowedClasses`. Json refuses to encode non-stdClass objects
in the first place, so the whitelist is moot there. The msgpack and
igbinary extensions provide no class-whitelist hook in their
deserializers. The option is accepted for interface compatibility but
ignored. Callers who need a hard whitelist must restrict the driver set
to `Serialize` only via `withAllowedDrivers()`.

## Custom drivers

Implement `Horde\Pack\Driver` and register your driver against an
unused wire-format slot. The canonical drivers occupy slots 1, 2, 4, 8
and 16. Slot 32 is reserved for third-party drivers in the canonical
registry layout.

```php
use Horde\Pack\Driver;
use Horde\Pack\WireFormat;

final class CborDriver implements Driver
{
    public function id(): int { return 32; }
    public function supportsPhpObjects(): bool { return false; }
    public function supported(): bool { return extension_loaded('cbor'); }
    public function pack(mixed $data): string { /* ... */ }
    public function unpack(string $data, ?array $allowedClasses = null): mixed { /* ... */ }
}

$packer = new Packer(
    new DefaultDriverRegistry(
        new CborDriver(),
        new Driver\Serialize(),
        new Driver\Json(),
    ),
);
```

`DefaultDriverRegistry`'s variadic constructor REPLACES the canonical
driver set entirely when called with arguments. Pass the canonical
drivers explicitly alongside your own if you want to extend rather than
replace.

## See also

- [`doc/wire-format.md`](doc/wire-format.md) - on-disk format specification.
- [`doc/UPGRADING.md`](doc/UPGRADING.md) - migrating from the legacy
  `Horde_Pack` API to the modern `Horde\Pack\Packer`.

## License

LGPL 2.1. See `LICENSE`.
