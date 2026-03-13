package org.androidbible.domain.model

import kotlinx.serialization.Serializable

@Serializable
data class BibleVersion(
    val id: Long = 0,
    val shortName: String,
    val longName: String,
    val languageCode: String,
    val locale: String? = null,
    val description: String? = null,
    val sortOrder: Int = 0,
    val hasOldTestament: Boolean = true,
    val hasNewTestament: Boolean = true,
    val isActive: Boolean = true,
)

@Serializable
data class Book(
    val id: Long = 0,
    val bibleVersionId: Long,
    val bookId: Int,
    val shortName: String,
    val longName: String,
    val abbreviation: String? = null,
    val chapterCount: Int,
    val sortOrder: Int = 0,
)

@Serializable
data class Verse(
    val id: Long = 0,
    val bibleVersionId: Long,
    val ari: Int,
    val bookId: Int,
    val chapter: Int,
    val verse: Int,
    val text: String,
    val textWithoutFormatting: String? = null,
)

@Serializable
data class Chapter(
    val bookId: Int,
    val chapter: Int,
    val verses: List<Verse> = emptyList(),
)

@Serializable
data class Pericope(
    val id: Long = 0,
    val bibleVersionId: Long,
    val ari: Int,
    val title: String,
)

@Serializable
data class CrossReference(
    val id: Long = 0,
    val bibleVersionId: Long,
    val fromAri: Int,
    val toAri: Int,
)

@Serializable
data class Footnote(
    val id: Long = 0,
    val bibleVersionId: Long,
    val ari: Int,
    val content: String,
)

@Serializable
data class SearchResult(
    val verse: Verse,
    val bookName: String,
    val highlights: List<IntRange> = emptyList(),
)
