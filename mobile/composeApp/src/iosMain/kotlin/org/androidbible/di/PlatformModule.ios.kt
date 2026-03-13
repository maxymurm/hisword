package org.androidbible.di

import app.cash.sqldelight.db.SqlDriver
import app.cash.sqldelight.driver.native.NativeSqliteDriver
import org.androidbible.data.local.AndroidBibleDatabase
import org.koin.dsl.module

actual val platformModule = module {
    single<SqlDriver> {
        NativeSqliteDriver(
            schema = AndroidBibleDatabase.Schema,
            name = "androidbible.db"
        )
    }
}
