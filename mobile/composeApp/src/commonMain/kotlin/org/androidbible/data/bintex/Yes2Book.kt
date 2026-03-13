package org.androidbible.data.bintex

/**
 * Metadata for a single book within a YES2 file.
 */
data class Yes2Book(
    val bookId: Int,
    val shortName: String?,
    val abbreviation: String?,
    val offset: Int,
    val chapterCount: Int,
    val verseCounts: IntArray,
    val chapterOffsets: IntArray,
) {
    override fun equals(other: Any?): Boolean {
        if (this === other) return true
        if (other !is Yes2Book) return false
        return bookId == other.bookId
    }

    override fun hashCode(): Int = bookId
}
