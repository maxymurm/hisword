package org.androidbible.data.remote

import io.github.aakira.napier.Napier
import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.plugins.*
import io.ktor.client.plugins.auth.*
import io.ktor.client.plugins.auth.providers.*
import io.ktor.client.plugins.contentnegotiation.*
import io.ktor.client.plugins.logging.*
import io.ktor.client.plugins.websocket.*
import io.ktor.client.request.*
import io.ktor.client.statement.*
import io.ktor.http.*
import io.ktor.serialization.kotlinx.json.*
import kotlinx.serialization.json.Json

/**
 * Factory to create the Ktor HttpClient with all required plugins.
 */
object HttpClientFactory {
    fun create(
        baseUrl: String,
        tokenProvider: () -> String?,
    ): HttpClient {
        return HttpClient {
            install(ContentNegotiation) {
                json(Json {
                    prettyPrint = false
                    isLenient = true
                    ignoreUnknownKeys = true
                    encodeDefaults = true
                    explicitNulls = false
                })
            }

            install(Auth) {
                bearer {
                    loadTokens {
                        val token = tokenProvider()
                        if (token != null) {
                            BearerTokens(token, "")
                        } else null
                    }
                }
            }

            install(Logging) {
                logger = object : Logger {
                    override fun log(message: String) {
                        Napier.d(message, tag = "Ktor")
                    }
                }
                level = LogLevel.HEADERS
            }

            install(WebSockets)

            install(HttpTimeout) {
                requestTimeoutMillis = 30_000
                connectTimeoutMillis = 15_000
                socketTimeoutMillis = 30_000
            }

            defaultRequest {
                url(baseUrl)
                contentType(ContentType.Application.Json)
                accept(ContentType.Application.Json)
            }
        }
    }
}
