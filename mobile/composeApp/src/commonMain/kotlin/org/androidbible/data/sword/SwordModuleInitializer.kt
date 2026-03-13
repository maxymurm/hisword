package org.androidbible.data.sword

expect class SwordModuleInitializer {
    fun getModulesBasePath(): String
    suspend fun initializeModules(): Boolean
    fun isInitialized(): Boolean
    suspend fun installFromZip(zipBytes: ByteArray): Boolean
}
