package org.androidbible.data.bintex

/**
 * Section index entry from the YES2 header.
 */
data class SectionEntry(
    val name: String,
    val offset: Int,
    val attributesSize: Int,
    val contentSize: Int,
)
