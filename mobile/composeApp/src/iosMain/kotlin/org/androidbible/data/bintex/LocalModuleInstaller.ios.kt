package org.androidbible.data.bintex

import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.addressOf
import kotlinx.cinterop.usePinned
import platform.Foundation.NSData
import platform.Foundation.NSFileManager
import platform.Foundation.NSSearchPathForDirectoriesInDomains
import platform.Foundation.NSDocumentDirectory
import platform.Foundation.NSUserDomainMask
import platform.Foundation.create
import platform.Foundation.writeToFile

actual class LocalModuleInstaller {

    private val modulesDir: String
        get() {
            val paths = NSSearchPathForDirectoriesInDomains(
                NSDocumentDirectory, NSUserDomainMask, true
            )
            val documentsDir = paths.firstOrNull() as? String ?: ""
            return "$documentsDir/modules"
        }

    @OptIn(ExperimentalForeignApi::class)
    actual suspend fun install(moduleKey: String, data: ByteArray): Boolean {
        val fm = NSFileManager.defaultManager
        if (!fm.fileExistsAtPath(modulesDir)) {
            fm.createDirectoryAtPath(modulesDir, withIntermediateDirectories = true, attributes = null, error = null)
        }

        val ext = when {
            data.size >= 8 && data.copyOfRange(0, 8).contentEquals(YES2_HEADER) -> "yes2"
            data.size >= 8 && data.copyOfRange(0, 8).contentEquals(YES1_HEADER) -> "yes1"
            else -> "yes"
        }

        val path = "$modulesDir/$moduleKey.$ext"
        val nsData = data.usePinned { pinned ->
            NSData.create(bytes = pinned.addressOf(0), length = data.size.toULong())
        }
        return nsData.writeToFile(path, atomically = true)
    }

    actual suspend fun uninstall(moduleKey: String): Boolean {
        val fm = NSFileManager.defaultManager
        var deleted = false
        for (ext in listOf("yes2", "yes1", "yes")) {
            val path = "$modulesDir/$moduleKey.$ext"
            if (fm.fileExistsAtPath(path)) {
                fm.removeItemAtPath(path, null)
                deleted = true
            }
        }
        return deleted
    }

    companion object {
        private val YES2_HEADER = byteArrayOf(
            0x98.toByte(), 0x58, 0x0D, 0x0A, 0x00, 0x5D, 0xE0.toByte(), 0x02
        )
        private val YES1_HEADER = byteArrayOf(
            0x98.toByte(), 0x58, 0x0D, 0x0A, 0x00, 0x5D, 0xE0.toByte(), 0x01
        )
    }
}
