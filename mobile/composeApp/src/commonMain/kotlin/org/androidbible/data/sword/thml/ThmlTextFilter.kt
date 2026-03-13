package org.androidbible.data.sword.thml

object ThmlTextFilter {

    private val scripRefWithPassageRegex = Regex(
        "<scripRef\\s+passage=\"([^\"]*)\"[^>]*>(.*?)</scripRef>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val scripRefRegex = Regex(
        "<scripRef[^>]*>(.*?)</scripRef>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val strongsSyncRegex = Regex(
        "<sync\\s+type=\"Strongs\"\\s+value=\"(\\d+)\"\\s*/?>",
    )
    private val morphSyncRegex = Regex("<sync\\s+type=\"morph\"[^>]*/?>")
    private val otherSyncRegex = Regex("<sync[^>]*/?>")
    private val noteRegex = Regex("<note[^>]*>(.*?)</note>", RegexOption.DOT_MATCHES_ALL)
    private val addedRegex = Regex("<added>(.*?)</added>", RegexOption.DOT_MATCHES_ALL)
    private val fontColorRedRegex = Regex(
        "<font\\s+color=\"red\"\\s*>(.*?)</font>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val fontTagRegex = Regex("</?(font)[^>]*>", RegexOption.IGNORE_CASE)
    private val thmlTagRegex = Regex("</?(ThML|ThML\\.body)[^>]*>")
    private val htmlTagRegex = Regex("<[^>]+>")
    private val htmlEntityRegex = Regex("&(amp|lt|gt|quot|apos|nbsp);")

    fun stripMarkup(thmlText: String): String {
        if (thmlText.isBlank()) return ""
        if (!thmlText.contains('<') && !thmlText.contains('&')) return thmlText.trim()

        var text = thmlText
        text = scripRefWithPassageRegex.replace(text, "$2")
        text = scripRefRegex.replace(text, "$1")
        text = strongsSyncRegex.replace(text, "")
        text = morphSyncRegex.replace(text, "")
        text = otherSyncRegex.replace(text, "")
        text = noteRegex.replace(text, "")
        text = addedRegex.replace(text, "$1")
        text = fontColorRedRegex.replace(text, "$1")
        text = fontTagRegex.replace(text, "")
        text = thmlTagRegex.replace(text, "")
        text = htmlTagRegex.replace(text, "")
        text = decodeEntities(text)

        return text.replace(Regex("\\s+"), " ").trim()
    }

    fun extractWithStrongs(thmlText: String): String {
        if (thmlText.isBlank()) return ""
        if (!thmlText.contains('<') && !thmlText.contains('&')) return thmlText.trim()

        var text = thmlText
        text = scripRefWithPassageRegex.replace(text, "$2")
        text = scripRefRegex.replace(text, "$1")

        text = strongsSyncRegex.replace(text) { match ->
            " [${match.groupValues[1]}]"
        }

        text = morphSyncRegex.replace(text, "")
        text = otherSyncRegex.replace(text, "")
        text = noteRegex.replace(text, "")
        text = addedRegex.replace(text, "$1")
        text = fontColorRedRegex.replace(text, "$1")
        text = fontTagRegex.replace(text, "")
        text = thmlTagRegex.replace(text, "")
        text = htmlTagRegex.replace(text, "")
        text = decodeEntities(text)

        return text.replace(Regex("\\s+"), " ").trim()
    }

    fun extractStrongsNumbers(thmlText: String): List<String> {
        return strongsSyncRegex.findAll(thmlText).map { it.groupValues[1] }.toList()
    }

    private fun decodeEntities(text: String): String {
        return htmlEntityRegex.replace(text) { match ->
            when (match.groupValues[1]) {
                "amp" -> "&"
                "lt" -> "<"
                "gt" -> ">"
                "quot" -> "\""
                "apos" -> "'"
                "nbsp" -> " "
                else -> match.value
            }
        }
    }
}
