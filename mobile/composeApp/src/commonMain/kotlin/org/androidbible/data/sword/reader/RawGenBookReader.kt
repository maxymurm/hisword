package org.androidbible.data.sword.reader

import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.data.sword.io.BinaryFileReader
import org.androidbible.data.sword.io.CipherUtils

class RawGenBookReader(
    private val config: SwordModuleConfig,
    private val modulePath: String,
) {
    private data class KeyEntry(
        val key: String,
        val offset: Long,
        val size: Int,
    )

    private var keyIndex: List<KeyEntry>? = null

    fun lookup(key: String): String {
        val entries = ensureIndex()
        if (entries.isEmpty()) return ""

        val exact = entries.find { it.key == key }
        if (exact != null && exact.size > 0) {
            return readData(exact.offset, exact.size)
        }

        val normalizedKey = "/" + key.trimStart('/')
        val match = entries.find { entry ->
            entry.key.equals(key, ignoreCase = true) ||
                entry.key.equals(normalizedKey, ignoreCase = true)
        }
        if (match != null && match.size > 0) {
            return readData(match.offset, match.size)
        }

        return ""
    }

    fun getAllKeys(): List<String> {
        return ensureIndex().map { it.key }
    }

    fun clearCache() {
        keyIndex = null
    }

    private fun ensureIndex(): List<KeyEntry> {
        keyIndex?.let { return it }

        val dataDir = "$modulePath/${config.dataPath}"
        val moduleName = config.dataPath.substringAfterLast("/")
        val bksPath = "$dataDir/$moduleName.bks"

        val bksReader: BinaryFileReader
        try {
            bksReader = BinaryFileReader(bksPath)
        } catch (_: Exception) {
            keyIndex = emptyList()
            return emptyList()
        }

        try {
            val bksData = bksReader.readAll()
            val len = bksData.size
            val entries = mutableListOf<KeyEntry>()
            var pos = 0

            while (pos < len) {
                if (pos + 4 > len) break
                val keySize = ByteUtils.readInt32LE(bksData, pos)
                pos += 4

                if (keySize <= 0 || keySize > 1024 || pos + keySize > len) break

                val keyBytes = bksData.copyOfRange(pos, pos + keySize)
                val key = keyBytes.decodeToString().trimEnd('\u0000')
                pos += keySize

                if (pos + 8 > len) break
                val dataOffset = ByteUtils.readUInt32LE(bksData, pos)
                val dataSize = ByteUtils.readUInt32LE(bksData, pos + 4).toInt()
                pos += 8

                if (key.isNotEmpty()) {
                    entries.add(KeyEntry(key, dataOffset, dataSize))
                }

                if (pos + 12 <= len) {
                    pos += 12
                }
            }

            keyIndex = entries
            return entries
        } finally {
            bksReader.close()
        }
    }

    private fun readData(offset: Long, size: Int): String {
        if (size <= 0) return ""

        val dataDir = "$modulePath/${config.dataPath}"
        val moduleName = config.dataPath.substringAfterLast("/")
        val bdtPath = "$dataDir/$moduleName.bdt"

        val bdtReader: BinaryFileReader
        try {
            bdtReader = BinaryFileReader(bdtPath)
        } catch (_: Exception) {
            return ""
        }

        try {
            val data = bdtReader.readBytes(offset, size)
            val decrypted = CipherUtils.applyCipher(data, config)
            return decrypted.decodeToString().trimEnd('\u0000')
        } finally {
            bdtReader.close()
        }
    }
}
