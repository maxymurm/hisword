package org.androidbible.data.sword

data class SwordModuleConfig(
    val moduleName: String,
    val description: String = "",
    val modDrv: ModDrv = ModDrv.UNKNOWN,
    val dataPath: String = "",
    val compressType: CompressType = CompressType.NONE,
    val blockType: BlockType = BlockType.BOOK,
    val sourceType: SourceType = SourceType.PLAIN,
    val encoding: String = "UTF-8",
    val language: String = "en",
    val version: String = "",
    val about: String = "",
    val rawEntries: Map<String, String> = emptyMap(),
) {
    enum class ModDrv {
        Z_TEXT, RAW_TEXT, RAW_COM, RAW_COM4,
        RAW_LD, RAW_LD4, Z_LD,
        RAW_GEN_BOOK, UNKNOWN;

        companion object {
            fun from(s: String): ModDrv = when (s.lowercase().trim()) {
                "ztext" -> Z_TEXT
                "rawtext" -> RAW_TEXT
                "rawcom" -> RAW_COM
                "rawcom4" -> RAW_COM4
                "rawld" -> RAW_LD
                "rawld4" -> RAW_LD4
                "zld" -> Z_LD
                "rawgenbook" -> RAW_GEN_BOOK
                else -> UNKNOWN
            }
        }
    }

    enum class CompressType { NONE, ZIP, LZSS, BZIP2, XZ }

    enum class BlockType { BOOK, CHAPTER, VERSE }

    enum class SourceType { OSIS, THML, GBF, TEI, PLAIN }

    companion object {
        fun parse(confText: String): SwordModuleConfig {
            val lines = confText.lines()
            if (lines.isEmpty()) return SwordModuleConfig("unknown")

            val headerLine = lines.first().trim()
            val moduleName = if (headerLine.startsWith("[") && headerLine.endsWith("]")) {
                headerLine.substring(1, headerLine.length - 1)
            } else {
                "unknown"
            }

            val entries = mutableMapOf<String, String>()
            var currentKey: String? = null
            var currentValue = StringBuilder()

            for (i in 1 until lines.size) {
                val line = lines[i]
                if (line.isBlank() || line.trimStart().startsWith("#")) continue

                if (line.isNotEmpty() && (line[0] == ' ' || line[0] == '\t') && currentKey != null) {
                    currentValue.append("\n").append(line.trim())
                    continue
                }

                if (currentKey != null) {
                    entries[currentKey.lowercase()] = currentValue.toString()
                }

                val eqIdx = line.indexOf('=')
                if (eqIdx > 0) {
                    currentKey = line.substring(0, eqIdx).trim()
                    currentValue = StringBuilder(line.substring(eqIdx + 1).trim())
                } else {
                    currentKey = null
                }
            }

            if (currentKey != null) {
                entries[currentKey.lowercase()] = currentValue.toString()
            }

            return SwordModuleConfig(
                moduleName = moduleName,
                description = entries["description"] ?: "",
                modDrv = ModDrv.from(entries["moddrv"] ?: ""),
                dataPath = normalizeDataPath(entries["datapath"] ?: ""),
                compressType = when (entries["compresstype"]?.lowercase()) {
                    "zip" -> CompressType.ZIP
                    "lzss" -> CompressType.LZSS
                    "bzip2" -> CompressType.BZIP2
                    "xz" -> CompressType.XZ
                    else -> CompressType.NONE
                },
                blockType = when (entries["blocktype"]?.lowercase()) {
                    "book" -> BlockType.BOOK
                    "chapter" -> BlockType.CHAPTER
                    "verse" -> BlockType.VERSE
                    else -> BlockType.BOOK
                },
                sourceType = when (entries["sourcetype"]?.lowercase()) {
                    "osis" -> SourceType.OSIS
                    "thml" -> SourceType.THML
                    "gbf" -> SourceType.GBF
                    "tei" -> SourceType.TEI
                    else -> SourceType.PLAIN
                },
                encoding = entries["encoding"] ?: "UTF-8",
                language = entries["lang"] ?: "en",
                version = entries["version"] ?: "",
                about = entries["about"] ?: "",
                rawEntries = entries,
            )
        }

        private fun normalizeDataPath(path: String): String {
            var result = path.trim()
            if (result.startsWith("./")) result = result.substring(2)
            if (result.endsWith("/")) result = result.substring(0, result.length - 1)
            return result
        }
    }
}
