package org.androidbible.data.auth

import io.github.aakira.napier.Napier

/**
 * Android Google Sign-In via Credential Manager.
 * Requires google-services.json with OAuth 2.0 Web Client ID.
 *
 * Full integration requires:
 *   implementation("androidx.credentials:credentials:1.3.0")
 *   implementation("com.google.android.libraries.identity.googleid:googleid:1.1.1")
 */
actual class GoogleSignInManager {

    actual suspend fun signIn(): SocialSignInResult? {
        // TODO: integrate Credential Manager when dependency is added
        //   val credentialManager = CredentialManager.create(context)
        //   val googleIdOption = GetGoogleIdOption.Builder()
        //       .setServerClientId(WEB_CLIENT_ID)
        //       .build()
        //   val request = GetCredentialRequest.Builder().addCredentialOption(googleIdOption).build()
        //   val result = credentialManager.getCredential(context, request)
        //   val googleIdToken = (result.credential as? GoogleIdTokenCredential)?.idToken
        Napier.w("GoogleSignIn: stub — add Credential Manager dependency", tag = "Auth")
        return null
    }

    actual fun signOut() {
        Napier.d("GoogleSignIn: signOut stub", tag = "Auth")
    }
}

/**
 * Android Apple Sign-In: delegates to web-based Apple OAuth.
 * Not a native flow on Android. Returns null.
 */
actual class AppleSignInManager {
    actual suspend fun signIn(): SocialSignInResult? {
        Napier.d("AppleSignIn: not available on Android", tag = "Auth")
        return null
    }
}
