package org.androidbible.data.bintex

/**
 * Kotlin port of yuku.alkitab.yes2.Yes2Reader — reads YES2 Bible files.
 *
 * YES2 header: 0x98 0x58 0x0D 0x0A 0x00 0x5D 0xE0 0x02
 * Section index starts at offset 12.
 * Sections: versionInfo, booksInfo, text, pericopes, xrefs, footnotes.
 */
class Yes2Reader(private val data: ByteArray) {

    private val sectionEntries = LinkedHashMap<String, SectionEntry>()
    private var sectionDataStartOffset: Int = 0

    // Cached data
    private var cachedVersionInfo: Yes2VersionInfo? = null
    private var cachedBooksInfo: List<Yes2Book>? = null
    private var cachedTextData: ByteArray? = null

    init {
        parseHeader()
        parseSectionIndex()
    }

    // -- Header & Index parsing --

    private fun parseHeader() {
        check(data.size >= HEADER.size) { "YES2: File too small for header" }
        for (i in HEADER.indices) {
            check(data[i] == HEADER[i]) { "YES2: Invalid header at byte $i" }
        }
    }

    private fun parseSectionIndex() {
        val br = BintexReader(data, SECTION_INDEX_OFFSET)

        val version = br.readUint8()
        check(version == 1) { "YES2: Unsupported section index version: $version" }

        val sectionCount = br.readInt()
        repeat(sectionCount) {
            val nameLen = br.readUint8()
            val nameBytes = br.readRaw(nameLen)
            // Section names are ASCII/Latin-1
            val name = String(CharArray(nameBytes.size) { (nameBytes[it].toInt() and 0xFF).toChar() })

            val offset = br.readInt()
            val attributesSize = br.readInt()
            val contentSize = br.readInt()
            br.skip(4) // reserved

            sectionEntries[name] = SectionEntry(name, offset, attributesSize, contentSize)
        }

        sectionDataStartOffset = br.pos
    }

    val sectionNames: List<String> get() = sectionEntries.keys.toList()

    // -- Section offsets --

    private fun getSectionAttributesOffset(name: String): Int? {
        val entry = sectionEntries[name] ?: return null
        return sectionDataStartOffset + entry.offset
    }

    private fun getSectionContentOffset(name: String): Int? {
        val entry = sectionEntries[name] ?: return null
        return sectionDataStartOffset + entry.offset + entry.attributesSize
    }

    private fun readSectionAttributes(name: String): Map<String, Any?>? {
        val offset = getSectionAttributesOffset(name) ?: return null
        val entry = sectionEntries[name]!!
        if (entry.attributesSize == 0) return null
        val br = BintexReader(data, offset)
        return br.readValueSimpleMap()
    }

    // -- Version Info --

    fun getVersionInfo(): Yes2VersionInfo {
        cachedVersionInfo?.let { return it }

        val offset = getSectionContentOffset("versionInfo")
            ?: error("YES2: versionInfo section not found")

        val br = BintexReader(data, offset)
        val map = br.readValueSimpleMap()

        val info = Yes2VersionInfo(
            shortName = map["shortName"] as? String,
            longName = map["longName"] as? String,
            description = map["description"] as? String,
            locale = map["locale"] as? String,
            bookCount = (map["book_count"] as? Int) ?: 0,
            hasPericopes = ((map["hasPericopes"] as? Int) ?: 0) != 0,
            textEncoding = (map["textEncoding"] as? Int) ?: 2,
        )
        cachedVersionInfo = info
        return info
    }

    // -- Books Info --

    fun getBooksInfo(): List<Yes2Book> {
        cachedBooksInfo?.let { return it }

        val offset = getSectionContentOffset("booksInfo")
            ?: error("YES2: booksInfo section not found")

        val br = BintexReader(data, offset)
        val bookCount = br.readInt()

        val books = ArrayList<Yes2Book>(bookCount)
        repeat(bookCount) {
            val map = br.readValueSimpleMap()
            books.add(
                Yes2Book(
                    bookId = (map["bookId"] as? Int) ?: -1,
                    shortName = map["shortName"] as? String,
                    abbreviation = map["abbreviation"] as? String,
                    offset = (map["offset"] as? Int) ?: 0,
                    chapterCount = (map["chapter_count"] as? Int) ?: 0,
                    verseCounts = (map["verse_counts"] as? IntArray) ?: intArrayOf(),
                    chapterOffsets = (map["chapter_offsets"] as? IntArray) ?: intArrayOf(),
                )
            )
        }

        cachedBooksInfo = books
        return books
    }

    /**
     * Find a book by bookId (1-based, e.g. Gen=1, Rev=66).
     */
    fun findBookByBookId(bookId: Int): Pair<Int, Yes2Book>? {
        val books = getBooksInfo()
        val index = books.indexOfFirst { it.bookId == bookId }
        return if (index >= 0) index to books[index] else null
    }

    // -- Text section --

    /**
     * Load verse texts for a specific book index and 1-based chapter.
     * Returns a list of verse strings (0-indexed within the returned list).
     */
    fun loadVerseText(bookIndex: Int, chapter1: Int): List<String> {
        val books = getBooksInfo()
        check(bookIndex in books.indices) { "YES2: Book index $bookIndex not found" }

        val book = books[bookIndex]
        check(chapter1 in 1..book.chapterCount) {
            "YES2: Chapter $chapter1 out of range for book $bookIndex"
        }

        val versionInfo = getVersionInfo()
        val textData = getDecompressedTextData()

        val contentOffset = book.offset + book.chapterOffsets[chapter1 - 1]
        val verseCount = book.verseCounts[chapter1 - 1]

        val br = BintexReader(textData, contentOffset)
        return readVerses(br, verseCount, versionInfo.textEncoding)
    }

    /**
     * Load a single verse by bookIndex, 1-based chapter, and 1-based verse.
     */
    fun loadSingleVerse(bookIndex: Int, chapter1: Int, verse1: Int): String? {
        val books = getBooksInfo()
        if (bookIndex !in books.indices) return null

        val book = books[bookIndex]
        if (chapter1 !in 1..book.chapterCount) return null
        if (verse1 < 1 || verse1 > book.verseCounts[chapter1 - 1]) return null

        val versionInfo = getVersionInfo()
        val textData = getDecompressedTextData()

        val contentOffset = book.offset + book.chapterOffsets[chapter1 - 1]
        val br = BintexReader(textData, contentOffset)

        // Skip to the target verse
        repeat(verse1 - 1) {
            val len = br.readVarUint()
            br.skip(len)
        }

        val len = br.readVarUint()
        val raw = br.readRaw(len)
        return decodeVerseBytes(raw, versionInfo.textEncoding)
    }

    private fun getDecompressedTextData(): ByteArray {
        cachedTextData?.let { return it }

        val sectionAttributes = readSectionAttributes("text")
        val contentOffset = getSectionContentOffset("text")
            ?: error("YES2: text section not found")

        val textData = decompressSection("text", sectionAttributes, contentOffset)
        cachedTextData = textData
        return textData
    }

    private fun decompressSection(
        sectionName: String,
        sectionAttributes: Map<String, Any?>?,
        contentOffset: Int,
    ): ByteArray {
        val entry = sectionEntries[sectionName]!!

        if (sectionAttributes == null) {
            return data.copyOfRange(contentOffset, contentOffset + entry.contentSize)
        }

        val compressionName = sectionAttributes["compression.name"] as? String
        if (compressionName == null) {
            return data.copyOfRange(contentOffset, contentOffset + entry.contentSize)
        }

        check(compressionName == "snappy-blocks") { "YES2: Unsupported compression: $compressionName" }

        @Suppress("UNCHECKED_CAST")
        val compressionInfo = sectionAttributes["compression.info"] as? Map<String, Any?> ?: emptyMap()
        val blockSize = (compressionInfo["block_size"] as? Int) ?: 0
        val compressedBlockSizes = (compressionInfo["compressed_block_sizes"] as? IntArray) ?: intArrayOf()

        check(blockSize > 0 && compressedBlockSizes.isNotEmpty()) {
            "YES2: Invalid snappy-blocks compression info"
        }

        // Compute offsets and decompress each block
        val blocks = ArrayList<ByteArray>(compressedBlockSizes.size)
        var totalSize = 0
        var blockOffset = 0
        for (compSize in compressedBlockSizes) {
            val blockData = data.copyOfRange(contentOffset + blockOffset, contentOffset + blockOffset + compSize)
            val decompressed = SnappyCodec.decompress(blockData)
            blocks.add(decompressed)
            totalSize += decompressed.size
            blockOffset += compSize
        }

        // Concatenate all decompressed blocks
        val result = ByteArray(totalSize)
        var pos = 0
        for (block in blocks) {
            block.copyInto(result, pos)
            pos += block.size
        }
        return result
    }

    private fun readVerses(br: BintexReader, verseCount: Int, textEncoding: Int): List<String> {
        val verses = ArrayList<String>(verseCount)
        repeat(verseCount) {
            val len = br.readVarUint()
            val raw = br.readRaw(len)
            verses.add(decodeVerseBytes(raw, textEncoding))
        }
        return verses
    }

    private fun decodeVerseBytes(raw: ByteArray, textEncoding: Int): String {
        return if (textEncoding == 1) {
            // Latin-1
            String(CharArray(raw.size) { (raw[it].toInt() and 0xFF).toChar() })
        } else {
            // UTF-8
            raw.decodeToString()
        }
    }

    // -- Pericopes --

    fun loadPericopes(): List<PericopeEntry> {
        val versionInfo = getVersionInfo()
        if (!versionInfo.hasPericopes) return emptyList()

        val sectionAttributes = readSectionAttributes("pericopes")
        val contentOffset = getSectionContentOffset("pericopes") ?: return emptyList()

        val sectionData = decompressSection("pericopes", sectionAttributes, contentOffset)
        val br = BintexReader(sectionData)

        val version = br.readUint8()
        check(version == 2 || version == 3) { "YES2: Unsupported pericope version: $version" }

        /* indexSize = */ br.readInt()
        val entryCount = br.readInt()

        val aris = IntArray(entryCount)
        val offsets = IntArray(entryCount)

        if (version == 2) {
            repeat(entryCount) { i ->
                aris[i] = br.readInt()
                offsets[i] = br.readInt()
            }
        } else {
            // version 3 — delta-encoded
            var lastAri = 0
            var lastOffset = 0
            repeat(entryCount) { i ->
                val dataAri = br.readUint16()
                val ari = if ((dataAri and 0x8000) == 0) {
                    (dataAri shl 16) or br.readUint16()
                } else {
                    lastAri + (dataAri and 0x7FFF)
                }
                aris[i] = ari
                lastAri = ari

                val dataOffset = br.readUint16()
                val offset = lastOffset + dataOffset
                offsets[i] = offset
                lastOffset = offset
            }
        }

        val dataStartPos = br.pos
        val result = ArrayList<PericopeEntry>(entryCount)

        repeat(entryCount) { i ->
            br.seek(dataStartPos + offsets[i])
            /* blockVersion = */ br.readUint8()
            val title = br.readValueString() ?: ""
            val parallelCount = br.readUint8()
            val parallels = ArrayList<String>(parallelCount)
            repeat(parallelCount) {
                br.readValueString()?.let { parallels.add(it) }
            }
            result.add(PericopeEntry(aris[i], title, parallels))
        }

        return result
    }

    // -- Xrefs & Footnotes --

    /**
     * Load cross-references. Returns map of ARIF → content string.
     */
    fun loadXrefs(): Map<Int, String> = loadArifSection("xrefs")

    /**
     * Load footnotes. Returns map of ARIF → content string.
     */
    fun loadFootnotes(): Map<Int, String> = loadArifSection("footnotes")

    private fun loadArifSection(sectionName: String): Map<Int, String> {
        val sectionAttributes = readSectionAttributes(sectionName)
        val contentOffset = getSectionContentOffset(sectionName) ?: return emptyMap()

        val sectionData = decompressSection(sectionName, sectionAttributes, contentOffset)
        val br = BintexReader(sectionData)

        val version = br.readUint8()
        check(version == 1) { "YES2: Unsupported $sectionName version: $version" }

        val entryCount = br.readInt()
        val arifs = IntArray(entryCount) { br.readInt() }
        val offsets = IntArray(entryCount) { br.readInt() }

        val dataStartPos = br.pos
        val result = LinkedHashMap<Int, String>(entryCount)

        repeat(entryCount) { i ->
            br.seek(dataStartPos + offsets[i])
            val content = br.readValueString()
            if (content != null) {
                result[arifs[i]] = content
            }
        }

        return result
    }

    companion object {
        private val HEADER = byteArrayOf(
            0x98.toByte(), 0x58, 0x0D, 0x0A, 0x00, 0x5D, 0xE0.toByte(), 0x02
        )
        private const val SECTION_INDEX_OFFSET = 12

        fun fromByteArray(data: ByteArray): Yes2Reader = Yes2Reader(data)
    }
}
