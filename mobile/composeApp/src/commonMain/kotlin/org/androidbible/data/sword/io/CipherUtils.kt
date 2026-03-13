package org.androidbible.data.sword.io

import org.androidbible.data.sword.SwordModuleConfig

object CipherUtils {

    fun applyCipher(data: ByteArray, config: SwordModuleConfig): ByteArray {
        val cipherKey = config.rawEntries["cipherkey"] ?: return data
        if (cipherKey.isEmpty()) return data
        val keyBytes = cipherKey.encodeToByteArray()
        val keyLen = keyBytes.size
        return ByteArray(data.size) { i ->
            (data[i].toInt() xor keyBytes[i % keyLen].toInt()).toByte()
        }
    }

    fun requiresCipherKey(config: SwordModuleConfig): Boolean =
        config.rawEntries.containsKey("cipherkey")

    fun isLocked(config: SwordModuleConfig): Boolean =
        config.rawEntries.containsKey("cipherkey") &&
            config.rawEntries["cipherkey"].isNullOrEmpty()
}
