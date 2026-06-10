# Upgrading horde/Pack

This document covers migration from the legacy `Horde_Pack` PSR-0 API to
the modern `Horde\Pack\Packer` PSR-4 API introduced in 2.0.

## TL;DR

- The two implementations cohabit. The legacy classes are not deprecated
  and not aliased. Existing code keeps working.
- New code should target `Horde\Pack\Packer`.
- Wire format is identical. Migrating call sites does not invalidate
  cached values.
- `allowedClasses` for object deserialization is now a first-class
  per-call option. Default is unrestricted (matches legacy).

## Why two implementations?

The legacy `Horde_Pack` is from 2013. Its public surface relies on
patterns the rest of Horde 6 has moved away from: magic
`__get`/`@property-read` properties, untyped `$opts` arrays,
`FilesystemIterator`-based driver discovery in the constructor and
hidden static caches that block parallel test isolation. Fixing these
in place would break every existing caller.

Instead, 2.0 ships the modern API alongside the legacy one. The legacy
classes stay byte-for-byte where they were. The new code lives in
`src/` under `Horde\Pack\`. The two implementations do not share code
(no inheritance, no `class_alias`, no traits in common) and produce
identical wire-format output. A value packed by either class unpacks
under the other.

This mirrors what `horde/Compress_Fast` did when it moved to PSR-4.

## Name mapping

| Legacy class | Modern class |
|---|---|
| `Horde_Pack` | `Horde\Pack\Packer` |
| `Horde_Pack_Driver` (abstract class) | `Horde\Pack\Driver` (interface) |
| `Horde_Pack_Driver_Serialize` | `Horde\Pack\Driver\Serialize` |
| `Horde_Pack_Driver_Json` | `Horde\Pack\Driver\Json` |
| `Horde_Pack_Driver_Igbinary` | `Horde\Pack\Driver\Igbinary` |
| `Horde_Pack_Driver_Msgpack` | `Horde\Pack\Driver\Msgpack` |
| `Horde_Pack_Driver_Msgpackserialize` | `Horde\Pack\Driver\MsgpackSerialize` |
| `Horde_Pack_Exception` | `Horde\Pack\PackException` |
| `Horde_Pack_Autodetermine` | (private static helper inside `Packer`) |

New types with no legacy peer:

- `Horde\Pack\PackOptions`. Typed per-call options (replaces the
  legacy `$opts` array).
- `Horde\Pack\DriverRegistry` (interface) and
  `Horde\Pack\DefaultDriverRegistry`. Explicit driver discovery.
- `Horde\Pack\Compressor` (interface) and
  `Horde\Pack\DefaultCompressor`. Compressor abstraction.
- `Horde\Pack\WireFormat`. Public constants for the on-disk format.

## Constructor changes

```php
// Legacy.
$pack = new Horde_Pack();

// Modern - same call shape, no required arguments.
$packer = new Horde\Pack\Packer();
```

The modern constructor accepts a `DriverRegistry` and a `Compressor`,
both with sensible defaults. The legacy constructor scanned its own
`Driver/` directory at runtime. The modern one delegates to the
registry, so custom drivers can be registered without dropping files
into the package.

## `pack()` / `unpack()` changes

### Method signatures

```php
// Legacy.
public function pack(mixed $data, array $opts = []): string;
public function unpack(string $data): mixed;

// Modern.
public function pack(mixed $data, ?PackOptions $options = null): string;
public function unpack(string $data, ?PackOptions $options = null): mixed;
```

The unpack method now accepts options too, specifically to plumb the
`allowedClasses` whitelist through to drivers.

### Replacing the `$opts` array

The legacy `$opts` array took three keys, with the `compress` key alone
having four distinct meanings depending on its type. Modern equivalents:

| Legacy `$opts` value | Modern equivalent |
|---|---|
| `[]` (default) | `new PackOptions()` or omit the second argument |
| `['compress' => true]` | `PackOptions::compressed()` (default) |
| `['compress' => false]` | `PackOptions::uncompressed()` |
| `['compress' => 0]` | `PackOptions::alwaysCompress()` |
| `['compress' => 256]` | `PackOptions::compressed(256)` |
| `['drivers' => [Class::class]]` | `(new PackOptions())->withAllowedDrivers(Class::class)` |
| `['phpob' => true]` | `(new PackOptions())->withPhpObjects(true)` |
| `['phpob' => false]` | `(new PackOptions())->withPhpObjects(false)` |

The driver-class strings used in the legacy `drivers` key were the
legacy class names (e.g. `'Horde_Pack_Driver_Json'`). The modern
equivalent uses `Horde\Pack\Driver\Json::class` etc.

## Migration cookbook

### Plain pack/unpack

```diff
-$pack = new Horde_Pack();
-$blob = $pack->pack($value);
-$out = $pack->unpack($blob);
+use Horde\Pack\Packer;
+
+$packer = new Packer();
+$blob = $packer->pack($value);
+$out = $packer->unpack($blob);
```

### Disable compression

```diff
-$blob = $pack->pack($value, ['compress' => false]);
+use Horde\Pack\PackOptions;
+
+$blob = $packer->pack($value, PackOptions::uncompressed());
```

### Restrict drivers

```diff
-$blob = $pack->pack($value, [
-    'drivers' => [
-        'Horde_Pack_Driver_Json',
-        'Horde_Pack_Driver_Serialize',
-    ],
-]);
+use Horde\Pack\Driver\Json;
+use Horde\Pack\Driver\Serialize;
+use Horde\Pack\PackOptions;
+
+$blob = $packer->pack(
+    $value,
+    (new PackOptions())->withAllowedDrivers(Json::class, Serialize::class),
+);
```

### Catch errors

```diff
-} catch (Horde_Pack_Exception $e) {
+use Horde\Pack\PackException;
+
+} catch (PackException $e) {
```

`PackException` extends `Horde\Exception\HordeRuntimeException` (which
extends `\RuntimeException`). The legacy `Horde_Pack_Exception` extended
`Horde_Exception_Wrapped`. Hierarchy-based catches should be reviewed
during migration.

## New: securing object deserialization

The legacy `Horde_Pack_Driver_Serialize::unpack()` called PHP's
`unserialize()` with no class whitelist, exposing a deserialization-
gadget primitive when the cache is attacker-controlled. The modern
driver supports the standard PHP `allowed_classes` whitelist:

```php
use Horde\Pack\PackOptions;

// Reject any object on unpack.
$value = $packer->unpack($blob, (new PackOptions())->withAllowedClasses());

// Allow only specific classes.
$value = $packer->unpack(
    $blob,
    (new PackOptions())->withAllowedClasses(MyDto::class, OtherDto::class),
);
```

The library default is `null` (unrestricted) to match the legacy
behaviour. If you can apply a tighter default at your application's
factory layer, do so. This is the right place to enforce policy
without forcing every caller of `Packer` to remember.

## Things that no longer exist

### `Horde_Pack_Autodetermine`

Replaced by a private static helper inside `Packer`. If you were
calling it directly, switch to `(new PackOptions())->withPhpObjects(...)`
or let `Packer` autodetect.

### `Horde_Pack::__sleep` throwing `LogicException`

The legacy class refused to be PHP-serialised because it cached state
in static properties. The modern `Packer` has no static state, so
serialisation is fine and no longer raises. (You still have no reason
to serialise one, but the language no longer refuses.)

### `FilesystemIterator` driver discovery

Drivers are no longer discovered by scanning a directory at construct
time. Add custom drivers via `DefaultDriverRegistry`'s variadic
constructor (replaces the canonical set) or implement your own
`DriverRegistry`.

## In-tree caller migration

The Horde tree itself contains 14 files across `horde/Core`,
`horde/imp`, and `horde/Imap_Client` that call `Horde_Pack` directly.
These callers continue to work with the legacy `Horde_Pack` from
`lib/`. Migration to `Horde\Pack\Packer` is planned as a separate
follow-up effort, not part of the 2.0 release.

If you maintain a downstream Horde application that calls `Horde_Pack`,
you may continue to do so. There is no scheduled removal of the legacy
classes during the 2.x line.

## Future direction

A 3.0 line, when it ships, will:

- Remove the `lib/` legacy implementation entirely.
- Default `allowedClasses` to deny rather than unrestricted.
- Possibly drop or merge the two msgpack drivers if extension behaviour
  for object types is conclusively pinned down.

These are out of scope for 2.0. The 2.x line is committed to keeping
both implementations and the unrestricted-by-default `allowedClasses`
behaviour.
