package org.androidbible.data.bintex

/**
 * Version info extracted from the versionInfo section of a YES2 file.
 */
data class Yes2VersionInfo(
    val shortName: String?,
    val longName: String?,
    val description: String?,
    val locale: String?,
    val bookCount: Int,
    val hasPericopes: Boolean,
    val textEncoding: Int, // 1=Latin-1, 2=UTF-8
)
