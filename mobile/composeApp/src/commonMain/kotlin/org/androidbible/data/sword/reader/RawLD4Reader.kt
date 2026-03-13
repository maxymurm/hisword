package org.androidbible.data.sword.reader

import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.data.sword.io.BinaryFileReader
import org.androidbible.data.sword.io.CipherUtils

class RawLD4Reader(
    private val config: SwordModuleConfig,
    private val modulePath: String,
) {
    private var indexEntries: List<IndexEntry>? = null

    private data class IndexEntry(
        val offset: Long,
        val size: Int,
    )

    fun lookup(key: String): String {
        val dataDir = "$modulePath/${config.dataPath}"
        val moduleName = config.dataPath.substringAfterLast("/")

        val idxPath = "$dataDir/$moduleName.idx"
        val datPath = "$dataDir/$moduleName.dat"

        val entries = loadIndex(idxPath)
        if (entries.isEmpty()) return ""

        val datReader = BinaryFileReader(datPath)
        try {
            return findEntry(key, entries, datReader, maxDepth = 5)
        } finally {
            datReader.close()
        }
    }

    fun getAllKeys(): List<String> {
        val dataDir = "$modulePath/${config.dataPath}"
        val moduleName = config.dataPath.substringAfterLast("/")
        val idxPath = "$dataDir/$moduleName.idx"
        val datPath = "$dataDir/$moduleName.dat"

        val entries = loadIndex(idxPath)
        if (entries.isEmpty()) return emptyList()

        val datReader = BinaryFileReader(datPath)
        try {
            return entries.mapNotNull { entry ->
                if (entry.size <= 0) return@mapNotNull null
                val raw = datReader.readBytes(entry.offset, entry.size)
                val rawStr = raw.decodeToString()
                val nlIdx = rawStr.indexOf('\n')
                if (nlIdx > 0) rawStr.substring(0, nlIdx).trim() else null
            }
        } finally {
            datReader.close()
        }
    }

    private fun loadIndex(idxPath: String): List<IndexEntry> {
        indexEntries?.let { return it }

        val idxReader = BinaryFileReader(idxPath)
        try {
            val fileSize = idxReader.fileSize()
            val entryCount = (fileSize / 8).toInt()
            val entries = mutableListOf<IndexEntry>()

            val allData = idxReader.readAll()
            for (i in 0 until entryCount) {
                val baseOff = i * 8
                val offset = ByteUtils.readUInt32LE(allData, baseOff)
                val size = ByteUtils.readUInt32LE(allData, baseOff + 4).toInt()
                entries.add(IndexEntry(offset, size))
            }

            indexEntries = entries
            return entries
        } finally {
            idxReader.close()
        }
    }

    private fun findEntry(
        targetKey: String,
        entries: List<IndexEntry>,
        datReader: BinaryFileReader,
        maxDepth: Int,
    ): String {
        if (maxDepth <= 0) return ""

        val normalizedTarget = targetKey.trim().uppercase()

        for (entry in entries) {
            if (entry.size <= 0) continue

            val raw = datReader.readBytes(entry.offset, entry.size)
            val rawStr = raw.decodeToString()
            val nlIdx = rawStr.indexOf('\n')
            if (nlIdx < 0) continue

            val entryKey = rawStr.substring(0, nlIdx).trim().uppercase()
            if (entryKey == normalizedTarget) {
                val contentBytes = raw.copyOfRange(nlIdx + 1, raw.size)
                val decrypted = CipherUtils.applyCipher(contentBytes, config)
                val content = decrypted.decodeToString().trim()

                if (content.startsWith("@LINK", ignoreCase = true)) {
                    val linkTarget = content.substringAfter(" ").trim()
                    return findEntry(linkTarget, entries, datReader, maxDepth - 1)
                }

                return content
            }
        }

        return ""
    }

    fun clearCache() {
        indexEntries = null
    }
}
