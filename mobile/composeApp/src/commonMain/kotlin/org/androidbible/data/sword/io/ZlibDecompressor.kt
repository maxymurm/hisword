package org.androidbible.data.sword.io

expect object ZlibDecompressor {
    fun decompress(compressed: ByteArray, uncompressedSize: Int): ByteArray
}
