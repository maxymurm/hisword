package org.androidbible.data.auth

import org.androidbible.domain.model.SocialAuthRequest

/**
 * Result from a platform social sign-in flow.
 */
data class SocialSignInResult(
    val provider: String,       // "google" or "apple"
    val idToken: String,        // Platform ID token
    val email: String? = null,
    val displayName: String? = null,
)

/**
 * Platform abstraction for social sign-in.
 * Implementations:
 *  - Android: Credential Manager (Google) + passthrough (Apple)
 *  - iOS: GIDSignIn (Google) + ASAuthorizationController (Apple)
 */
expect class GoogleSignInManager {
    /**
     * Launch the Google sign-in flow and return the ID token.
     * Returns null if the user cancelled or an error occurred.
     */
    suspend fun signIn(): SocialSignInResult?

    /** Sign out the current Google session. */
    fun signOut()
}

expect class AppleSignInManager {
    /**
     * Launch Apple Sign-In and return the ID token.
     * Returns null on platforms where Apple Sign-In is unavailable.
     */
    suspend fun signIn(): SocialSignInResult?
}

/**
 * Convenience: convert a SocialSignInResult to the API request model.
 */
fun SocialSignInResult.toAuthRequest(): SocialAuthRequest {
    return SocialAuthRequest(
        provider = provider,
        token = idToken,
        email = email,
        name = displayName,
    )
}
