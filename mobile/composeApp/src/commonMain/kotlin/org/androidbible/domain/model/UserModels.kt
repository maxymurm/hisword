package org.androidbible.domain.model

import kotlinx.serialization.Serializable

@Serializable
data class User(
    val id: Long = 0,
    val name: String,
    val email: String,
    val displayName: String? = null,
    val avatarUrl: String? = null,
    val createdAt: String? = null,
)

@Serializable
data class AuthToken(
    val token: String,
    val user: User,
)

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
    val deviceName: String = "mobile",
)

@Serializable
data class RegisterRequest(
    val name: String,
    val email: String,
    val password: String,
    val passwordConfirmation: String,
    val deviceName: String = "mobile",
)

@Serializable
data class UserPreference(
    val id: Long = 0,
    val userId: Long,
    val key: String,
    val value: String,
)
