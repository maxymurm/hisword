package org.androidbible.data.sword.io

import java.util.zip.Inflater

actual object ZlibDecompressor {
    actual fun decompress(compressed: ByteArray, uncompressedSize: Int): ByteArray {
        val inflater = Inflater()
        inflater.setInput(compressed)
        val output = ByteArray(uncompressedSize)
        val resultLen = inflater.inflate(output)
        inflater.end()
        return if (resultLen == uncompressedSize) output else output.copyOf(resultLen)
    }
}
