package org.androidbible.data.repository

import app.cash.sqldelight.coroutines.asFlow
import app.cash.sqldelight.coroutines.mapToOneOrNull
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.IO
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.datetime.Clock
import org.androidbible.data.local.AndroidBibleDatabase
import org.androidbible.data.remote.ApiService
import org.androidbible.domain.repository.UserPreferenceRepository

class UserPreferenceRepositoryImpl(
    private val db: AndroidBibleDatabase,
    private val api: ApiService,
) : UserPreferenceRepository {

    override suspend fun get(key: String): String? {
        return db.syncQueries.getPreference(key).executeAsOneOrNull()?.value_
    }

    override suspend fun set(key: String, value: String) {
        val now = Clock.System.now().toString()
        db.syncQueries.upsertPreference(
            key = key,
            value_ = value,
            is_synced = 0,
            updated_at = now,
        )
        // Attempt API sync
        try {
            api.setPreference(key, value)
            db.syncQueries.markPreferenceSynced(key)
        } catch (_: Exception) {
            // Will sync later
        }
    }

    override suspend fun remove(key: String) {
        db.syncQueries.deletePreference(key)
        try {
            api.deletePreference(key)
        } catch (_: Exception) { }
    }

    override fun observe(key: String): Flow<String?> {
        return db.syncQueries.getPreference(key).asFlow().mapToOneOrNull(Dispatchers.IO).map { it?.value_ }
    }

    override suspend fun syncPreferences() {
        // Push unsynced
        val unsynced = db.syncQueries.getUnsyncedPreferences().executeAsList()
        unsynced.forEach { pref ->
            try {
                api.setPreference(pref.key, pref.value_)
                db.syncQueries.markPreferenceSynced(pref.key)
            } catch (_: Exception) { }
        }
        // Pull from server
        try {
            val serverPrefs = api.getPreferences()
            val now = Clock.System.now().toString()
            db.transaction {
                serverPrefs.forEach { p ->
                    db.syncQueries.upsertPreference(
                        key = p.key,
                        value_ = p.value,
                        is_synced = 1,
                        updated_at = now,
                    )
                }
            }
        } catch (_: Exception) { }
    }
}
