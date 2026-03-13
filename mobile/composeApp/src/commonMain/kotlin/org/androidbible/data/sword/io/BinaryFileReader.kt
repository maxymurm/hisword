package org.androidbible.data.sword.io

expect class BinaryFileReader(filePath: String) {
    fun readBytes(offset: Long, length: Int): ByteArray
    fun readAll(): ByteArray
    fun fileSize(): Long
    fun close()
}
