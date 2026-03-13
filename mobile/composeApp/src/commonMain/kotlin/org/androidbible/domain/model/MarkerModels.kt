package org.androidbible.domain.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Marker(
    val id: Long = 0,
    val gid: String = "",
    val userId: Long? = null,
    val bibleVersionId: Long? = null,
    val ari: Int,
    val kind: Int,
    val caption: String = "",
    val verseCount: Int = 1,
    val color: Int? = null,
    val createdAt: String? = null,
    val updatedAt: String? = null,
) {
    companion object {
        const val KIND_BOOKMARK = 0
        const val KIND_NOTE = 1
        const val KIND_HIGHLIGHT = 2
    }

    val isBookmark get() = kind == KIND_BOOKMARK
    val isNote get() = kind == KIND_NOTE
    val isHighlight get() = kind == KIND_HIGHLIGHT
}

@Serializable
data class Label(
    val id: Long = 0,
    val gid: String = "",
    val userId: Long? = null,
    val title: String,
    @SerialName("background_color")
    val backgroundColor: Int? = null,
    val createdAt: String? = null,
    val updatedAt: String? = null,
)

@Serializable
data class ProgressMark(
    val id: Long = 0,
    val gid: String = "",
    val userId: Long? = null,
    val preset: Int,
    val ari: Int,
    val caption: String = "",
    val modifyTime: String? = null,
    val createdAt: String? = null,
    val updatedAt: String? = null,
)

@Serializable
data class ProgressMarkHistory(
    val id: Long = 0,
    val progressMarkId: Long,
    val ari: Int,
    val createdAt: String? = null,
)
