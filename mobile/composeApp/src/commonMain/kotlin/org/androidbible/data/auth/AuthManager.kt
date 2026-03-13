package org.androidbible.data.auth

import io.github.aakira.napier.Napier
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import org.androidbible.data.remote.ApiService
import org.androidbible.data.remote.HisWordApiClient
import org.androidbible.data.remote.TokenRefresher
import org.androidbible.domain.model.*
import org.androidbible.domain.repository.AuthRepository

/**
 * Enhanced Auth API with:
 * - Token refresh flow
 * - Social auth (Google, Apple)
 * - Encrypted token storage
 */
class AuthManager(
    private val apiClient: HisWordApiClient,
    private val tokenStorage: TokenStorage,
) : AuthRepository, TokenRefresher {

    private val _currentUser = MutableStateFlow<User?>(null)
    private val _isLoggedIn = MutableStateFlow(tokenStorage.getAccessToken() != null)

    override suspend fun login(request: LoginRequest): AuthToken {
        return apiClient.execute {
            val response = login(request)
            onAuthSuccess(response.token, response.user)
            AuthToken(token = response.token, user = response.user)
        }
    }

    override suspend fun register(request: RegisterRequest): AuthToken {
        return apiClient.execute {
            val response = register(request)
            onAuthSuccess(response.token, response.user)
            AuthToken(token = response.token, user = response.user)
        }
    }

    override suspend fun socialAuth(request: SocialAuthRequest): AuthToken {
        return apiClient.execute {
            val response = socialAuth(request)
            onAuthSuccess(response.token, response.user)
            AuthToken(token = response.token, user = response.user)
        }
    }

    override suspend fun forgotPassword(email: String) {
        apiClient.execute { forgotPassword(ForgotPasswordRequest(email)) }
    }

    override suspend fun changePassword(request: ChangePasswordRequest) {
        apiClient.execute { changePassword(request) }
    }

    override suspend fun updateProfile(request: UpdateProfileRequest): User {
        return apiClient.execute {
            val user = updateProfile(request)
            _currentUser.value = user
            user
        }
    }

    override suspend fun deleteAccount() {
        apiClient.execute { deleteAccount() }
        onLogout()
    }

    override suspend fun logout() {
        try {
            apiClient.execute(maxRetries = 0) { logout() }
        } catch (_: Exception) {
            // Best-effort server-side logout
        }
        onLogout()
    }

    override suspend fun getProfile(): User {
        return apiClient.execute {
            val user = getProfile()
            _currentUser.value = user
            user
        }
    }

    override fun isLoggedIn(): Flow<Boolean> = _isLoggedIn
    override fun getCurrentUser(): Flow<User?> = _currentUser
    override fun getToken(): String? = tokenStorage.getAccessToken()

    // ── TokenRefresher ───────────────────────────────────

    override suspend fun refreshToken(): Boolean {
        // Laravel Sanctum doesn't have a refresh endpoint by default.
        // If the token is expired, the user needs to re-login.
        // Clear the invalid token to prevent infinite retry loops.
        Napier.w("Token expired, clearing token", tag = "Auth")
        tokenStorage.clearAccessToken()
        _isLoggedIn.value = false
        return false
    }

    // ── Private ──────────────────────────────────────────

    private fun onAuthSuccess(token: String, user: User) {
        tokenStorage.setAccessToken(token)
        _currentUser.value = user
        _isLoggedIn.value = true
    }

    private fun onLogout() {
        tokenStorage.clearAll()
        _currentUser.value = null
        _isLoggedIn.value = false
    }
}
