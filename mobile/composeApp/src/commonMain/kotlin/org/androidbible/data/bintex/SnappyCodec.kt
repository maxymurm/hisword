package org.androidbible.data.bintex

/**
 * Pure-Kotlin Snappy decompression — port of de.jarnbjo.jsnappy.SnappyDecompressor.
 * No JNI or platform dependencies.
 */
object SnappyCodec {

    fun decompress(input: ByteArray, offset: Int = 0, length: Int = input.size - offset): ByteArray {
        var sourceIndex = offset
        val max = offset + length

        // Read uncompressed length (standard Snappy varint, NOT Bintex varuint)
        var targetLength = 0
        var shift = 0
        do {
            check(sourceIndex < max) { "Snappy: truncated varint in header" }
            val byte = input[sourceIndex].toInt() and 0xFF
            targetLength = targetLength or ((byte and 0x7F) shl shift)
            shift += 7
            sourceIndex++
        } while ((byte and 0x80) != 0)

        val out = ByteArray(targetLength)
        var targetIndex = 0

        while (sourceIndex < max) {
            check(targetIndex < targetLength) { "Snappy: superfluous input data at offset $sourceIndex" }

            val tag = input[sourceIndex].toInt() and 0xFF
            val elementType = tag and 3

            when (elementType) {
                0 -> { // Literal
                    var literalLen = (tag shr 2) and 0x3F
                    sourceIndex++
                    when (literalLen) {
                        60 -> {
                            literalLen = input[sourceIndex++].toInt() and 0xFF
                            literalLen++
                        }
                        61 -> {
                            literalLen = (input[sourceIndex++].toInt() and 0xFF) or
                                ((input[sourceIndex++].toInt() and 0xFF) shl 8)
                            literalLen++
                        }
                        62 -> {
                            literalLen = (input[sourceIndex++].toInt() and 0xFF) or
                                ((input[sourceIndex++].toInt() and 0xFF) shl 8) or
                                ((input[sourceIndex++].toInt() and 0xFF) shl 16)
                            literalLen++
                        }
                        63 -> {
                            literalLen = (input[sourceIndex++].toInt() and 0xFF) or
                                ((input[sourceIndex++].toInt() and 0xFF) shl 8) or
                                ((input[sourceIndex++].toInt() and 0xFF) shl 16) or
                                ((input[sourceIndex++].toInt() and 0xFF) shl 24)
                            literalLen++
                        }
                        else -> literalLen++
                    }
                    input.copyInto(out, targetIndex, sourceIndex, sourceIndex + literalLen)
                    sourceIndex += literalLen
                    targetIndex += literalLen
                }
                1 -> { // Copy with 1-byte offset
                    val copyLen = 4 + ((tag shr 2) and 7)
                    var copyOffset = (tag and 0xE0) shl 3
                    sourceIndex++
                    copyOffset = copyOffset or (input[sourceIndex++].toInt() and 0xFF)
                    copyWithinBuffer(out, targetIndex, copyOffset, copyLen)
                    targetIndex += copyLen
                }
                2 -> { // Copy with 2-byte LE offset
                    val copyLen = ((tag shr 2) and 0x3F) + 1
                    sourceIndex++
                    val copyOffset = (input[sourceIndex++].toInt() and 0xFF) or
                        ((input[sourceIndex++].toInt() and 0xFF) shl 8)
                    copyWithinBuffer(out, targetIndex, copyOffset, copyLen)
                    targetIndex += copyLen
                }
                3 -> { // Copy with 4-byte LE offset
                    val copyLen = ((tag shr 2) and 0x3F) + 1
                    sourceIndex++
                    val copyOffset = (input[sourceIndex++].toInt() and 0xFF) or
                        ((input[sourceIndex++].toInt() and 0xFF) shl 8) or
                        ((input[sourceIndex++].toInt() and 0xFF) shl 16) or
                        ((input[sourceIndex++].toInt() and 0xFF) shl 24)
                    copyWithinBuffer(out, targetIndex, copyOffset, copyLen)
                    targetIndex += copyLen
                }
            }
        }

        return out
    }

    private fun copyWithinBuffer(buffer: ByteArray, targetIndex: Int, offset: Int, length: Int) {
        val srcIndex = targetIndex - offset
        if (length <= offset) {
            // Non-overlapping
            buffer.copyInto(buffer, targetIndex, srcIndex, srcIndex + length)
        } else if (offset == 1) {
            // Run-length encoding: repeat single byte
            val b = buffer[srcIndex]
            for (i in 0 until length) {
                buffer[targetIndex + i] = b
            }
        } else {
            // Overlapping copy — byte by byte
            var remaining = length
            var dst = targetIndex
            while (remaining > 0) {
                val chunk = minOf(remaining, offset)
                for (i in 0 until chunk) {
                    buffer[dst + i] = buffer[srcIndex + i]
                }
                dst += chunk
                remaining -= chunk
            }
        }
    }
}
