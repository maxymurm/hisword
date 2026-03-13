package org.androidbible.data.bintex

/**
 * Platform-specific file access for loading module data files.
 * Implementations should return the raw bytes of the module file.
 */
expect class ModuleFileLoader {
    /**
     * Load module data bytes by module key.
     * Returns null if the module file is not found on this platform.
     */
    suspend fun loadModuleData(moduleKey: String): ByteArray?

    /**
     * Check if a module's data files exist on this platform.
     */
    suspend fun hasModuleFiles(moduleKey: String): Boolean

    /**
     * List all available module keys on the device.
     */
    suspend fun listAvailableModules(): List<String>
}
