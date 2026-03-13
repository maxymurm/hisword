package org.androidbible.data.bintex

/**
 * Kotlin port of yuku.bintex.BintexReader — reads the Bintex binary format.
 *
 * All multi-byte integers are BIG-ENDIAN.
 * VarUint uses a custom prefix encoding (NOT protobuf varint).
 */
class BintexReader(private val data: ByteArray, offset: Int = 0) {

    var pos: Int = offset
        private set

    val remaining: Int get() = data.size - pos

    fun readUint8(): Int {
        check(pos < data.size) { "Unexpected end of data reading uint8" }
        val v = data[pos].toInt() and 0xFF
        pos++
        return v
    }

    fun readUint16(): Int {
        check(pos + 2 <= data.size) { "Unexpected end of data reading uint16" }
        val v = ((data[pos].toInt() and 0xFF) shl 8) or (data[pos + 1].toInt() and 0xFF)
        pos += 2
        return v
    }

    /** Read a 32-bit signed big-endian integer. */
    fun readInt(): Int {
        check(pos + 4 <= data.size) { "Unexpected end of data reading int32" }
        val v = ((data[pos].toInt() and 0xFF) shl 24) or
            ((data[pos + 1].toInt() and 0xFF) shl 16) or
            ((data[pos + 2].toInt() and 0xFF) shl 8) or
            (data[pos + 3].toInt() and 0xFF)
        pos += 4
        return v
    }

    fun readRaw(length: Int): ByteArray {
        check(pos + length <= data.size) { "Unexpected end of data reading $length raw bytes" }
        val result = data.copyOfRange(pos, pos + length)
        pos += length
        return result
    }

    fun skip(n: Int) {
        pos += n
    }

    fun seek(position: Int) {
        pos = position
    }

    /**
     * Read a custom variable-length unsigned int.
     * Prefix encoding: NOT protobuf varint.
     */
    fun readVarUint(): Int {
        val first = readUint8()
        if ((first and 0x80) == 0) {
            // 0xxxxxxx — 7 bits
            return first
        }
        if ((first and 0xC0) == 0x80) {
            // 10xxxxxx — 14 bits
            val next0 = readUint8()
            return ((first and 0x3F) shl 8) or next0
        }
        if ((first and 0xE0) == 0xC0) {
            // 110xxxxx — 21 bits
            val next1 = readUint8()
            val next0 = readUint8()
            return ((first and 0x1F) shl 16) or (next1 shl 8) or next0
        }
        if ((first and 0xF0) == 0xE0) {
            // 1110xxxx — 28 bits
            val next2 = readUint8()
            val next1 = readUint8()
            val next0 = readUint8()
            return ((first and 0x0F) shl 24) or (next2 shl 16) or (next1 shl 8) or next0
        }
        if (first == 0xF0) {
            // 11110000 — full 32 bits
            val next3 = readUint8()
            val next2 = readUint8()
            val next1 = readUint8()
            val next0 = readUint8()
            return (next3 shl 24) or (next2 shl 16) or (next1 shl 8) or next0
        }
        error("Unknown first byte in varuint: $first")
    }

    /** Read a typed integer value. */
    fun readValueInt(): Int {
        val t = readUint8()
        return decodeValueInt(t)
    }

    private fun decodeValueInt(t: Int): Int = when {
        t == 0x0E -> 0
        t in 0x01..0x07 -> t
        t == 0x0F -> -1
        t == 0x10 || t == 0x11 -> decodeSignedBytes(t, 1)
        t == 0x20 || t == 0x21 -> decodeSignedBytes(t, 2)
        t == 0x30 || t == 0x31 -> decodeSignedBytes(t, 3)
        t == 0x40 || t == 0x41 -> decodeSignedBytes(t, 4)
        else -> error("Value is not int: type=0x${t.toString(16).padStart(2, '0')}")
    }

    private fun decodeSignedBytes(t: Int, byteCount: Int): Int {
        var a = 0
        for (i in (byteCount - 1) downTo 0) {
            a = a or (readUint8() shl (i * 8))
        }
        return if ((t and 1) != 0) a.inv() else a
    }

    /** Read a typed string value. */
    fun readValueString(): String? {
        val t = readUint8()
        return decodeValueString(t)
    }

    private fun decodeValueString(t: Int): String? = when {
        t == 0x0C -> null
        t == 0x0D -> ""
        t in 0x51..0x5F -> read8BitString(t and 0x0F)
        t in 0x61..0x6F -> read16BitString(t and 0x0F)
        t == 0x70 -> read8BitString(readUint8())
        t == 0x71 -> read16BitString(readUint8())
        t == 0x72 -> read8BitString(readInt())
        t == 0x73 -> read16BitString(readInt())
        else -> error("Value is not string: type=0x${t.toString(16).padStart(2, '0')}")
    }

    private fun read8BitString(len: Int): String {
        // ISO-8859-1: each byte is a Unicode code point 0-255
        val chars = CharArray(len) { readUint8().toChar() }
        return String(chars)
    }

    private fun read16BitString(len: Int): String {
        // UTF-16 BE: 2 bytes per char
        val chars = CharArray(len) { readUint16().toChar() }
        return String(chars)
    }

    /** Read typed uint8 array. */
    fun readValueUint8Array(): IntArray {
        val t = readUint8()
        return decodeValueUint8Array(t)
    }

    private fun decodeValueUint8Array(t: Int): IntArray {
        val len = when (t) {
            0xC0 -> readUint8()
            0xC8 -> readInt()
            else -> error("Value is not uint8 array: type=0x${t.toString(16).padStart(2, '0')}")
        }
        return IntArray(len) { readUint8() }
    }

    /** Read typed uint16 array. */
    fun readValueUint16Array(): IntArray {
        val t = readUint8()
        return decodeValueUint16Array(t)
    }

    private fun decodeValueUint16Array(t: Int): IntArray {
        val len = when (t) {
            0xC1 -> readUint8()
            0xC9 -> readInt()
            else -> error("Value is not uint16 array: type=0x${t.toString(16).padStart(2, '0')}")
        }
        return IntArray(len) { readUint16() }
    }

    /** Read typed int32 array (also handles uint8 and uint16 arrays). */
    fun readValueIntArray(): IntArray {
        val t = readUint8()
        return decodeValueIntArray(t)
    }

    private fun decodeValueIntArray(t: Int): IntArray = when (t) {
        0xC0, 0xC8 -> decodeValueUint8Array(t)
        0xC1, 0xC9 -> decodeValueUint16Array(t)
        0xC4 -> {
            val len = readUint8()
            IntArray(len) { readInt() }
        }
        0xCC -> {
            val len = readInt()
            IntArray(len) { readInt() }
        }
        else -> error("Value is not int array: type=0x${t.toString(16).padStart(2, '0')}")
    }

    /** Read a typed SimpleMap (key-value where keys are 8-bit strings). */
    fun readValueSimpleMap(): Map<String, Any?> {
        val t = readUint8()
        return decodeValueSimpleMap(t)
    }

    private fun decodeValueSimpleMap(t: Int): Map<String, Any?> {
        if (t == 0x90) return emptyMap()
        check(t == 0x91) { "Value is not simple map: type=0x${t.toString(16).padStart(2, '0')}" }

        val size = readUint8()
        val map = LinkedHashMap<String, Any?>(size)
        repeat(size) {
            val keyLen = readUint8()
            val key = read8BitString(keyLen)
            val value = readValue()
            map[key] = value
        }
        return map
    }

    /** Read any typed value (dispatches by type tag). */
    fun readValue(): Any? {
        val t = readUint8()
        return when (TYPE_MAP.getOrElse(t) { 0 }) {
            1 -> decodeValueInt(t)
            2 -> decodeValueString(t)
            3 -> decodeValueIntArray(t)
            4 -> decodeValueSimpleMap(t)
            else -> error("Value has unknown type: type=0x${t.toString(16).padStart(2, '0')}")
        }
    }

    companion object {
        // Type map: 1=int, 2=string, 3=int[], 4=simple map
        private val TYPE_MAP = intArrayOf(
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
        )
    }
}
