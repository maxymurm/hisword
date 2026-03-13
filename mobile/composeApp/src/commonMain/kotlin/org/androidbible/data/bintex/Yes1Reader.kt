package org.androidbible.data.bintex

/**
 * Kotlin port: reads YES1 (legacy) Bible files.
 *
 * YES1 header: 0x98 0x58 0x0D 0x0A 0x00 0x5D 0xE0 0x01
 * Sequential sections with 12-byte underscore-padded names.
 * Verse text: newline (0x0A) separated within chapter blocks.
 */
class Yes1Reader(private val data: ByteArray) {

    private var cachedVersionInfo: Yes2VersionInfo? = null
    private var cachedBooksInfo: Map<Int, Yes2Book>? = null
    private var textBaseOffset: Int? = null

    init {
        check(data.size >= HEADER.size) { "YES1: File too small for header" }
        for (i in HEADER.indices) {
            check(data[i] == HEADER[i]) { "YES1: Invalid header at byte $i" }
        }
    }

    /** Find a section by name. Scans sequentially from offset 8. */
    private fun findSection(sectionName: String): SectionLocation? {
        val paddedName = sectionName.padEnd(SECTION_NAME_LENGTH, '_')
        var pos = HEADER.size

        while (pos + SECTION_NAME_LENGTH + 4 <= data.size) {
            val nameBytes = data.copyOfRange(pos, pos + SECTION_NAME_LENGTH)
            val name = String(CharArray(nameBytes.size) { (nameBytes[it].toInt() and 0xFF).toChar() })
            pos += SECTION_NAME_LENGTH

            val size = readInt32At(pos)
            pos += 4

            if (name == "____________") return null // end marker
            if (name == paddedName) return SectionLocation(pos, size)

            pos += size
        }
        return null
    }

    private fun readInt32At(offset: Int): Int {
        return ((data[offset].toInt() and 0xFF) shl 24) or
            ((data[offset + 1].toInt() and 0xFF) shl 16) or
            ((data[offset + 2].toInt() and 0xFF) shl 8) or
            (data[offset + 3].toInt() and 0xFF)
    }

    fun getVersionInfo(): Yes2VersionInfo {
        cachedVersionInfo?.let { return it }

        val section = findSection("infoEdisi") ?: error("YES1: infoEdisi section not found")
        val sectionData = data.copyOfRange(section.offset, section.offset + section.size)
        val br = BintexReader(sectionData)

        var shortName: String? = null
        var longName: String? = null
        var description: String? = null
        var locale: String? = null
        var bookCount = 0
        var hasPericopes = false
        var textEncoding = 1

        while (br.remaining > 0) {
            val key = br.readShortString()
            when (key) {
                "versi", "format" -> br.readInt()
                "nama" -> br.readShortString()
                "shortName", "shortTitle" -> shortName = br.readShortString()
                "judul" -> longName = br.readShortString()
                "keterangan" -> description = br.readLongString()
                "nkitab" -> bookCount = br.readInt()
                "perikopAda" -> hasPericopes = br.readInt() != 0
                "encoding" -> textEncoding = br.readInt()
                "locale" -> locale = br.readShortString()
                "end" -> break
                else -> break
            }
        }

        val info = Yes2VersionInfo(shortName, longName, description, locale, bookCount, hasPericopes, textEncoding)
        cachedVersionInfo = info
        return info
    }

    fun getBooksInfo(): Map<Int, Yes2Book> {
        cachedBooksInfo?.let { return it }

        val versionInfo = getVersionInfo()
        val section = findSection("infoKitab") ?: error("YES1: infoKitab section not found")
        val sectionData = data.copyOfRange(section.offset, section.offset + section.size)
        val br = BintexReader(sectionData)

        val books = LinkedHashMap<Int, Yes2Book>()

        for (bookIndex in 0 until versionInfo.bookCount) {
            var bookId = -1
            var shortName: String? = null
            var chapterCount = 0
            var verseCounts = intArrayOf()
            var chapterOffsets = intArrayOf()
            var offset = 0
            var empty = false

            var keyIndex = 0
            while (br.remaining > 0) {
                val key = br.readShortString()
                when (key) {
                    "versi" -> br.readInt()
                    "pos" -> bookId = br.readInt()
                    "nama", "judul" -> shortName = br.readShortString()
                    "npasal" -> chapterCount = br.readInt()
                    "nayat" -> verseCounts = IntArray(chapterCount) { br.readUint8() }
                    "ayatLoncat", "pdbBookNumber" -> br.readInt()
                    "pasal_offset" -> chapterOffsets = IntArray(chapterCount + 1) { br.readInt() }
                    "encoding" -> br.readInt()
                    "offset" -> offset = br.readInt()
                    "end" -> {
                        if (keyIndex == 0) empty = true
                        break
                    }
                    else -> break
                }
                keyIndex++
            }

            if (!empty && bookId >= 0) {
                books[bookId] = Yes2Book(bookId, shortName, null, offset, chapterCount, verseCounts, chapterOffsets)
            }
        }

        cachedBooksInfo = books
        return books
    }

    private fun getTextBaseOffset(): Int {
        textBaseOffset?.let { return it }
        val section = findSection("teks") ?: error("YES1: teks section not found")
        textBaseOffset = section.offset
        return section.offset
    }

    /**
     * Load verse texts for a specific bookId and 1-based chapter.
     * YES1 stores chapter text as newline-separated (0x0A) verse blocks.
     */
    fun loadVerseText(bookId: Int, chapter1: Int): List<String> {
        val books = getBooksInfo()
        val book = books[bookId] ?: error("YES1: Book ID $bookId not found")
        check(chapter1 in 1..book.chapterCount) { "YES1: Chapter $chapter1 out of range for book $bookId" }

        val versionInfo = getVersionInfo()
        val base = getTextBaseOffset()

        val seekTo = base + book.offset + book.chapterOffsets[chapter1 - 1]
        val chapterLength = book.chapterOffsets[chapter1] - book.chapterOffsets[chapter1 - 1]

        if (chapterLength <= 0) return emptyList()

        val raw = data.copyOfRange(seekTo, seekTo + chapterLength)
        val text = if (versionInfo.textEncoding == 1) {
            String(CharArray(raw.size) { (raw[it].toInt() and 0xFF).toChar() })
        } else {
            raw.decodeToString()
        }

        val parts = text.split('\n').toMutableList()
        if (parts.isNotEmpty() && parts.last().isEmpty()) {
            parts.removeAt(parts.lastIndex)
        }
        return parts
    }

    /** Read YES1 helper: short string (1-byte length prefix, UTF-16BE chars). */
    private fun BintexReader.readShortString(): String {
        val len = readUint8()
        if (len == 0) return ""
        return String(CharArray(len) { readUint16().toChar() })
    }

    /** Read YES1 helper: long string (4-byte length prefix, UTF-16BE chars). */
    private fun BintexReader.readLongString(): String {
        val len = readInt()
        if (len == 0) return ""
        return String(CharArray(len) { readUint16().toChar() })
    }

    private data class SectionLocation(val offset: Int, val size: Int)

    companion object {
        private val HEADER = byteArrayOf(
            0x98.toByte(), 0x58, 0x0D, 0x0A, 0x00, 0x5D, 0xE0.toByte(), 0x01
        )
        private const val SECTION_NAME_LENGTH = 12

        fun fromByteArray(data: ByteArray): Yes1Reader = Yes1Reader(data)
    }
}
