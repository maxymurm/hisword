package org.androidbible.data.bintex

/**
 * Handles installing/uninstalling YES2/YES1 module files.
 * - Extracts module data from downloaded archives
 * - Registers modules in the local SQLDelight database
 * - Removes module data files on uninstall
 */
expect class LocalModuleInstaller {
    /**
     * Install a module from raw data bytes.
     * Writes the file to the platform modules directory and returns the installed module key.
     */
    suspend fun install(moduleKey: String, data: ByteArray): Boolean

    /**
     * Uninstall a module by deleting its data file.
     */
    suspend fun uninstall(moduleKey: String): Boolean
}
