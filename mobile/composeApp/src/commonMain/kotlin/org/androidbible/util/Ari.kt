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
}
