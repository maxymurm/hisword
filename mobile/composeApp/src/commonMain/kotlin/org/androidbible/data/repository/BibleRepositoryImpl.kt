package org.androidbible.data.repository

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import app.cash.sqldelight.coroutines.mapToOneOrNull
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.*
import org.androidbible.domain.repository.BibleRepository
import org.androidbible.util.Ari

class BibleRepositoryImpl(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
) : BibleRepository {

    override fun getVersions(): Flow<List<BibleVersion>> {
        return db.bibleQueries.getAllVersions().asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toBibleVersion() }
        }
    }

    override suspend fun getVersion(id: Long): BibleVersion? {
        return db.bibleQueries.getVersionById(id).executeAsOneOrNull()?.toBibleVersion()
    }

    override fun getBooks(versionId: Long): Flow<List<Book>> {
        return db.bibleQueries.getBooksByVersion(versionId).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toBook() }
        }
    }

    override suspend fun getBook(versionId: Long, bookId: Int): Book? {
        return db.bibleQueries.getBook(versionId, bookId.toLong()).executeAsOneOrNull()?.toBook()
    }

    override fun getChapter(versionId: Long, bookId: Int, chapter: Int): Flow<Chapter> {
        return db.bibleQueries.getVersesByChapter(versionId, bookId.toLong(), chapter.toLong())
            .asFlow()
            .mapToList(Dispatchers.IO)
            .map { rows ->
                Chapter(
                    bookId = bookId,
                    chapter = chapter,
                    verses = rows.map { it.toVerse() }
                )
            }
    }

    override suspend fun getVerse(versionId: Long, ari: Int): Verse? {
        return db.bibleQueries.getVerseByAri(versionId, ari.toLong()).executeAsOneOrNull()?.toVerse()
    }

    override suspend fun searchVerses(versionId: Long, query: String): List<SearchResult> {
        return db.bibleQueries.searchVerses(versionId, query).executeAsList().map { row ->
            val verse = row.toVerse()
            val bookName = db.bibleQueries.getBook(versionId, verse.bookId.toLong())
                .executeAsOneOrNull()?.short_name ?: "?"
            SearchResult(verse = verse, bookName = bookName)
        }
    }

    override suspend fun syncVersions() {
        val versions = api.getVersions()
        db.transaction {
            versions.forEach { v ->
                db.bibleQueries.insertVersion(
                    id = v.id,
                    short_name = v.shortName,
                    long_name = v.longName,
                    language_code = v.languageCode,
                    locale = v.locale,
                    description = v.description,
                    sort_order = v.sortOrder.toLong(),
                    has_old_testament = if (v.hasOldTestament) 1L else 0L,
                    has_new_testament = if (v.hasNewTestament) 1L else 0L,
                    is_active = if (v.isActive) 1L else 0L,
                    created_at = null,
                    updated_at = null,
                )
            }
        }
    }

    override suspend fun syncBooks(versionId: Long) {
        val books = api.getBooks(versionId)
        db.transaction {
            books.forEach { b ->
                db.bibleQueries.insertBook(
                    id = b.id,
                    bible_version_id = b.bibleVersionId,
                    book_id = b.bookId.toLong(),
                    short_name = b.shortName,
                    long_name = b.longName,
                    abbreviation = b.abbreviation,
                    chapter_count = b.chapterCount.toLong(),
                    sort_order = b.sortOrder.toLong(),
                )
            }
        }
    }

    override suspend fun syncChapter(versionId: Long, bookId: Int, chapter: Int) {
        val response = api.getChapter(versionId, bookId, chapter)
        db.transaction {
            db.bibleQueries.deleteVersesByChapter(versionId, bookId.toLong(), chapter.toLong())
            response.verses.forEach { v ->
                val ari = Ari.encode(v.bookId, v.chapter, v.verse)
                db.bibleQueries.insertVerse(
                    id = v.id,
                    bible_version_id = versionId,
                    ari = ari.toLong(),
                    book_id = v.bookId.toLong(),
                    chapter = v.chapter.toLong(),
                    verse = v.verse.toLong(),
                    text = v.text,
                    text_without_formatting = v.textWithoutFormatting,
                )
            }
            response.pericopes.forEach { p ->
                db.bibleQueries.insertPericope(
                    id = p.id,
                    bible_version_id = versionId,
                    ari = p.ari.toLong(),
                    title = p.title,
                )
            }
        }
    }
}

// ========== Extension mappers ==========

private fun org.androidbible.data.local.Bible_versions.toBibleVersion() = BibleVersion(
    id = id,
    shortName = short_name,
    longName = long_name,
    languageCode = language_code,
    locale = locale,
    description = description,
    sortOrder = sort_order.toInt(),
    hasOldTestament = has_old_testament == 1L,
    hasNewTestament = has_new_testament == 1L,
    isActive = is_active == 1L,
)

private fun org.androidbible.data.local.Books.toBook() = Book(
    id = id,
    bibleVersionId = bible_version_id,
    bookId = book_id.toInt(),
    shortName = short_name,
    longName = long_name,
    abbreviation = abbreviation,
    chapterCount = chapter_count.toInt(),
    sortOrder = sort_order.toInt(),
)

private fun org.androidbible.data.local.Verses.toVerse() = Verse(
    id = id,
    bibleVersionId = bible_version_id,
    ari = ari.toInt(),
    bookId = book_id.toInt(),
    chapter = chapter.toInt(),
    verse = verse.toInt(),
    text = text,
    textWithoutFormatting = text_without_formatting,
)
