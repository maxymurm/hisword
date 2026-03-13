package org.androidbible.util

import org.androidbible.data.sword.SwordVersification

/**
 * Deep link parser and builder for `bible://` URI scheme.
 *
 * Format: bible://BookOsisId.chapter.verse?version=KEY
 * Examples:
 *   bible://Gen.1.1?version=KJV
 *   bible://Ps.23
 *   bible://Rev.22.21?version=tb
 */
object DeepLink {

    data class BibleReference(
        val bookId: Int,
        val chapter: Int,
        val verse: Int,
        val versionKey: String?,
    )

    /**
     * Parse a bible:// deep link URI into a BibleReference.
     * Returns null if the URI is invalid.
     */
    fun parse(uri: String): BibleReference? {
        val normalized = uri.removePrefix("bible://").removePrefix("bible:")
        val parts = normalized.split("?", limit = 2)
        val path = parts[0]
        val query = if (parts.size > 1) parts[1] else null

        val segments = path.split(".")
        if (segments.isEmpty()) return null

        val osisId = segments[0]
        val chapter = segments.getOrNull(1)?.toIntOrNull() ?: 1
        val verse = segments.getOrNull(2)?.toIntOrNull() ?: 0

        val (bookIndex, _) = SwordVersification.findBookByOsisId(osisId) ?: return null
        val bookId = bookIndex + 1

        val versionKey = query?.split("&")
            ?.map { it.split("=", limit = 2) }
            ?.find { it[0].equals("version", ignoreCase = true) }
            ?.getOrNull(1)

        return BibleReference(bookId, chapter, verse, versionKey)
    }

    /**
     * Build a bible:// deep link URI from components.
     */
    fun build(bookId: Int, chapter: Int, verse: Int = 0, versionKey: String? = null): String {
        val bookDef = org.androidbible.data.repository.SwordBibleReader.bookDefForId(bookId) ?: return ""
        val sb = StringBuilder("bible://${bookDef.osisId}.$chapter")
        if (verse > 0) sb.append(".$verse")
        if (!versionKey.isNullOrBlank()) sb.append("?version=$versionKey")
        return sb.toString()
    }
}
