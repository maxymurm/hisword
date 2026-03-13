package org.androidbible.data.remote

import io.github.aakira.napier.Napier
import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.plugins.*
import io.ktor.client.request.*
import io.ktor.client.statement.*
import io.ktor.http.*
import kotlinx.coroutines.delay

/**
 * High-level API client that wraps ApiService with:
 * - Automatic retry with exponential backoff (network errors only)
 * - Typed error mapping (ApiException hierarchy)
 * - Request/response logging
 */
class HisWordApiClient(
    val api: ApiService,
    private val tokenRefresher: TokenRefresher? = null,
) {
    /**
     * Execute an API call with retry logic.
     * Retries only on network/timeout errors, never on 4xx.
     */
    suspend fun <T> execute(
        maxRetries: Int = 2,
        block: suspend ApiService.() -> T,
    ): T {
        var lastException: Exception? = null
        repeat(maxRetries + 1) { attempt ->
            try {
                return api.block()
            } catch (e: ClientRequestException) {
                // 4xx errors: don't retry
                val status = e.response.status
                when (status) {
                    HttpStatusCode.Unauthorized -> {
                        if (attempt == 0 && tokenRefresher != null) {
                            val refreshed = tokenRefresher.refreshToken()
                            if (refreshed) {
                                // Retry once after token refresh
                                return api.block()
                            }
                        }
                        throw ApiException.Unauthorized(e.message ?: "Unauthorized")
                    }
                    HttpStatusCode.Forbidden -> throw ApiException.Forbidden(e.message ?: "Forbidden")
                    HttpStatusCode.NotFound -> throw ApiException.NotFound(e.message ?: "Not found")
                    HttpStatusCode.UnprocessableEntity -> {
                        val body = e.response.bodyAsText()
                        throw ApiException.ValidationError(body)
                    }
                    HttpStatusCode.TooManyRequests -> throw ApiException.RateLimited(e.message ?: "Rate limited")
                    else -> throw ApiException.ClientError(status.value, e.message ?: "Client error")
                }
            } catch (e: ServerResponseException) {
                // 5xx: retry
                lastException = e
                Napier.w("Server error (attempt ${attempt + 1}): ${e.message}", tag = "API")
            } catch (e: HttpRequestTimeoutException) {
                lastException = e
                Napier.w("Timeout (attempt ${attempt + 1})", tag = "API")
            } catch (e: Exception) {
                // Network errors: retry
                lastException = e
                Napier.w("Network error (attempt ${attempt + 1}): ${e.message}", tag = "API")
            }

            if (attempt < maxRetries) {
                val backoff = (1L shl attempt) * 1000L // 1s, 2s
                delay(backoff)
            }
        }
        throw ApiException.NetworkError(lastException?.message ?: "Request failed after retries", lastException)
    }
}

/**
 * Interface for refreshing expired auth tokens.
 */
fun interface TokenRefresher {
    suspend fun refreshToken(): Boolean
}

/**
 * Typed API exception hierarchy.
 */
sealed class ApiException(message: String, cause: Throwable? = null) : Exception(message, cause) {
    class Unauthorized(message: String) : ApiException(message)
    class Forbidden(message: String) : ApiException(message)
    class NotFound(message: String) : ApiException(message)
    class ValidationError(val body: String) : ApiException("Validation error: $body")
    class RateLimited(message: String) : ApiException(message)
    class ClientError(val statusCode: Int, message: String) : ApiException("HTTP $statusCode: $message")
    class ServerError(val statusCode: Int, message: String) : ApiException("HTTP $statusCode: $message")
    class NetworkError(message: String, cause: Throwable?) : ApiException(message, cause)
}
