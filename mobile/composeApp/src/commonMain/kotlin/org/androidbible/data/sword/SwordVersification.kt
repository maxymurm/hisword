package org.androidbible.data.sword

object SwordVersification {

    data class BookDef(
        val name: String,
        val osisId: String,
        val abbrev: String,
        val chapterCount: Int,
    )

    val otBooks: List<BookDef> = listOf(
        BookDef("Genesis", "Gen", "Gen", 50),
        BookDef("Exodus", "Exod", "Exod", 40),
        BookDef("Leviticus", "Lev", "Lev", 27),
        BookDef("Numbers", "Num", "Num", 36),
        BookDef("Deuteronomy", "Deut", "Deut", 34),
        BookDef("Joshua", "Josh", "Josh", 24),
        BookDef("Judges", "Judg", "Judg", 21),
        BookDef("Ruth", "Ruth", "Ruth", 4),
        BookDef("I Samuel", "1Sam", "1Sam", 31),
        BookDef("II Samuel", "2Sam", "2Sam", 24),
        BookDef("I Kings", "1Kgs", "1Kgs", 22),
        BookDef("II Kings", "2Kgs", "2Kgs", 25),
        BookDef("I Chronicles", "1Chr", "1Chr", 29),
        BookDef("II Chronicles", "2Chr", "2Chr", 36),
        BookDef("Ezra", "Ezra", "Ezra", 10),
        BookDef("Nehemiah", "Neh", "Neh", 13),
        BookDef("Esther", "Esth", "Esth", 10),
        BookDef("Job", "Job", "Job", 42),
        BookDef("Psalms", "Ps", "Ps", 150),
        BookDef("Proverbs", "Prov", "Prov", 31),
        BookDef("Ecclesiastes", "Eccl", "Eccl", 12),
        BookDef("Song of Solomon", "Song", "Song", 8),
        BookDef("Isaiah", "Isa", "Isa", 66),
        BookDef("Jeremiah", "Jer", "Jer", 52),
        BookDef("Lamentations", "Lam", "Lam", 5),
        BookDef("Ezekiel", "Ezek", "Ezek", 48),
        BookDef("Daniel", "Dan", "Dan", 12),
        BookDef("Hosea", "Hos", "Hos", 14),
        BookDef("Joel", "Joel", "Joel", 3),
        BookDef("Amos", "Amos", "Amos", 9),
        BookDef("Obadiah", "Obad", "Obad", 1),
        BookDef("Jonah", "Jonah", "Jonah", 4),
        BookDef("Micah", "Mic", "Mic", 7),
        BookDef("Nahum", "Nah", "Nah", 3),
        BookDef("Habakkuk", "Hab", "Hab", 3),
        BookDef("Zephaniah", "Zeph", "Zeph", 3),
        BookDef("Haggai", "Hag", "Hag", 2),
        BookDef("Zechariah", "Zech", "Zech", 14),
        BookDef("Malachi", "Mal", "Mal", 4),
    )

    val ntBooks: List<BookDef> = listOf(
        BookDef("Matthew", "Matt", "Matt", 28),
        BookDef("Mark", "Mark", "Mark", 16),
        BookDef("Luke", "Luke", "Luke", 24),
        BookDef("John", "John", "John", 21),
        BookDef("Acts", "Acts", "Acts", 28),
        BookDef("Romans", "Rom", "Rom", 16),
        BookDef("I Corinthians", "1Cor", "1Cor", 16),
        BookDef("II Corinthians", "2Cor", "2Cor", 13),
        BookDef("Galatians", "Gal", "Gal", 6),
        BookDef("Ephesians", "Eph", "Eph", 6),
        BookDef("Philippians", "Phil", "Phil", 4),
        BookDef("Colossians", "Col", "Col", 4),
        BookDef("I Thessalonians", "1Thess", "1Thess", 5),
        BookDef("II Thessalonians", "2Thess", "2Thess", 3),
        BookDef("I Timothy", "1Tim", "1Tim", 6),
        BookDef("II Timothy", "2Tim", "2Tim", 4),
        BookDef("Titus", "Titus", "Titus", 3),
        BookDef("Philemon", "Phlm", "Phlm", 1),
        BookDef("Hebrews", "Heb", "Heb", 13),
        BookDef("James", "Jas", "Jas", 5),
        BookDef("I Peter", "1Pet", "1Pet", 5),
        BookDef("II Peter", "2Pet", "2Pet", 3),
        BookDef("I John", "1John", "1John", 5),
        BookDef("II John", "2John", "2John", 1),
        BookDef("III John", "3John", "3John", 1),
        BookDef("Jude", "Jude", "Jude", 1),
        BookDef("Revelation of John", "Rev", "Rev", 22),
    )

    val allBooks: List<BookDef> = otBooks + ntBooks

    val versesPerChapter: IntArray = intArrayOf(
        // Genesis (50 chapters)
        31, 25, 24, 26, 32, 22, 24, 22, 29, 32,
        32, 20, 18, 24, 21, 16, 27, 33, 38, 18,
        34, 24, 20, 67, 34, 35, 46, 22, 35, 43,
        55, 32, 20, 31, 29, 43, 36, 30, 23, 23,
        57, 38, 34, 34, 28, 34, 31, 22, 33, 26,
        // Exodus (40)
        22, 25, 22, 31, 23, 30, 25, 32, 35, 29,
        10, 51, 22, 31, 27, 36, 16, 27, 25, 26,
        36, 31, 33, 18, 40, 37, 21, 43, 46, 38,
        18, 35, 23, 35, 35, 38, 29, 31, 43, 38,
        // Leviticus (27)
        17, 16, 17, 35, 19, 30, 38, 36, 24, 20,
        47, 8, 59, 57, 33, 34, 16, 30, 37, 27,
        24, 33, 44, 23, 55, 46, 34,
        // Numbers (36)
        54, 34, 51, 49, 31, 27, 89, 26, 23, 36,
        35, 16, 33, 45, 41, 50, 13, 32, 22, 29,
        35, 41, 30, 25, 18, 65, 23, 31, 40, 16,
        54, 42, 56, 29, 34, 13,
        // Deuteronomy (34)
        46, 37, 29, 49, 33, 25, 26, 20, 29, 22,
        32, 32, 18, 29, 23, 22, 20, 22, 21, 20,
        23, 30, 25, 22, 19, 19, 26, 68, 29, 20,
        30, 52, 29, 12,
        // Joshua (24)
        18, 24, 17, 24, 15, 27, 26, 35, 27, 43,
        23, 24, 33, 15, 63, 10, 18, 28, 51, 9,
        45, 34, 16, 33,
        // Judges (21)
        36, 23, 31, 24, 31, 40, 25, 35, 57, 18,
        40, 15, 25, 20, 20, 31, 13, 31, 30, 48,
        25,
        // Ruth (4)
        22, 23, 18, 22,
        // I Samuel (31)
        28, 36, 21, 22, 12, 21, 17, 22, 27, 27,
        15, 25, 23, 52, 35, 23, 58, 30, 24, 42,
        15, 23, 29, 22, 44, 25, 12, 25, 11, 31,
        13,
        // II Samuel (24)
        27, 32, 39, 12, 25, 23, 29, 18, 13, 19,
        27, 31, 39, 33, 37, 23, 29, 33, 43, 26,
        22, 51, 39, 25,
        // I Kings (22)
        53, 46, 28, 34, 18, 38, 51, 66, 28, 29,
        43, 33, 34, 31, 34, 34, 24, 46, 21, 43,
        29, 53,
        // II Kings (25)
        18, 25, 27, 44, 27, 33, 20, 29, 37, 36,
        21, 21, 25, 29, 38, 20, 41, 37, 37, 21,
        26, 20, 37, 20, 30,
        // I Chronicles (29)
        54, 55, 24, 43, 26, 81, 40, 40, 44, 14,
        47, 40, 14, 17, 29, 43, 27, 17, 19, 8,
        30, 19, 32, 31, 31, 32, 34, 21, 30,
        // II Chronicles (36)
        17, 18, 17, 22, 14, 42, 22, 18, 31, 19,
        23, 16, 22, 15, 19, 14, 19, 34, 11, 37,
        20, 12, 21, 27, 28, 23, 9, 27, 36, 27,
        21, 33, 25, 33, 27, 23,
        // Ezra (10)
        11, 70, 13, 24, 17, 22, 28, 36, 15, 44,
        // Nehemiah (13)
        11, 20, 32, 23, 19, 19, 73, 18, 38, 39,
        36, 47, 31,
        // Esther (10)
        22, 23, 15, 17, 14, 14, 10, 17, 32, 3,
        // Job (42)
        22, 13, 26, 21, 27, 30, 21, 22, 35, 22,
        20, 25, 28, 22, 35, 22, 16, 21, 29, 29,
        34, 30, 17, 25, 6, 14, 23, 28, 25, 31,
        40, 22, 33, 37, 16, 33, 24, 41, 30, 24,
        34, 17,
        // Psalms (150)
        6, 12, 8, 8, 12, 10, 17, 9, 20, 18,
        7, 8, 6, 7, 5, 11, 15, 50, 14, 9,
        13, 31, 6, 10, 22, 12, 14, 9, 11, 12,
        24, 11, 22, 22, 28, 12, 40, 22, 13, 17,
        13, 11, 5, 26, 17, 11, 9, 14, 20, 23,
        19, 9, 6, 7, 23, 13, 11, 11, 17, 12,
        8, 12, 11, 10, 13, 20, 7, 35, 36, 5,
        24, 20, 28, 23, 10, 12, 20, 72, 13, 19,
        16, 8, 18, 12, 13, 17, 7, 18, 52, 17,
        16, 15, 5, 23, 11, 13, 12, 9, 9, 5,
        8, 28, 22, 35, 45, 48, 43, 13, 31, 7,
        10, 10, 9, 8, 18, 19, 2, 29, 176, 7,
        8, 9, 4, 8, 5, 6, 5, 6, 8, 8,
        3, 18, 3, 3, 21, 26, 9, 8, 24, 13,
        10, 7, 12, 15, 21, 10, 20, 14, 9, 6,
        // Proverbs (31)
        33, 22, 35, 27, 23, 35, 27, 36, 18, 32,
        31, 28, 25, 35, 33, 33, 28, 24, 29, 30,
        31, 29, 35, 34, 28, 28, 27, 28, 27, 33,
        31,
        // Ecclesiastes (12)
        18, 26, 22, 16, 20, 12, 29, 17, 18, 20,
        10, 14,
        // Song of Solomon (8)
        17, 17, 11, 16, 16, 13, 13, 14,
        // Isaiah (66)
        31, 22, 26, 6, 30, 13, 25, 22, 21, 34,
        16, 6, 22, 32, 9, 14, 14, 7, 25, 6,
        17, 25, 18, 23, 12, 21, 13, 29, 24, 33,
        9, 20, 24, 17, 10, 22, 38, 22, 8, 31,
        29, 25, 28, 28, 25, 13, 15, 22, 26, 11,
        23, 15, 12, 17, 13, 12, 21, 14, 21, 22,
        11, 12, 19, 12, 25, 24,
        // Jeremiah (52)
        19, 37, 25, 31, 31, 30, 34, 22, 26, 25,
        23, 17, 27, 22, 21, 21, 27, 23, 15, 18,
        14, 30, 40, 10, 38, 24, 22, 17, 32, 24,
        40, 44, 26, 22, 19, 32, 21, 28, 18, 16,
        18, 22, 13, 30, 5, 28, 7, 47, 39, 46,
        64, 34,
        // Lamentations (5)
        22, 22, 66, 22, 22,
        // Ezekiel (48)
        28, 10, 27, 17, 17, 14, 27, 18, 11, 22,
        25, 28, 23, 23, 8, 63, 24, 32, 14, 49,
        32, 31, 49, 27, 17, 21, 36, 26, 21, 26,
        18, 32, 33, 31, 15, 38, 28, 23, 29, 49,
        26, 20, 27, 31, 25, 24, 23, 35,
        // Daniel (12)
        21, 49, 30, 37, 31, 28, 28, 27, 27, 21,
        45, 13,
        // Hosea (14)
        11, 23, 5, 19, 15, 11, 16, 14, 17, 15,
        12, 14, 16, 9,
        // Joel (3)
        20, 32, 21,
        // Amos (9)
        15, 16, 15, 13, 27, 14, 17, 14, 15,
        // Obadiah (1)
        21,
        // Jonah (4)
        17, 10, 10, 11,
        // Micah (7)
        16, 13, 12, 13, 15, 16, 20,
        // Nahum (3)
        15, 13, 19,
        // Habakkuk (3)
        17, 20, 19,
        // Zephaniah (3)
        18, 15, 20,
        // Haggai (2)
        15, 23,
        // Zechariah (14)
        21, 13, 10, 14, 11, 15, 14, 23, 17, 12,
        17, 14, 9, 21,
        // Malachi (4)
        14, 17, 18, 6,
        // ─── NEW TESTAMENT ───
        // Matthew (28)
        25, 23, 17, 25, 48, 34, 29, 34, 38, 42,
        30, 50, 58, 36, 39, 28, 27, 35, 30, 34,
        46, 46, 39, 51, 46, 75, 66, 20,
        // Mark (16)
        45, 28, 35, 41, 43, 56, 37, 38, 50, 52,
        33, 44, 37, 72, 47, 20,
        // Luke (24)
        80, 52, 38, 44, 39, 49, 50, 56, 62, 42,
        54, 59, 35, 35, 32, 31, 37, 43, 48, 47,
        38, 71, 56, 53,
        // John (21)
        51, 25, 36, 54, 47, 71, 53, 59, 41, 42,
        57, 50, 38, 31, 27, 33, 26, 40, 42, 31,
        25,
        // Acts (28)
        26, 47, 26, 37, 42, 15, 60, 40, 43, 48,
        30, 25, 52, 28, 41, 40, 34, 28, 41, 38,
        40, 30, 35, 27, 27, 32, 44, 31,
        // Romans (16)
        32, 29, 31, 25, 21, 23, 25, 39, 33, 21,
        36, 21, 14, 23, 33, 27,
        // I Corinthians (16)
        31, 16, 23, 21, 13, 20, 40, 13, 27, 33,
        34, 31, 13, 40, 58, 24,
        // II Corinthians (13)
        24, 17, 18, 18, 21, 18, 16, 24, 15, 18,
        33, 21, 14,
        // Galatians (6)
        24, 21, 29, 31, 26, 18,
        // Ephesians (6)
        23, 22, 21, 32, 33, 24,
        // Philippians (4)
        30, 30, 21, 23,
        // Colossians (4)
        29, 23, 25, 18,
        // I Thessalonians (5)
        10, 20, 13, 18, 28,
        // II Thessalonians (3)
        12, 17, 18,
        // I Timothy (6)
        20, 15, 16, 16, 25, 21,
        // II Timothy (4)
        18, 26, 17, 22,
        // Titus (3)
        16, 15, 15,
        // Philemon (1)
        25,
        // Hebrews (13)
        14, 18, 19, 16, 14, 20, 28, 13, 28, 39,
        40, 29, 25,
        // James (5)
        27, 26, 18, 17, 20,
        // I Peter (5)
        25, 25, 22, 19, 14,
        // II Peter (3)
        21, 22, 18,
        // I John (5)
        10, 29, 24, 21, 21,
        // II John (1)
        13,
        // III John (1)
        14,
        // Jude (1)
        25,
        // Revelation (22)
        20, 29, 22, 11, 14, 17, 17, 13, 21, 11,
        19, 17, 18, 20, 8, 21, 18, 24, 21, 15,
        27, 21,
    )

    private val bookChapterOffset: IntArray by lazy {
        val offsets = IntArray(allBooks.size)
        var offset = 0
        for (i in allBooks.indices) {
            offsets[i] = offset
            offset += allBooks[i].chapterCount
        }
        offsets
    }

    fun getVerseCount(bookIndex: Int, chapter: Int): Int {
        val offset = bookChapterOffset[bookIndex] + (chapter - 1)
        return versesPerChapter[offset]
    }

    fun getTestament(bookIndex: Int): Int = if (bookIndex < otBooks.size) 0 else 1

    fun getTestamentBookIndex(bookIndex: Int): Int =
        if (bookIndex < otBooks.size) bookIndex else bookIndex - otBooks.size

    fun computeLinearIndex(
        testamentBookIndex: Int,
        chapter: Int,
        verse: Int,
        books: List<BookDef>,
    ): Long {
        val globalBookOffset = if (books === otBooks) 0 else otBooks.size

        var index = 1L

        for (b in 0 until testamentBookIndex) {
            index += 1
            val bookGlobalIdx = globalBookOffset + b
            val chapOffset = bookChapterOffset[bookGlobalIdx]
            for (c in 0 until books[b].chapterCount) {
                index += 1
                index += versesPerChapter[chapOffset + c]
            }
        }

        index += 1

        val currentBookGlobalIdx = globalBookOffset + testamentBookIndex
        val chapOffset = bookChapterOffset[currentBookGlobalIdx]
        for (c in 0 until (chapter - 1)) {
            index += 1
            index += versesPerChapter[chapOffset + c]
        }

        index += 1
        index += verse

        return index
    }

    fun findBookByOsisId(osisId: String): Pair<Int, BookDef>? {
        for (i in allBooks.indices) {
            if (allBooks[i].osisId.equals(osisId, ignoreCase = true) ||
                allBooks[i].abbrev.equals(osisId, ignoreCase = true)
            ) {
                return i to allBooks[i]
            }
        }
        return null
    }

    fun findBookByName(name: String): Pair<Int, BookDef>? {
        for (i in allBooks.indices) {
            if (allBooks[i].name.equals(name, ignoreCase = true)) {
                return i to allBooks[i]
            }
        }
        for (i in allBooks.indices) {
            if (allBooks[i].name.startsWith(name, ignoreCase = true)) {
                return i to allBooks[i]
            }
        }
        return null
    }
}
