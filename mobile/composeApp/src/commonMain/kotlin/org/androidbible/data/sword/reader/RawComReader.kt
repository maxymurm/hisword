package org.androidbible.data.sword.reader

import org.androidbible.data.sword.SwordModuleConfig
import org.androidbible.data.sword.SwordVersification
import org.androidbible.data.sword.io.BinaryFileReader
import org.androidbible.data.sword.io.CipherUtils

class RawComReader(
    private val config: SwordModuleConfig,
    private val modulePath: String,
) {
    fun readVerse(bookOsisId: String, chapter: Int, verse: Int): String {
        val (bookIndex, _) = SwordVersification.findBookByOsisId(bookOsisId)
            ?: return ""

        val testament = SwordVersification.getTestament(bookIndex)
        val testamentBookIndex = SwordVersification.getTestamentBookIndex(bookIndex)
        val books = if (testament == 0) SwordVersification.otBooks else SwordVersification.ntBooks

        val linearIndex = SwordVersification.computeLinearIndex(
            testamentBookIndex, chapter, verse, books
        )

        return readByIndex(testament, linearIndex)
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
            val text = readByIndex(testament, linearIndex)
            if (text.isNotBlank()) {
                result.add(v to text)
            }
        }
        return result
    }

    private fun readByIndex(testament: Int, linearIndex: Long): String {
        val prefix = if (testament == 0) "ot" else "nt"
        val dataDir = "$modulePath/${config.dataPath}"
        val vssPath = "$dataDir/$prefix.vss"
        val datPath = "$dataDir/$prefix"

        val vssReader = BinaryFileReader(vssPath)
        try {
            val vssOffset = linearIndex * 6
            if (vssOffset + 6 > vssReader.fileSize()) return ""

            val vssEntry = vssReader.readBytes(vssOffset, 6)
            val start = ByteUtils.readInt32LE(vssEntry, 0)
            val size = ByteUtils.readUInt16LE(vssEntry, 4)

            if (start < 0 || size == 0) return ""

            val datReader = BinaryFileReader(datPath)
            try {
                val data = datReader.readBytes(start.toLong(), size)
                val decrypted = CipherUtils.applyCipher(data, config)
                return decrypted.decodeToString()
            } finally {
                datReader.close()
            }
        } finally {
            vssReader.close()
        }
    }
}
