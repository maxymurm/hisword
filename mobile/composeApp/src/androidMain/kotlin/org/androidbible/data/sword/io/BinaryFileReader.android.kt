package org.androidbible.data.sword.io

import java.io.RandomAccessFile

actual class BinaryFileReader actual constructor(filePath: String) {
    private val raf = RandomAccessFile(filePath, "r")

    actual fun readBytes(offset: Long, length: Int): ByteArray {
        raf.seek(offset)
        val buf = ByteArray(length)
        val read = raf.read(buf)
        return if (read == length) buf else buf.copyOf(maxOf(0, read))
    }

    actual fun readAll(): ByteArray {
        raf.seek(0)
        val buf = ByteArray(raf.length().toInt())
        raf.readFully(buf)
        return buf
    }

    actual fun fileSize(): Long = raf.length()

    actual fun close() {
        raf.close()
    }
}
