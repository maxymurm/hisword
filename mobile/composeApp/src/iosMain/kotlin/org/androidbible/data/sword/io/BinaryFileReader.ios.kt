package org.androidbible.data.sword.io

import kotlinx.cinterop.addressOf
import kotlinx.cinterop.usePinned
import platform.Foundation.NSData
import platform.Foundation.NSFileHandle
import platform.Foundation.NSURL
import platform.Foundation.closeFile
import platform.Foundation.dataWithContentsOfURL
import platform.Foundation.fileHandleForReadingAtPath
import platform.Foundation.readDataOfLength
import platform.Foundation.seekToFileOffset
import platform.posix.memcpy

actual class BinaryFileReader actual constructor(filePath: String) {
    private val fileHandle: NSFileHandle? = NSFileHandle.fileHandleForReadingAtPath(filePath)
    private val url = NSURL.fileURLWithPath(filePath)

    actual fun readBytes(offset: Long, length: Int): ByteArray {
        val handle = fileHandle ?: return ByteArray(0)
        handle.seekToFileOffset(offset.toULong())
        val data = handle.readDataOfLength(length.toULong())
        return data.toKotlinByteArray()
    }

    actual fun readAll(): ByteArray {
        val data = NSData.dataWithContentsOfURL(url) ?: return ByteArray(0)
        return data.toKotlinByteArray()
    }

    actual fun fileSize(): Long {
        val handle = fileHandle ?: return 0L
        val cur = handle.offsetInFile
        handle.seekToEndOfFile()
        val size = handle.offsetInFile
        handle.seekToFileOffset(cur)
        return size.toLong()
    }

    actual fun close() {
        fileHandle?.closeFile()
    }
}

internal fun NSData.toKotlinByteArray(): ByteArray {
    val len = length.toInt()
    if (len == 0) return ByteArray(0)
    val result = ByteArray(len)
    result.usePinned { pinned ->
        memcpy(pinned.addressOf(0), bytes, length)
    }
    return result
}
