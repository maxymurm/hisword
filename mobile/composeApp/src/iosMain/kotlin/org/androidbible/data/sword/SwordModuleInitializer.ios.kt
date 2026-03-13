package org.androidbible.data.sword

import platform.Foundation.NSFileManager
import platform.Foundation.NSSearchPathDirectory
import platform.Foundation.NSSearchPathDomainMask
import platform.Foundation.NSUserDomainMask
import platform.Foundation.NSDocumentDirectory
import platform.Foundation.NSString
import platform.Foundation.stringByAppendingPathComponent

actual class SwordModuleInitializer {

    private val swordDir: String by lazy {
        val paths = NSSearchPathForDirectoriesInDomains(
            NSDocumentDirectory,
            NSUserDomainMask,
            true
        )
        val docDir = paths.firstOrNull() as? String ?: ""
        (docDir as NSString).stringByAppendingPathComponent("sword")
    }

    actual fun getModulesBasePath(): String = swordDir

    actual fun isInitialized(): Boolean {
        val modsD = (swordDir as NSString).stringByAppendingPathComponent("mods.d")
        return NSFileManager.defaultManager.fileExistsAtPath(modsD)
    }

    actual suspend fun initializeModules(): Boolean {
        if (isInitialized()) return true

        val fm = NSFileManager.defaultManager
        fm.createDirectoryAtPath(swordDir, withIntermediateDirectories = true, attributes = null, error = null)

        return isInitialized()
    }

    @OptIn(kotlinx.cinterop.ExperimentalForeignApi::class, kotlinx.cinterop.BetaInteropApi::class)
    actual suspend fun installFromZip(zipBytes: ByteArray): Boolean {
        val fm = NSFileManager.defaultManager
        fm.createDirectoryAtPath(swordDir, withIntermediateDirectories = true, attributes = null, error = null)
        return false
    }
}

private fun NSSearchPathForDirectoriesInDomains(
    directory: NSSearchPathDirectory,
    domainMask: NSSearchPathDomainMask,
    expandTilde: Boolean,
): List<*> {
    return platform.Foundation.NSSearchPathForDirectoriesInDomains(directory, domainMask, expandTilde)
}
