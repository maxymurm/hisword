package org.androidbible.data.sword.io

import kotlinx.cinterop.addressOf
import kotlinx.cinterop.alloc
import kotlinx.cinterop.memScoped
import kotlinx.cinterop.ptr
import kotlinx.cinterop.usePinned
import kotlinx.cinterop.value
import platform.zlib.Z_OK
import platform.zlib.uLongVar
import platform.zlib.uncompress

actual object ZlibDecompressor {
    actual fun decompress(compressed: ByteArray, uncompressedSize: Int): ByteArray {
        val output = ByteArray(uncompressedSize)
        memScoped {
            val destLen = alloc<uLongVar>()
            destLen.value = uncompressedSize.toULong()
            output.usePinned { outPinned ->
                compressed.usePinned { inPinned ->
                    val result = uncompress(
                        outPinned.addressOf(0).reinterpret(),
                        destLen.ptr,
                        inPinned.addressOf(0).reinterpret(),
                        compressed.size.toULong()
                    )
                    if (result != Z_OK) {
                        throw RuntimeException("zlib uncompress failed with code $result")
                    }
                }
            }
        }
        return output
    }
}
