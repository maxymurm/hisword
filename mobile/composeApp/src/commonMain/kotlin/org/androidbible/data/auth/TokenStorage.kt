package org.androidbible.data.auth

import com.russhwolf.settings.Settings
import com.russhwolf.settings.set

/**
 * Encrypted token storage abstraction.
 * Platform implementations should use:
 * - Android: EncryptedSharedPreferences (AndroidX Security)
 * - iOS: Keychain Services
 *
 * Fallback: multiplatform-settings (unencrypted but works everywhere).
 */
interface TokenStorage {
    fun getAccessToken(): String?
    fun setAccessToken(token: String)
    fun clearAccessToken()
    fun getRefreshToken(): String?
    fun setRefreshToken(token: String)
    fun clearRefreshToken()
    fun clearAll()
}

/**
 * Default implementation using multiplatform-settings.
 * Suitable for development; production apps should use platform-specific
 * encrypted storage via expect/actual.
 */
class SettingsTokenStorage(private val settings: Settings) : TokenStorage {

    override fun getAccessToken(): String? = settings.getStringOrNull(KEY_ACCESS_TOKEN)

    override fun setAccessToken(token: String) {
        settings[KEY_ACCESS_TOKEN] = token
    }

    override fun clearAccessToken() {
        settings.remove(KEY_ACCESS_TOKEN)
    }

    override fun getRefreshToken(): String? = settings.getStringOrNull(KEY_REFRESH_TOKEN)

    override fun setRefreshToken(token: String) {
        settings[KEY_REFRESH_TOKEN] = token
    }

    override fun clearRefreshToken() {
        settings.remove(KEY_REFRESH_TOKEN)
    }

    override fun clearAll() {
        settings.remove(KEY_ACCESS_TOKEN)
        settings.remove(KEY_REFRESH_TOKEN)
    }

    companion object {
        private const val KEY_ACCESS_TOKEN = "hisword_access_token"
        private const val KEY_REFRESH_TOKEN = "hisword_refresh_token"
    }
}
