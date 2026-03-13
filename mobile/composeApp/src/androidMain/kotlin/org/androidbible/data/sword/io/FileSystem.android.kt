package org.androidbible.data.sword.io

import java.io.File

actual object FileSystem {
    actual fun exists(path: String): Boolean = File(path).exists()

    actual fun listDir(path: String): List<String> =
        File(path).list()?.toList() ?: emptyList()

    actual fun readText(path: String): String = File(path).readText(Charsets.UTF_8)
}
