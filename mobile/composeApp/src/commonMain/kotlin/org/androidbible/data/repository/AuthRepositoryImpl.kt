package org.androidbible.data.repository

import com.russhwolf.settings.Settings
import com.russhwolf.settings.set
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.*
import org.androidbible.domain.repository.AuthRepository

class AuthRepositoryImpl(
    private val api: ApiService,
    private val db: AndroidBibleDatabase,
    private val settings: Settings,
) : AuthRepository {

    private val _currentUser = MutableStateFlow<User?>(null)
    private val _isLoggedIn = MutableStateFlow(settings.getStringOrNull(KEY_TOKEN) != null)

    override suspend fun login(request: LoginRequest): AuthToken {
        val response = api.login(request)
        saveToken(response.token)
        _currentUser.value = response.user
        _isLoggedIn.value = true
        return AuthToken(token = response.token, user = response.user)
    }

    override suspend fun register(request: RegisterRequest): AuthToken {
        val response = api.register(request)
        saveToken(response.token)
        _currentUser.value = response.user
        _isLoggedIn.value = true
        return AuthToken(token = response.token, user = response.user)
    }

    override suspend fun socialAuth(request: SocialAuthRequest): AuthToken {
        val response = api.socialAuth(request)
        saveToken(response.token)
        _currentUser.value = response.user
        _isLoggedIn.value = true
        return AuthToken(token = response.token, user = response.user)
    }

    override suspend fun forgotPassword(email: String) {
        api.forgotPassword(ForgotPasswordRequest(email))
    }

    override suspend fun changePassword(request: ChangePasswordRequest) {
        api.changePassword(request)
    }

    override suspend fun updateProfile(request: UpdateProfileRequest): User {
        val user = api.updateProfile(request)
        _currentUser.value = user
        return user
    }

    override suspend fun deleteAccount() {
        api.deleteAccount()
        clearToken()
        _currentUser.value = null
        _isLoggedIn.value = false
    }

    override suspend fun logout() {
        try {
            api.logout()
        } catch (_: Exception) {
            // Best effort
        }
        clearToken()
        _currentUser.value = null
        _isLoggedIn.value = false
    }

    override suspend fun getProfile(): User {
        val user = api.getProfile()
        _currentUser.value = user
        return user
    }

    override fun isLoggedIn(): Flow<Boolean> = _isLoggedIn

    override fun getCurrentUser(): Flow<User?> = _currentUser

    override fun getToken(): String? = settings.getStringOrNull(KEY_TOKEN)

    private fun saveToken(token: String) {
        settings[KEY_TOKEN] = token
    }

    private fun clearToken() {
        settings.remove(KEY_TOKEN)
    }

    companion object {
        private const val KEY_TOKEN = "auth_token"
    }
}
