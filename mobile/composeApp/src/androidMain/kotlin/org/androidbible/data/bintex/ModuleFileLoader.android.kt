package org.androidbible.data.bintex

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

/**
 * Android implementation: loads YES2/YES1 files from app's internal storage.
 * Module files are stored at: {filesDir}/modules/{moduleKey}.yes2
 */
actual class ModuleFileLoader(private val context: Context) {

    private val modulesDir: File
        get() = File(context.filesDir, "modules")

    actual suspend fun loadModuleData(moduleKey: String): ByteArray? = withContext(Dispatchers.IO) {
        val file = findModuleFile(moduleKey)
        file?.readBytes()
    }

    actual suspend fun hasModuleFiles(moduleKey: String): Boolean = withContext(Dispatchers.IO) {
        findModuleFile(moduleKey) != null
    }

    actual suspend fun listAvailableModules(): List<String> = withContext(Dispatchers.IO) {
        val dir = modulesDir
        if (!dir.exists()) return@withContext emptyList()
        dir.listFiles()
            ?.filter { it.extension in listOf("yes2", "yes1", "yes") }
            ?.map { it.nameWithoutExtension }
            ?: emptyList()
    }

    private fun findModuleFile(moduleKey: String): File? {
        val dir = modulesDir
        if (!dir.exists()) return null
        for (ext in listOf("yes2", "yes1", "yes")) {
            val file = File(dir, "$moduleKey.$ext")
            if (file.exists()) return file
        }
        return null
    }
}
