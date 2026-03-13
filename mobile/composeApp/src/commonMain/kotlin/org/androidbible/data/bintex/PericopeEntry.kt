package org.androidbible.data.bintex

/**
 * A pericope (section heading) within a YES2 file.
 */
data class PericopeEntry(
    val ari: Int,
    val title: String,
    val parallels: List<String> = emptyList(),
)
