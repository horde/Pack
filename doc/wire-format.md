# horde/Pack Wire Format

**Status:** Public contract for Pack 2.x.
**Compatibility:** Stable across all 2.x releases shipping the canonical driver set. Values packed by any 1.x release unpack under any 2.x release that ships the same driver set, and the reverse holds too.

## Overview

A packed value is a byte string consisting of:

```
+--------+-----------------------------+
| header |        payload bytes        |
+--------+-----------------------------+
   1 byte         variable
```

The first byte is the header. The remaining bytes are the (optionally
compressed) output of the driver named by the header.

## Header byte

The header is a bit field. Each bit has a fixed meaning:

```
bit  7   6   5   4   3   2   1   0
   +---+---+---+---+---+---+---+---+
   |EXT|CMP| 32| 16|  8|  4|  2|  1|
   +---+---+---+---+---+---+---+---+
       ^^^   ^^^^^^^^^^^^^^^^^^^^^^
       |     |
       |     driver-id slots (FORMAT_BITS)
       |
       compress flag (COMPRESS_MASK)
```

| Bit | Mask (decimal) | Constant | Purpose |
|----:|---------------:|----------|---------|
| 0 | 1 | `FORMAT_BITS[0]` | Driver-id slot 1 (Serialize in canonical set) |
| 1 | 2 | `FORMAT_BITS[1]` | Driver-id slot 2 (Json in canonical set) |
| 2 | 4 | `FORMAT_BITS[2]` | Driver-id slot 4 (Igbinary in canonical set) |
| 3 | 8 | `FORMAT_BITS[3]` | Driver-id slot 8 (MsgpackSerialize in canonical set) |
| 4 | 16 | `FORMAT_BITS[4]` | Driver-id slot 16 (Msgpack in canonical set) |
| 5 | 32 | `FORMAT_BITS[5]` | Driver-id slot 32 (reserved for third-party drivers) |
| 6 | 64 | `COMPRESS_MASK` | Payload bytes are compressed |
| 7 | 128 | `EXTENSION_MASK` | Reserved; if set, the header extends into a second byte |

Constants are exposed as PHP class constants on `Horde\Pack\WireFormat`.

### Driver id

Exactly one of the six driver-id bits must be set in any header emitted by
the canonical drivers. Third-party drivers may register their own
implementation against any unused slot (currently only slot 32 is free in
the canonical set).

A reader that finds a header bit set for a driver it does not know must
fail loudly with a `PackException`. Silently routing the payload to the
wrong driver would mis-decode the bytes and surface as data corruption
elsewhere.

### Compress flag

If bit 6 is set, the payload bytes were run through the `Compressor`
implementation in use (default: `horde/compress_fast`). The reader must
decompress before passing to the driver's unpack.

If bit 6 is not set, the payload bytes go to the driver's unpack as-is.

The compressor is part of the wire-format contract: a payload compressed
by `horde/compress_fast` cannot be decompressed by any other library,
and the reverse holds too. Implementations that swap the compressor in
the public-API sense (via `Horde\Pack\Compressor`) must keep using
`horde/compress_fast`'s compatibility on disk, or arrange a major version
bump.

### Extension flag

Bit 7 is reserved for a future header revision that needs more than the
six driver slots and the compress flag fit in a single byte. No release
emits this bit. Readers MUST reject any payload with bit 7 set rather
than guess at the rest of the header layout.

## Payload bytes

Whatever the driver named in the header produced (or its compressed form
if the compress flag is set). Driver payload formats are not part of this
spec; consult the underlying serialization library for each:

| Driver slot | Canonical driver | Payload format |
|-------------|------------------|----------------|
| 1 | `Horde\Pack\Driver\Serialize` | PHP `serialize()` output |
| 2 | `Horde\Pack\Driver\Json` | One ASCII byte (`'0'` or `'1'`, see below) followed by `json_encode()` output |
| 4 | `Horde\Pack\Driver\Igbinary` | `igbinary_serialize()` output |
| 8 | `Horde\Pack\Driver\MsgpackSerialize` | `msgpack_serialize()` output |
| 16 | `Horde\Pack\Driver\Msgpack` | `msgpack_pack()` output |

### Json driver: array discriminator byte

JSON does not distinguish between an associative array and an object on
decode. To preserve the original PHP shape, the JSON driver prefixes its
payload with a single ASCII byte:

- `'0'` (0x30). The original input was NOT a PHP array. Decode with
  `json_decode($s, false)`.
- `'1'` (0x31). The original input was a PHP array. Decode with
  `json_decode($s, true)`.

This byte is internal to the Json driver. It sits inside the driver's
payload, not in the Pack header.

## Round-trip examples

### Plain `'hello'` packed by msgpack, no compression

```
header byte:   0x10  (driver id 16, no compress)
payload:       msgpack_pack('hello')
```

### Large array packed by Serialize, with compression

```
header byte:   0x41  (driver id 1 | COMPRESS_MASK = 1 + 64)
payload:       horde/compress_fast compression of serialize($value)
```

### Empty input

The string `''` (zero bytes) is a special case at the API level: passing
it to `Packer::unpack()` returns `''` unchanged. This avoids the
boilerplate `if ($v !== '')` test at every cache-read site. Empty input
to `pack()` is not special-cased. It goes through the driver chain like
any other value.

## Stability guarantees

Pack 2.x guarantees:

1. The header layout and the meaning of every bit will not change.
2. The five canonical drivers and their slot assignments will not change.
3. The Json driver's array-discriminator byte will not change.

Anything else (the choice of compressor backend, internal driver
behaviour for edge cases like non-UTF-8 strings, autodetection of PHP
objects on input) is implementation detail and may evolve.

## See also

- `src/WireFormat.php`. The constants definition.
- `src/Packer.php`. The reference reader/writer of this format.
- `src/Driver.php`. The contract a driver must satisfy to be slotted in.
- `test/Modern/InteropTest.php`. Enforces the cross-implementation
  contract test by test on every CI run.
