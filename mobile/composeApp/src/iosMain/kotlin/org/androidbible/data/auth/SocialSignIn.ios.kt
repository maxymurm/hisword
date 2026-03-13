package org.androidbible.data.auth

import io.github.aakira.napier.Napier

/**
 * iOS Google Sign-In via Google Sign-In SDK (GIDSignIn).
 *
 * Full integration requires:
 *   cocoapods { pod("GoogleSignIn") }
 *   or SPM GoogleSignIn package
 */
actual class GoogleSignInManager {

    actual suspend fun signIn(): SocialSignInResult? {
        // TODO: integrate GIDSignIn when CocoaPod is added
        //   GIDSignIn.sharedInstance.signIn(withPresenting: rootVC) { result, error in ... }
        Napier.w("GoogleSignIn: iOS stub — add GIDSignIn CocoaPod", tag = "Auth")
        return null
    }

    actual fun signOut() {
        Napier.d("GoogleSignIn: iOS signOut stub", tag = "Auth")
    }
}

/**
 * iOS Apple Sign-In via ASAuthorizationController.
 *
 * Full integration requires AuthenticationServices framework.
 */
actual class AppleSignInManager {

    actual suspend fun signIn(): SocialSignInResult? {
        // TODO: integrate ASAuthorizationController
        //   val provider = ASAuthorizationAppleIDProvider()
        //   val request = provider.createRequest()
        //   request.requestedScopes = [.fullName, .email]
        //   val controller = ASAuthorizationController(authorizationRequests: [request])
        //   controller.delegate = delegate
        //   controller.performRequests()
        Napier.w("AppleSignIn: iOS stub — add ASAuthorizationController integration", tag = "Auth")
        return null
    }
}
