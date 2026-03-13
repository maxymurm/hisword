package org.androidbible.data.repository

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToList
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.model.Song
import org.androidbible.domain.model.SongBook
import org.androidbible.domain.repository.SongRepository

class SongRepositoryImpl(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
) : SongRepository {

    override fun getSongBooks(): Flow<List<SongBook>> {
        return db.syncQueries.getAllSongBooks().asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map {
                SongBook(
                    id = it.id,
                    title = it.title,
                    description = it.description,
                    isActive = it.is_active == 1L,
                )
            }
        }
    }

    override fun getSongs(bookId: Long): Flow<List<Song>> {
        return db.syncQueries.getSongsByBook(bookId).asFlow().mapToList(Dispatchers.IO).map { rows ->
            rows.map { it.toSong() }
        }
    }

    override suspend fun getSong(id: Long): Song? {
        return db.syncQueries.getSongById(id).executeAsOneOrNull()?.toSong()
    }

    override suspend fun searchSongs(query: String): List<Song> {
        return db.syncQueries.searchSongs(query, query).executeAsList().map { it.toSong() }
    }

    override suspend fun syncSongBooks() {
        val books = api.getSongBooks()
        db.transaction {
            books.forEach { b ->
                db.syncQueries.insertSongBook(
                    id = b.id,
                    title = b.title,
                    description = b.description,
                    is_active = if (b.isActive) 1L else 0L,
                )
            }
        }
        // Sync songs for each book
        books.forEach { b ->
            val songs = api.getSongs(b.id)
            db.transaction {
                songs.forEach { s ->
                    db.syncQueries.insertSong(
                        id = s.id,
                        song_book_id = s.songBookId,
                        number = s.number.toLong(),
                        title = s.title,
                        lyrics = s.lyrics,
                        author = s.author,
                        tune = s.tune,
                        key_signature = s.key,
                        created_at = s.createdAt,
                    )
                }
            }
        }
    }
}

private fun org.androidbible.data.local.Songs.toSong() = Song(
    id = id,
    songBookId = song_book_id,
    number = number.toInt(),
    title = title,
    lyrics = lyrics,
    author = author,
    tune = tune,
    key = key_signature,
    createdAt = created_at,
)
