package org.androidbible.domain.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class SocialAuthRequest(
    val provider: String, // "google" or "apple"
    val token: String,
    @SerialName("device_name")
    val deviceName: String = "mobile",
)

@Serializable
data class ForgotPasswordRequest(
    val email: String,
)

@Serializable
data class ResetPasswordRequest(
    val email: String,
    val token: String,
    val password: String,
    @SerialName("password_confirmation")
    val passwordConfirmation: String,
)

@Serializable
data class ChangePasswordRequest(
    @SerialName("current_password")
    val currentPassword: String,
    val password: String,
    @SerialName("password_confirmation")
    val passwordConfirmation: String,
)

@Serializable
data class UpdateProfileRequest(
    val name: String? = null,
    @SerialName("display_name")
    val displayName: String? = null,
)

@Serializable
data class MessageResponse(
    val message: String,
)
