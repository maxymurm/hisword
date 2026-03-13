package org.androidbible.data.sword.osis

object OsisTextFilter {

    fun stripMarkup(osisText: String): String {
        if (osisText.isBlank()) return ""
        if (!osisText.contains('<')) return osisText.trim()

        val result = StringBuilder(osisText.length)
        var i = 0
        var inTag = false
        var tagName = StringBuilder()
        var readingTagName = false
        var skipContent = false
        var skipTagDepth = 0

        while (i < osisText.length) {
            val c = osisText[i]

            if (c == '<') {
                inTag = true
                readingTagName = true
                tagName.clear()
                i++
                continue
            }

            if (inTag) {
                if (c == '>') {
                    inTag = false
                    readingTagName = false
                    val tag = tagName.toString().trim()
                    val tagLower = tag.lowercase()

                    if (tag.endsWith("/")) {
                        if (tagLower.startsWith("lb") || tagLower.startsWith("milestone")) {
                            if (result.isNotEmpty() && result.last() != '\n' && result.last() != ' ') {
                                result.append(' ')
                            }
                        }
                    } else if (tagLower.startsWith("note") && !tagLower.startsWith("/note")) {
                        skipContent = true
                        skipTagDepth = 1
                    } else if (tagLower.startsWith("/note") && skipContent) {
                        skipTagDepth--
                        if (skipTagDepth <= 0) {
                            skipContent = false
                            skipTagDepth = 0
                        }
                    } else if (tagLower.startsWith("p") || tagLower.startsWith("l ") ||
                        tagLower.startsWith("l>") || tagLower == "lg" ||
                        tagLower.startsWith("title") || tagLower == "div"
                    ) {
                        if (result.isNotEmpty() && result.last() != ' ' && result.last() != '\n') {
                            result.append(' ')
                        }
                    } else if (tagLower.startsWith("/p") || tagLower.startsWith("/l") ||
                        tagLower.startsWith("/title") || tagLower.startsWith("/div")
                    ) {
                        if (result.isNotEmpty() && result.last() != ' ' && result.last() != '\n') {
                            result.append(' ')
                        }
                    }
                } else {
                    if (readingTagName) {
                        tagName.append(c)
                    }
                }
                i++
                continue
            }

            if (!skipContent) {
                result.append(c)
            }
            i++
        }

        return result.toString()
            .replace(Regex("\\s+"), " ")
            .trim()
    }

    fun extractWithStrongs(osisText: String): String {
        if (osisText.isBlank()) return ""
        if (!osisText.contains('<')) return osisText.trim()

        val result = StringBuilder(osisText.length)
        var i = 0
        var inTag = false
        var tagContent = StringBuilder()
        var currentLemma: String? = null
        var skipNote = false
        var noteDepth = 0

        while (i < osisText.length) {
            val c = osisText[i]

            if (c == '<') {
                inTag = true
                tagContent.clear()
                i++
                continue
            }

            if (inTag) {
                if (c == '>') {
                    inTag = false
                    val tag = tagContent.toString()
                    val tagLower = tag.lowercase()

                    if (tagLower.startsWith("w ")) {
                        val lemmaMatch = Regex("""lemma="([^"]+)"""").find(tag)
                        currentLemma = lemmaMatch?.groupValues?.get(1)
                            ?.replace("strong:", "")
                            ?.replace("Strong:", "")
                    } else if (tagLower == "/w") {
                        if (currentLemma != null) {
                            result.append(" [$currentLemma]")
                            currentLemma = null
                        }
                    } else if (tagLower.startsWith("note") && !tagLower.startsWith("/")) {
                        skipNote = true
                        noteDepth = 1
                    } else if (tagLower.startsWith("/note")) {
                        noteDepth--
                        if (noteDepth <= 0) {
                            skipNote = false
                            noteDepth = 0
                        }
                    }
                } else {
                    tagContent.append(c)
                }
                i++
                continue
            }

            if (!skipNote) {
                result.append(c)
            }
            i++
        }

        return result.toString()
            .replace(Regex("\\s+"), " ")
            .trim()
    }

    fun extractStrongsNumbers(osisText: String): List<String> {
        if (!osisText.contains("lemma")) return emptyList()

        val results = mutableListOf<String>()
        val regex = Regex("""lemma="([^"]+)"""")
        for (match in regex.findAll(osisText)) {
            val lemma = match.groupValues[1]
            for (ref in lemma.split(" ")) {
                val cleaned = ref.replace("strong:", "").replace("Strong:", "").trim()
                if (cleaned.isNotEmpty()) {
                    results.add(cleaned)
                }
            }
        }
        return results
    }
}
