<?php

declare(strict_types=1);

namespace App\Services\Bintex;

use RuntimeException;

/**
 * PHP port of yuku.bintex.BintexReader — reads the Bintex binary format.
 *
 * All multi-byte integers are BIG-ENDIAN.
 * VarUint uses a custom prefix encoding (NOT protobuf varint).
 */
class BintexReader
{
    // Type map: 1=int, 2=string, 3=int[], 4=simple map
    private const TYPE_MAP = [
        //  .0 .1 .2 .3 .4 .5 .6 .7 .8 .9 .a .b .c .d .e .f
        0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 2, 2, 1, 1, // 0x
        1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 1x
        1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 2x
        1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 3x
        1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 4x
        0, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, // 5x
        0, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, // 6x
        2, 2, 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 7x
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 8x
        4, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // 9x
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // ax
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // bx
        3, 3, 0, 0, 3, 0, 0, 0, 3, 3, 0, 0, 3, 0, 0, 0, // cx
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // dx
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // ex
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, // fx
    ];

    private string $data;
    private int $pos = 0;
    private int $length;

    public function __construct(string $data, int $offset = 0)
    {
        $this->data = $data;
        $this->pos = $offset;
        $this->length = strlen($data);
    }

    public function getPos(): int
    {
        return $this->pos;
    }

    public function setPos(int $pos): void
    {
        $this->pos = $pos;
    }

    public function remaining(): int
    {
        return $this->length - $this->pos;
    }

    public function readUint8(): int
    {
        if ($this->pos >= $this->length) {
            throw new RuntimeException('Unexpected end of data reading uint8');
        }
        $val = ord($this->data[$this->pos]);
        $this->pos++;
        return $val;
    }

    public function readUint16(): int
    {
        if ($this->pos + 2 > $this->length) {
            throw new RuntimeException('Unexpected end of data reading uint16');
        }
        $val = (ord($this->data[$this->pos]) << 8) | ord($this->data[$this->pos + 1]);
        $this->pos += 2;
        return $val;
    }

    /**
     * Read a 32-bit signed big-endian integer.
     */
    public function readInt(): int
    {
        if ($this->pos + 4 > $this->length) {
            throw new RuntimeException('Unexpected end of data reading int32');
        }
        $val = unpack('N', substr($this->data, $this->pos, 4))[1];
        $this->pos += 4;
        // Convert unsigned 32-bit to signed
        if ($val >= 0x80000000) {
            $val -= 0x100000000;
        }
        return (int) $val;
    }

    public function readRaw(int $length): string
    {
        if ($this->pos + $length > $this->length) {
            throw new RuntimeException("Unexpected end of data reading {$length} raw bytes");
        }
        $data = substr($this->data, $this->pos, $length);
        $this->pos += $length;
        return $data;
    }

    /**
     * Read a custom variable-length unsigned int.
     * Prefix encoding: NOT protobuf varint.
     */
    public function readVarUint(): int
    {
        $first = $this->readUint8();
        if (($first & 0x80) === 0) {
            // 0xxxxxxx — 7 bits
            return $first;
        }
        if (($first & 0xc0) === 0x80) {
            // 10xxxxxx — 14 bits
            $next0 = $this->readUint8();
            return (($first & 0x3f) << 8) | $next0;
        }
        if (($first & 0xe0) === 0xc0) {
            // 110xxxxx — 21 bits
            $next1 = $this->readUint8();
            $next0 = $this->readUint8();
            return (($first & 0x1f) << 16) | ($next1 << 8) | $next0;
        }
        if (($first & 0xf0) === 0xe0) {
            // 1110xxxx — 28 bits
            $next2 = $this->readUint8();
            $next1 = $this->readUint8();
            $next0 = $this->readUint8();
            return (($first & 0x0f) << 24) | ($next2 << 16) | ($next1 << 8) | $next0;
        }
        if ($first === 0xf0) {
            // 11110000 — full 32 bits
            $next3 = $this->readUint8();
            $next2 = $this->readUint8();
            $next1 = $this->readUint8();
            $next0 = $this->readUint8();
            return ($next3 << 24) | ($next2 << 16) | ($next1 << 8) | $next0;
        }
        throw new RuntimeException("Unknown first byte in varuint: {$first}");
    }

    /**
     * Read a typed integer value.
     */
    public function readValueInt(): int
    {
        $t = $this->readUint8();
        return $this->decodeValueInt($t);
    }

    private function decodeValueInt(int $t): int
    {
        return match (true) {
            $t === 0x0e => 0,
            $t >= 0x01 && $t <= 0x07 => $t,
            $t === 0x0f => -1,
            $t === 0x10 || $t === 0x11 => $this->decodeSignedBytes($t, 1),
            $t === 0x20 || $t === 0x21 => $this->decodeSignedBytes($t, 2),
            $t === 0x30 || $t === 0x31 => $this->decodeSignedBytes($t, 3),
            $t === 0x40 || $t === 0x41 => $this->decodeSignedBytes($t, 4),
            default => throw new RuntimeException(sprintf('Value is not int: type=0x%02x', $t)),
        };
    }

    private function decodeSignedBytes(int $t, int $byteCount): int
    {
        $a = 0;
        for ($i = $byteCount - 1; $i >= 0; $i--) {
            $a |= ($this->readUint8() << ($i * 8));
        }
        // Odd type tags use bitwise NOT (~) for negative encoding
        return ($t & 1) ? ~$a : $a;
    }

    /**
     * Read a typed string value.
     */
    public function readValueString(): ?string
    {
        $t = $this->readUint8();
        return $this->decodeValueString($t);
    }

    private function decodeValueString(int $t): ?string
    {
        return match (true) {
            $t === 0x0c => null,
            $t === 0x0d => '',
            $t >= 0x51 && $t <= 0x5f => $this->read8BitString($t & 0x0f),
            $t >= 0x61 && $t <= 0x6f => $this->read16BitString($t & 0x0f),
            $t === 0x70 => $this->read8BitString($this->readUint8()),
            $t === 0x71 => $this->read16BitString($this->readUint8()),
            $t === 0x72 => $this->read8BitString($this->readInt()),
            $t === 0x73 => $this->read16BitString($this->readInt()),
            default => throw new RuntimeException(sprintf('Value is not string: type=0x%02x', $t)),
        };
    }

    /**
     * Read an 8-bit (Latin-1) encoded string.
     */
    private function read8BitString(int $len): string
    {
        if ($len === 0) {
            return '';
        }
        $raw = $this->readRaw($len);
        // Java's new String(bytes, 0, offset, len) with charset 0 is ISO-8859-1
        return mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Read a 16-bit (UTF-16BE) encoded string.
     */
    private function read16BitString(int $len): string
    {
        if ($len === 0) {
            return '';
        }
        $raw = $this->readRaw($len * 2);
        return mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
    }

    /**
     * Read a short string: 1-byte length prefix, then UTF-16BE chars.
     */
    public function readShortString(): string
    {
        $len = $this->readUint8();
        if ($len === 0) {
            return '';
        }
        return $this->read16BitString($len);
    }

    /**
     * Read a long string: 4-byte int length, then UTF-16BE chars.
     */
    public function readLongString(): string
    {
        $len = $this->readInt();
        if ($len === 0) {
            return '';
        }
        return $this->read16BitString($len);
    }

    /**
     * Read an auto-detected string (8-bit or 16-bit, short or long).
     */
    public function readAutoString(): ?string
    {
        $kind = $this->readUint8();
        $len = match (true) {
            $kind === 0x01, $kind === 0x02 => $this->readUint8(),
            $kind === 0x11, $kind === 0x12 => $this->readInt(),
            default => 0,
        };

        if ($kind === 0x01 || $kind === 0x11) {
            return $this->read8BitString($len);
        }
        if ($kind === 0x02 || $kind === 0x12) {
            return $this->read16BitString($len);
        }
        return null;
    }

    /**
     * Read a typed int array (uint8[], uint16[], or int32[]).
     */
    public function readValueIntArray(): array
    {
        $t = $this->readUint8();
        return $this->decodeValueIntArray($t);
    }

    /**
     * @return int[]
     */
    private function decodeValueIntArray(int $t): array
    {
        // Delegate to specialized readers for uint8 and uint16
        if ($t === 0xc0 || $t === 0xc8) {
            return $this->decodeValueUint8Array($t);
        }
        if ($t === 0xc1 || $t === 0xc9) {
            return $this->decodeValueUint16Array($t);
        }

        // int32 array
        $len = match ($t) {
            0xc4 => $this->readUint8(),
            0xcc => $this->readInt(),
            default => throw new RuntimeException(sprintf('Value is not int array: type=0x%02x', $t)),
        };

        $result = [];
        for ($i = 0; $i < $len; $i++) {
            $result[] = $this->readInt();
        }
        return $result;
    }

    /**
     * @return int[]
     */
    private function decodeValueUint8Array(int $t): array
    {
        $len = match ($t) {
            0xc0 => $this->readUint8(),
            0xc8 => $this->readInt(),
            default => throw new RuntimeException(sprintf('Value is not uint8 array: type=0x%02x', $t)),
        };
        $raw = $this->readRaw($len);
        $result = [];
        for ($i = 0; $i < $len; $i++) {
            $result[] = ord($raw[$i]);
        }
        return $result;
    }

    /**
     * @return int[]
     */
    private function decodeValueUint16Array(int $t): array
    {
        $len = match ($t) {
            0xc1 => $this->readUint8(),
            0xc9 => $this->readInt(),
            default => throw new RuntimeException(sprintf('Value is not uint16 array: type=0x%02x', $t)),
        };
        $result = [];
        for ($i = 0; $i < $len; $i++) {
            $result[] = $this->readUint16();
        }
        return $result;
    }

    /**
     * Read a simple map (string keys → mixed values).
     *
     * @return array<string, mixed>
     */
    public function readValueSimpleMap(): array
    {
        $t = $this->readUint8();
        return $this->decodeValueSimpleMap($t);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeValueSimpleMap(int $t): array
    {
        if ($t === 0x90) {
            return [];
        }
        if ($t !== 0x91) {
            throw new RuntimeException(sprintf('Value is not simple map: type=0x%02x', $t));
        }
        $size = $this->readUint8();
        $result = [];
        for ($i = 0; $i < $size; $i++) {
            $keyLen = $this->readUint8();
            $key = $this->read8BitString($keyLen);
            $value = $this->readValue();
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Read a generic typed value (int, string, int array, or simple map).
     */
    public function readValue(): mixed
    {
        $t = $this->readUint8();
        $type = self::TYPE_MAP[$t] ?? 0;
        return match ($type) {
            1 => $this->decodeValueInt($t),
            2 => $this->decodeValueString($t),
            3 => $this->decodeValueIntArray($t),
            4 => $this->decodeValueSimpleMap($t),
            default => throw new RuntimeException(sprintf('Value has unknown type: type=0x%02x', $t)),
        };
    }

    public function skip(int $n): void
    {
        $this->pos += $n;
    }
}
