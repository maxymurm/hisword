package org.androidbible.data.sword.reader

import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.data.sword.io.BinaryFileReader
import org.androidbible.data.sword.io.CipherUtils
import org.androidbible.data.sword.io.ZlibDecompressor

class ZLDReader(
    private val config: SwordModuleConfig,
    private val modulePath: String,
) {
    fun lookup(key: String): String {
        val dataDir = "$modulePath/${config.dataPath}"
        val moduleName = config.dataPath.substringAfterLast("/")

        val idxPath = "$dataDir/$moduleName.idx"
        val datPath = "$dataDir/$moduleName.dat"

        val idxReader = try { BinaryFileReader(idxPath) } catch (_: Exception) { null }
        if (idxReader != null) {
            try {
                return readFromIdxDat(key, idxReader, datPath)
            } finally {
                idxReader.close()
            }
        }

        return ""
    }

    fun getAllKeys(): List<String> {
        val dataDir = "$modulePath/${config.dataPath}"
        val moduleName = config.dataPath.substringAfterLast("/")
        val idxPath = "$dataDir/$moduleName.idx"
        val datPath = "$dataDir/$moduleName.dat"

        val idxReader = try { BinaryFileReader(idxPath) } catch (_: Exception) { return emptyList() }
        try {
            val datReader = BinaryFileReader(datPath)
            try {
                val fileSize = idxReader.fileSize()
                val entryCount = (fileSize / 8).toInt()
                val allIdx = idxReader.readAll()
                val keys = mutableListOf<String>()

                for (i in 0 until entryCount) {
                    val baseOff = i * 8
                    val offset = ByteUtils.readUInt32LE(allIdx, baseOff)
                    val size = ByteUtils.readUInt32LE(allIdx, baseOff + 4).toInt()
                    if (size <= 0) continue

                    val raw = datReader.readBytes(offset, size)
                    val rawStr = raw.decodeToString()
                    val nlIdx = rawStr.indexOf('\n')
                    if (nlIdx > 0) {
                        keys.add(rawStr.substring(0, nlIdx).trim())
                    }
                }
                return keys
            } finally {
                datReader.close()
            }
        } finally {
            idxReader.close()
        }
    }

    private fun readFromIdxDat(key: String, idxReader: BinaryFileReader, datPath: String): String {
        val fileSize = idxReader.fileSize()
        val entryCount = (fileSize / 8).toInt()
        val allIdx = idxReader.readAll()

        val datReader = BinaryFileReader(datPath)
        try {
            val normalizedKey = key.trim().uppercase()

            for (i in 0 until entryCount) {
                val baseOff = i * 8
                val offset = ByteUtils.readUInt32LE(allIdx, baseOff)
                val size = ByteUtils.readUInt32LE(allIdx, baseOff + 4).toInt()
                if (size <= 0) continue

                val raw = datReader.readBytes(offset, size)
                val rawStr = raw.decodeToString()
                val nlIdx = rawStr.indexOf('\n')
                if (nlIdx < 0) continue

                val entryKey = rawStr.substring(0, nlIdx).trim().uppercase()
                if (entryKey == normalizedKey) {
                    val contentBytes = raw.copyOfRange(nlIdx + 1, raw.size)
                    val decrypted = CipherUtils.applyCipher(contentBytes, config)
                    val content = decrypted.decodeToString().trim()

                    if (content.startsWith("@LINK", ignoreCase = true)) {
                        val linkTarget = content.substringAfter(" ").trim()
                        return readFromIdxDat(linkTarget, idxReader, datPath)
                    }

                    return content
                }
            }
            return ""
        } finally {
            datReader.close()
        }
    }
}
