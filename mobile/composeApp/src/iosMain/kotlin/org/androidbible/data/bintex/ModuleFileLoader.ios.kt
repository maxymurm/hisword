package org.androidbible.data.bintex

import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.addressOf
import kotlinx.cinterop.usePinned
import platform.Foundation.NSBundle
import platform.Foundation.NSData
import platform.Foundation.NSFileManager
import platform.Foundation.NSSearchPathForDirectoriesInDomains
import platform.Foundation.NSDocumentDirectory
import platform.Foundation.NSUserDomainMask
import platform.posix.memcpy

/**
 * iOS implementation: loads YES2/YES1 files from app's Documents directory.
 * Module files are stored at: {Documents}/modules/{moduleKey}.yes2
 */
actual class ModuleFileLoader {

    private val modulesDir: String
        get() {
            val paths = NSSearchPathForDirectoriesInDomains(
                NSDocumentDirectory, NSUserDomainMask, true
            )
            val documentsDir = paths.firstOrNull() as? String ?: ""
            return "$documentsDir/modules"
        }

    @OptIn(ExperimentalForeignApi::class)
    actual suspend fun loadModuleData(moduleKey: String): ByteArray? {
        val path = findModuleFile(moduleKey) ?: return null
        val data = NSData.dataWithContentsOfFile(path) ?: return null
        val bytes = ByteArray(data.length.toInt())
        bytes.usePinned { pinned ->
            memcpy(pinned.addressOf(0), data.bytes, data.length)
        }
        return bytes
    }

    actual suspend fun hasModuleFiles(moduleKey: String): Boolean {
        return findModuleFile(moduleKey) != null
    }

    actual suspend fun listAvailableModules(): List<String> {
        val fm = NSFileManager.defaultManager
        if (!fm.fileExistsAtPath(modulesDir)) return emptyList()
        val contents = fm.contentsOfDirectoryAtPath(modulesDir, null) ?: return emptyList()
        return (contents as List<*>)
            .filterIsInstance<String>()
            .filter { it.endsWith(".yes2") || it.endsWith(".yes1") || it.endsWith(".yes") }
            .map { it.substringBeforeLast('.') }
    }

    private fun findModuleFile(moduleKey: String): String? {
        val fm = NSFileManager.defaultManager
        for (ext in listOf("yes2", "yes1", "yes")) {
            val path = "$modulesDir/$moduleKey.$ext"
            if (fm.fileExistsAtPath(path)) return path
        }
        return null
    }
}
