package org.androidbible.data.sword.reader

import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.data.sword.SwordVersification
import org.androidbible.data.sword.io.BinaryFileReader
import org.androidbible.data.sword.io.CipherUtils
import org.androidbible.data.sword.io.ZlibDecompressor

class ZTextReader(
    private val config: SwordModuleConfig,
    private val modulePath: String,
) {
    private val blockCache = mutableMapOf<Pair<Int, Long>, ByteArray>()
    private val maxCacheEntries = 10

    fun readVerse(bookOsisId: String, chapter: Int, verse: Int): String {
        val (bookIndex, _) = SwordVersification.findBookByOsisId(bookOsisId)
            ?: return ""

        val testament = SwordVersification.getTestament(bookIndex)
        val testamentBookIndex = SwordVersification.getTestamentBookIndex(bookIndex)
        val books = if (testament == 0) SwordVersification.otBooks else SwordVersification.ntBooks

        val linearIndex = SwordVersification.computeLinearIndex(
            testamentBookIndex, chapter, verse, books
        )

        return readVerseByIndex(testament, linearIndex)
    }

    fun readChapter(bookOsisId: String, chapter: Int): List<Pair<Int, String>> {
        val (bookIndex, bookDef) = SwordVersification.findBookByOsisId(bookOsisId)
            ?: return emptyList()

        if (chapter < 1 || chapter > bookDef.chapterCount) return emptyList()

        val verseCount = SwordVersification.getVerseCount(bookIndex, chapter)
        val testament = SwordVersification.getTestament(bookIndex)
        val testamentBookIndex = SwordVersification.getTestamentBookIndex(bookIndex)
        val books = if (testament == 0) SwordVersification.otBooks else SwordVersification.ntBooks

        val result = mutableListOf<Pair<Int, String>>()

        for (v in 1..verseCount) {
            val linearIndex = SwordVersification.computeLinearIndex(
                testamentBookIndex, chapter, v, books
            )
            val text = readVerseByIndex(testament, linearIndex)
            if (text.isNotBlank()) {
                result.add(v to text)
            }
        }

        return result
    }

    private fun readVerseByIndex(testament: Int, linearIndex: Long): String {
        val prefix = if (testament == 0) "ot" else "nt"
        val dataDir = "$modulePath/${config.dataPath}"
        val bzvPath = "$dataDir/$prefix.bzv"
        val bzsPath = "$dataDir/$prefix.bzs"
        val bzzPath = "$dataDir/$prefix.bzz"

        val bzvReader = BinaryFileReader(bzvPath)
        try {
            val bzvOffset = linearIndex * 10
            if (bzvOffset + 10 > bzvReader.fileSize()) return ""

            val bzvEntry = bzvReader.readBytes(bzvOffset, 10)
            val blockNum = ByteUtils.readUInt32LE(bzvEntry, 0)
            val verseStart = ByteUtils.readUInt32LE(bzvEntry, 4)
            val verseSize = ByteUtils.readUInt16LE(bzvEntry, 8)

            if (verseSize == 0) return ""

            val block = getBlock(testament, blockNum, bzsPath, bzzPath)
                ?: return ""

            val start = verseStart.toInt()
            val end = minOf(start + verseSize, block.size)
            if (start >= block.size || start < 0) return ""

            val verseBytes = block.copyOfRange(start, end)
            val decrypted = CipherUtils.applyCipher(verseBytes, config)
            return decrypted.decodeToString()
        } finally {
            bzvReader.close()
        }
    }

    private fun getBlock(testament: Int, blockNum: Long, bzsPath: String, bzzPath: String): ByteArray? {
        val cacheKey = testament to blockNum

        blockCache[cacheKey]?.let { return it }

        val bzsReader = BinaryFileReader(bzsPath)
        try {
            val bzsOffset = blockNum * 12
            if (bzsOffset + 12 > bzsReader.fileSize()) return null

            val bzsEntry = bzsReader.readBytes(bzsOffset, 12)
            val compOffset = ByteUtils.readUInt32LE(bzsEntry, 0)
            val compSize = ByteUtils.readUInt32LE(bzsEntry, 4)
            val uncompSize = ByteUtils.readUInt32LE(bzsEntry, 8)

            if (compSize == 0L || uncompSize == 0L) return null

            val bzzReader = BinaryFileReader(bzzPath)
            try {
                val compressed = bzzReader.readBytes(compOffset, compSize.toInt())
                val decompressed = ZlibDecompressor.decompress(compressed, uncompSize.toInt())

                if (blockCache.size >= maxCacheEntries) {
                    blockCache.remove(blockCache.keys.first())
                }
                blockCache[cacheKey] = decompressed

                return decompressed
            } finally {
                bzzReader.close()
            }
        } finally {
            bzsReader.close()
        }
    }

    fun clearCache() {
        blockCache.clear()
    }
}
