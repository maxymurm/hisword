package org.androidbible.util

/**
 * ARI (Absolute Reference Integer) encoding/decoding.
 *
 * Encodes book, chapter, and verse into a single Int:
 *   ari = (bookId << 16) | (chapter << 8) | verse
 *
 * This is the same encoding used in the legacy Android Bible app.
 */
object Ari {
    fun encode(bookId: Int, chapter: Int, verse: Int): Int {
        return (bookId shl 16) or (chapter shl 8) or verse
    }

    fun decodeBook(ari: Int): Int = (ari ushr 16) and 0xFF

    fun decodeChapter(ari: Int): Int = (ari ushr 8) and 0xFF

    fun decodeVerse(ari: Int): Int = ari and 0xFF

    fun decode(ari: Int): Triple<Int, Int, Int> {
        return Triple(decodeBook(ari), decodeChapter(ari), decodeVerse(ari))
    }

    /**
     * Encode a range of verses as a pair of ARIs.
     */
    fun encodeRange(bookId: Int, chapter: Int, verseStart: Int, verseEnd: Int): Pair<Int, Int> {
        return encode(bookId, chapter, verseStart) to encode(bookId, chapter, verseEnd)
    }

    /**
     * Format ARI as human-readable reference string.
     */
    fun toReference(ari: Int, bookName: String): String {
        val chapter = decodeChapter(ari)
        val verse = decodeVerse(ari)
        return if (verse == 0) "$bookName $chapter" else "$bookName $chapter:$verse"
    }

    /**
     * Compact reference string using canonical book names (1-based bookId).
     */
    fun referenceString(ari: Int): String {
        val bookId = decodeBook(ari)
        val chapter = decodeChapter(ari)
        val verse = decodeVerse(ari)
        val bookName = BOOK_NAMES.getOrElse(bookId) { "Book $bookId" }
        return if (verse == 0) "$bookName $chapter" else "$bookName $chapter:$verse"
    }

    private val BOOK_NAMES = mapOf(
        1 to "Gen", 2 to "Exo", 3 to "Lev", 4 to "Num", 5 to "Deu",
        6 to "Jos", 7 to "Jdg", 8 to "Rth", 9 to "1Sa", 10 to "2Sa",
        11 to "1Ki", 12 to "2Ki", 13 to "1Ch", 14 to "2Ch", 15 to "Ezr",
        16 to "Neh", 17 to "Est", 18 to "Job", 19 to "Psa", 20 to "Pro",
        21 to "Ecc", 22 to "Sol", 23 to "Isa", 24 to "Jer", 25 to "Lam",
        26 to "Eze", 27 to "Dan", 28 to "Hos", 29 to "Joe", 30 to "Amo",
        31 to "Oba", 32 to "Jon", 33 to "Mic", 34 to "Nah", 35 to "Hab",
        36 to "Zep", 37 to "Hag", 38 to "Zec", 39 to "Mal",
        40 to "Mat", 41 to "Mar", 42 to "Luk", 43 to "Joh", 44 to "Act",
        45 to "Rom", 46 to "1Co", 47 to "2Co", 48 to "Gal", 49 to "Eph",
        50 to "Php", 51 to "Col", 52 to "1Th", 53 to "2Th", 54 to "1Ti",
        55 to "2Ti", 56 to "Tit", 57 to "Phm", 58 to "Heb", 59 to "Jam",
        60 to "1Pe", 61 to "2Pe", 62 to "1Jn", 63 to "2Jn", 64 to "3Jn",
        65 to "Jud", 66 to "Rev",
    )
}
