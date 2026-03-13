package org.androidbible.data.bintex

/**
 * Decodes YES2 verse markup into plain text or annotated segments.
 *
 * YES2 verse text may contain inline markup tags:
 * - @@  → literal '@'
 * - @9  → start italic
 * - @7  → end italic
 * - @8  → line break within verse
 * - @6  → paragraph break
 * - @0  → start red letter (words of Jesus)
 * - @5  → end red letter
 * - @<n> → other formatting markers
 *
 * For plain text rendering these are stripped; for rich text they produce segments.
 */
object Yes2TextDecoder {

    /**
     * Strip all YES2 markup tags, returning plain text.
     */
    fun toPlainText(verseText: String): String {
        if (!verseText.contains('@')) return verseText

        val sb = StringBuilder(verseText.length)
        var i = 0
        while (i < verseText.length) {
            val c = verseText[i]
            if (c == '@' && i + 1 < verseText.length) {
                val next = verseText[i + 1]
                when (next) {
                    '@' -> {
                        sb.append('@')
                        i += 2
                    }
                    '8' -> {
                        sb.append('\n')
                        i += 2
                    }
                    '6' -> {
                        sb.append('\n')
                        i += 2
                    }
                    else -> {
                        // Skip formatting tag
                        i += 2
                    }
                }
            } else {
                sb.append(c)
                i++
            }
        }
        return sb.toString()
    }

    /**
     * Parse YES2 markup into segments for rich text rendering.
     */
    fun toSegments(verseText: String): List<TextSegment> {
        if (!verseText.contains('@')) {
            return listOf(TextSegment(verseText, TextStyle.NORMAL))
        }

        val segments = mutableListOf<TextSegment>()
        val current = StringBuilder()
        var style = TextStyle.NORMAL
        var i = 0

        while (i < verseText.length) {
            val c = verseText[i]
            if (c == '@' && i + 1 < verseText.length) {
                val next = verseText[i + 1]
                when (next) {
                    '@' -> {
                        current.append('@')
                        i += 2
                    }
                    '9' -> {
                        if (current.isNotEmpty()) {
                            segments.add(TextSegment(current.toString(), style))
                            current.clear()
                        }
                        style = TextStyle.ITALIC
                        i += 2
                    }
                    '7' -> {
                        if (current.isNotEmpty()) {
                            segments.add(TextSegment(current.toString(), style))
                            current.clear()
                        }
                        style = TextStyle.NORMAL
                        i += 2
                    }
                    '0' -> {
                        if (current.isNotEmpty()) {
                            segments.add(TextSegment(current.toString(), style))
                            current.clear()
                        }
                        style = TextStyle.RED_LETTER
                        i += 2
                    }
                    '5' -> {
                        if (current.isNotEmpty()) {
                            segments.add(TextSegment(current.toString(), style))
                            current.clear()
                        }
                        style = TextStyle.NORMAL
                        i += 2
                    }
                    '8', '6' -> {
                        current.append('\n')
                        i += 2
                    }
                    else -> {
                        // Unknown tag — skip
                        i += 2
                    }
                }
            } else {
                current.append(c)
                i++
            }
        }

        if (current.isNotEmpty()) {
            segments.add(TextSegment(current.toString(), style))
        }

        return segments
    }
}

data class TextSegment(
    val text: String,
    val style: TextStyle,
)

enum class TextStyle {
    NORMAL,
    ITALIC,
    RED_LETTER,
}
