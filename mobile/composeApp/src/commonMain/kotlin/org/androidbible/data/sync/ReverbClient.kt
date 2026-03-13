package org.androidbible.data.sync

import io.github.aakira.napier.Napier
import io.ktor.client.*
import io.ktor.client.plugins.websocket.*
import io.ktor.websocket.*
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*
import kotlinx.serialization.json.Json

/**
 * Laravel Reverb WebSocket client.
 * Handles: connection, subscription, ping/pong, reconnect with exponential backoff.
 */
class ReverbClient(
    private val client: HttpClient,
    private val wsUrl: String,
) {
    private val _connectionState = MutableStateFlow(ConnectionState.DISCONNECTED)
    val connectionState: StateFlow<ConnectionState> = _connectionState.asStateFlow()

    private val _incomingEvents = MutableSharedFlow<ReverbEvent>(extraBufferCapacity = 64)
    val incomingEvents: SharedFlow<ReverbEvent> = _incomingEvents.asSharedFlow()

    private var wsJob: Job? = null
    private var session: DefaultClientWebSocketSession? = null
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val json = Json { ignoreUnknownKeys = true }

    /**
     * Connect to Reverb and subscribe to channels.
     * Automatically reconnects with exponential backoff.
     */
    fun connect(channels: List<String> = listOf("private-user.sync")) {
        wsJob?.cancel()
        wsJob = scope.launch {
            var backoff = 1000L
            val maxBackoff = 30_000L

            while (isActive) {
                try {
                    _connectionState.value = ConnectionState.CONNECTING
                    client.webSocket(wsUrl) {
                        session = this
                        _connectionState.value = ConnectionState.CONNECTED
                        backoff = 1000L
                        Napier.i("Reverb WebSocket connected", tag = "Reverb")

                        // Subscribe to channels
                        channels.forEach { channel ->
                            val msg = """{"event":"pusher:subscribe","data":{"channel":"$channel"}}"""
                            send(Frame.Text(msg))
                        }

                        // Listen for incoming frames
                        for (frame in incoming) {
                            when (frame) {
                                is Frame.Text -> {
                                    val text = frame.readText()
                                    parseAndEmit(text)
                                }
                                is Frame.Ping -> send(Frame.Pong(frame.data))
                                else -> {}
                            }
                        }
                    }
                } catch (e: CancellationException) {
                    throw e
                } catch (e: Exception) {
                    Napier.e("Reverb disconnected: ${e.message}", tag = "Reverb")
                }

                _connectionState.value = ConnectionState.DISCONNECTED
                session = null
                delay(backoff)
                backoff = (backoff * 2).coerceAtMost(maxBackoff)
            }
        }
    }

    fun disconnect() {
        wsJob?.cancel()
        wsJob = null
        session = null
        _connectionState.value = ConnectionState.DISCONNECTED
    }

    suspend fun sendEvent(channel: String, event: String, data: String) {
        val msg = """{"event":"$event","channel":"$channel","data":$data}"""
        session?.send(Frame.Text(msg))
    }

    private suspend fun parseAndEmit(text: String) {
        when {
            text.contains("pusher:ping") -> {
                session?.send(Frame.Text("""{"event":"pusher:pong","data":{}}"""))
            }
            text.contains("sync.event") || text.contains("SyncEventCreated") -> {
                _incomingEvents.emit(ReverbEvent.SyncEventReceived(text))
            }
            text.contains("pusher:subscription_succeeded") -> {
                Napier.d("Channel subscription succeeded", tag = "Reverb")
            }
            else -> {
                _incomingEvents.emit(ReverbEvent.Unknown(text))
            }
        }
    }

    enum class ConnectionState { DISCONNECTED, CONNECTING, CONNECTED }

    sealed class ReverbEvent {
        data class SyncEventReceived(val raw: String) : ReverbEvent()
        data class Unknown(val raw: String) : ReverbEvent()
    }
}
