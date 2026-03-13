package org.androidbible.data.bintex

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

actual class LocalModuleInstaller(private val context: Context) {

    private val modulesDir: File
        get() = File(context.filesDir, "modules").also { it.mkdirs() }

    actual suspend fun install(moduleKey: String, data: ByteArray): Boolean = withContext(Dispatchers.IO) {
        val ext = when {
            data.size >= 8 && data.copyOfRange(0, 8).contentEquals(YES2_HEADER) -> "yes2"
            data.size >= 8 && data.copyOfRange(0, 8).contentEquals(YES1_HEADER) -> "yes1"
            else -> "yes"
        }
        val file = File(modulesDir, "$moduleKey.$ext")
        file.writeBytes(data)
        true
    }

    actual suspend fun uninstall(moduleKey: String): Boolean = withContext(Dispatchers.IO) {
        var deleted = false
        for (ext in listOf("yes2", "yes1", "yes")) {
            val file = File(modulesDir, "$moduleKey.$ext")
            if (file.exists()) {
                file.delete()
                deleted = true
            }
        }
        deleted
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
