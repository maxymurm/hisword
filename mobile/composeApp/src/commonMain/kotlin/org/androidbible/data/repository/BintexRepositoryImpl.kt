package org.androidbible.data.repository

import org.androidbible.data.bintex.Yes1Reader
import org.androidbible.data.bintex.Yes2Reader
import org.androidbible.data.bintex.Yes2TextDecoder
import org.androidbible.domain.model.Verse
import org.androidbible.domain.repository.BibleReader
import org.androidbible.util.Ari

/**
 * BibleReader implementation for YES2/YES1 binary format modules.
 * Loads .yes2 / .yes1 files from the device file system.
 *
 * @param loadModuleData Platform-specific callback that returns the raw bytes of a module file.
 */
class BintexRepositoryImpl(
    private val loadModuleData: suspend (moduleKey: String) -> ByteArray?,
) : BibleReader {

    // Cache: moduleKey -> reader
    private val readerCache = mutableMapOf<String, Any>() // Yes2Reader or Yes1Reader

    override suspend fun readChapter(moduleKey: String, bookId: Int, chapter: Int): List<Verse> {
        val reader = getOrLoadReader(moduleKey) ?: return emptyList()

        return when (reader) {
            is Yes2Reader -> readChapterYes2(reader, moduleKey, bookId, chapter)
            is Yes1Reader -> readChapterYes1(reader, moduleKey, bookId, chapter)
            else -> emptyList()
        }
    }

    override suspend fun readVerse(moduleKey: String, ari: Int): Verse? {
        val bookId = Ari.decodeBook(ari)
        val chapter = Ari.decodeChapter(ari)
        val verse = Ari.decodeVerse(ari)

        val reader = getOrLoadReader(moduleKey) ?: return null
        return when (reader) {
            is Yes2Reader -> readSingleVerseYes2(reader, moduleKey, bookId, chapter, verse)
            is Yes1Reader -> readSingleVerseYes1(reader, moduleKey, bookId, chapter, verse)
            else -> null
        }
    }

    override suspend fun hasDataFiles(moduleKey: String): Boolean {
        return loadModuleData(moduleKey) != null
    }

    // -- YES2 --

    private fun readChapterYes2(
        reader: Yes2Reader,
        moduleKey: String,
        bookId: Int,
        chapter: Int,
    ): List<Verse> {
        val (bookIndex, _) = reader.findBookByBookId(bookId) ?: return emptyList()
        val verseTexts = reader.loadVerseText(bookIndex, chapter)

        return verseTexts.mapIndexed { index, rawText ->
            val verseNum = index + 1
            val ari = Ari.encode(bookId, chapter, verseNum)
            Verse(
                bibleVersionId = 0,
                ari = ari,
                bookId = bookId,
                chapter = chapter,
                verse = verseNum,
                text = rawText,
                textWithoutFormatting = Yes2TextDecoder.toPlainText(rawText),
            )
        }
    }

    private fun readSingleVerseYes2(
        reader: Yes2Reader,
        moduleKey: String,
        bookId: Int,
        chapter: Int,
        verse: Int,
    ): Verse? {
        val (bookIndex, _) = reader.findBookByBookId(bookId) ?: return null
        val text = reader.loadSingleVerse(bookIndex, chapter, verse) ?: return null
        val ari = Ari.encode(bookId, chapter, verse)
        return Verse(
            bibleVersionId = 0,
            ari = ari,
            bookId = bookId,
            chapter = chapter,
            verse = verse,
            text = text,
            textWithoutFormatting = Yes2TextDecoder.toPlainText(text),
        )
    }

    // -- YES1 --

    private fun readChapterYes1(
        reader: Yes1Reader,
        moduleKey: String,
        bookId: Int,
        chapter: Int,
    ): List<Verse> {
        val books = reader.getBooksInfo()
        if (bookId !in books) return emptyList()

        val verseTexts = reader.loadVerseText(bookId, chapter)
        return verseTexts.mapIndexed { index, rawText ->
            val verseNum = index + 1
            val ari = Ari.encode(bookId, chapter, verseNum)
            Verse(
                bibleVersionId = 0,
                ari = ari,
                bookId = bookId,
                chapter = chapter,
                verse = verseNum,
                text = rawText,
                textWithoutFormatting = Yes2TextDecoder.toPlainText(rawText),
            )
        }
    }

    private fun readSingleVerseYes1(
        reader: Yes1Reader,
        moduleKey: String,
        bookId: Int,
        chapter: Int,
        verse: Int,
    ): Verse? {
        val books = reader.getBooksInfo()
        if (bookId !in books) return null

        val verseTexts = reader.loadVerseText(bookId, chapter)
        if (verse < 1 || verse > verseTexts.size) return null

        val text = verseTexts[verse - 1]
        val ari = Ari.encode(bookId, chapter, verse)
        return Verse(
            bibleVersionId = 0,
            ari = ari,
            bookId = bookId,
            chapter = chapter,
            verse = verse,
            text = text,
            textWithoutFormatting = Yes2TextDecoder.toPlainText(text),
        )
    }

    // -- Reader loading --

    private suspend fun getOrLoadReader(moduleKey: String): Any? {
        readerCache[moduleKey]?.let { return it }

        val bytes = loadModuleData(moduleKey) ?: return null
        val reader = detectAndCreate(bytes)
        if (reader != null) {
            readerCache[moduleKey] = reader
        }
        return reader
    }

    companion object {
        private val YES2_HEADER = byteArrayOf(
            0x98.toByte(), 0x58, 0x0D, 0x0A, 0x00, 0x5D, 0xE0.toByte(), 0x02
        )
        private val YES1_HEADER = byteArrayOf(
            0x98.toByte(), 0x58, 0x0D, 0x0A, 0x00, 0x5D, 0xE0.toByte(), 0x01
        )

        fun detectAndCreate(data: ByteArray): Any? {
            if (data.size < 8) return null
            return when {
                data.copyOfRange(0, 8).contentEquals(YES2_HEADER) -> Yes2Reader(data)
                data.copyOfRange(0, 8).contentEquals(YES1_HEADER) -> Yes1Reader(data)
                else -> null
            }
        }
    }
}
