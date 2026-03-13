package org.androidbible.data.sword

import android.content.Context
import java.io.File
import java.util.zip.ZipInputStream

actual class SwordModuleInitializer(private val context: Context) {

    private val swordDir: File
        get() = File(context.filesDir, "sword")

    actual fun getModulesBasePath(): String = swordDir.absolutePath

    actual fun isInitialized(): Boolean {
        val modsD = File(swordDir, "mods.d")
        return modsD.exists() && (modsD.listFiles()?.isNotEmpty() == true)
    }

    actual suspend fun initializeModules(): Boolean {
        if (isInitialized()) return true

        try {
            swordDir.mkdirs()

            val assetFiles = try {
                context.assets.list("sword") ?: emptyArray()
            } catch (_: Exception) {
                emptyArray()
            }

            val zipFiles = assetFiles.filter { it.endsWith(".zip", ignoreCase = true) }
            if (zipFiles.isEmpty()) return false

            for (zipName in zipFiles) {
                extractZip("sword/$zipName")
            }

            return isInitialized()
        } catch (_: Exception) {
            return false
        }
    }

    private fun extractZip(assetPath: String) {
        context.assets.open(assetPath).use { inputStream ->
            ZipInputStream(inputStream).use { zis ->
                var entry = zis.nextEntry
                while (entry != null) {
                    val outFile = File(swordDir, entry.name)

                    if (entry.isDirectory) {
                        outFile.mkdirs()
                    } else {
                        outFile.parentFile?.mkdirs()
                        outFile.outputStream().use { fos ->
                            zis.copyTo(fos)
                        }
                    }

                    zis.closeEntry()
                    entry = zis.nextEntry
                }
            }
        }
    }

    actual suspend fun installFromZip(zipBytes: ByteArray): Boolean {
        return try {
            swordDir.mkdirs()
            ZipInputStream(zipBytes.inputStream()).use { zis ->
                var entry = zis.nextEntry
                while (entry != null) {
                    val entryName = entry.name
                    if (entryName.contains("..")) {
                        zis.closeEntry()
                        entry = zis.nextEntry
                        continue
                    }
                    val outFile = File(swordDir, entryName)
                    if (entry.isDirectory) {
                        outFile.mkdirs()
                    } else {
                        outFile.parentFile?.mkdirs()
                        outFile.outputStream().use { fos ->
                            zis.copyTo(fos)
                        }
                    }
                    zis.closeEntry()
                    entry = zis.nextEntry
                }
            }
            true
        } catch (_: Exception) {
            false
        }
    }
}
