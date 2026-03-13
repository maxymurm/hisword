package org.androidbible.data.sword.io

expect object FileSystem {
    fun exists(path: String): Boolean
    fun listDir(path: String): List<String>
    fun readText(path: String): String
}
