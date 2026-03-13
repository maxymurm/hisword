package org.androidbible.data.sword.io

import platform.Foundation.NSFileManager
import platform.Foundation.NSString
import platform.Foundation.NSUTF8StringEncoding
import platform.Foundation.stringWithContentsOfFile

actual object FileSystem {
    actual fun exists(path: String): Boolean =
        NSFileManager.defaultManager.fileExistsAtPath(path)

    actual fun listDir(path: String): List<String> =
        NSFileManager.defaultManager.contentsOfDirectoryAtPath(path, error = null)
            ?.map { it as String }
            ?: emptyList()

    @Suppress("CAST_NEVER_SUCCEEDS")
    actual fun readText(path: String): String =
        NSString.stringWithContentsOfFile(path, NSUTF8StringEncoding, null) as? String ?: ""
}
