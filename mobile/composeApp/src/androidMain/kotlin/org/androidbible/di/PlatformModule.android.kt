package org.androidbible.di

import app.cash.sqldelight.db.SqlDriver
import app.cash.sqldelight.driver.android.AndroidSqliteDriver
import org.androidbible.data.local.AndroidBibleDatabase
import org.koin.android.ext.koin.androidContext
import org.koin.dsl.module

actual val platformModule = module {
    single<SqlDriver> {
        AndroidSqliteDriver(
            schema = AndroidBibleDatabase.Schema,
            context = androidContext(),
            name = "androidbible.db"
        )
    }
}
