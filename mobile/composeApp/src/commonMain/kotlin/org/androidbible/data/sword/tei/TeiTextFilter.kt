package org.androidbible.data.sword.tei

object TeiTextFilter {

    private val orthRegex = Regex("<orth[^>]*>(.*?)</orth>", RegexOption.DOT_MATCHES_ALL)
    private val formRegex = Regex("</?(form)[^>]*>", RegexOption.IGNORE_CASE)
    private val pronRegex = Regex("<pron[^>]*>(.*?)</pron>", RegexOption.DOT_MATCHES_ALL)
    private val gramPosRegex = Regex(
        "<gram\\s+type=\"pos\"[^>]*>(.*?)</gram>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val gramGrpRegex = Regex("</?(gramGrp)[^>]*>", RegexOption.IGNORE_CASE)
    private val gramRegex = Regex("</?(gram)[^>]*>", RegexOption.IGNORE_CASE)
    private val senseWithNRegex = Regex(
        "<sense\\s+n=\"([^\"]*)\"[^>]*>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val senseRegex = Regex("<sense[^>]*>")
    private val defRegex = Regex("<def[^>]*>(.*?)</def>", RegexOption.DOT_MATCHES_ALL)
    private val quoteRegex = Regex("<quote[^>]*>(.*?)</quote>", RegexOption.DOT_MATCHES_ALL)
    private val citRegex = Regex("</?(cit)[^>]*>", RegexOption.IGNORE_CASE)
    private val refWithOsisRegex = Regex(
        "<ref\\s+osisRef=\"([^\"]*)\"[^>]*>(.*?)</ref>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val refRegex = Regex("<ref[^>]*>(.*?)</ref>", RegexOption.DOT_MATCHES_ALL)
    private val xrRegex = Regex("<xr[^>]*>(.*?)</xr>", RegexOption.DOT_MATCHES_ALL)
    private val etymRegex = Regex("<etym[^>]*>(.*?)</etym>", RegexOption.DOT_MATCHES_ALL)
    private val noteRegex = Regex("<note[^>]*>(.*?)</note>", RegexOption.DOT_MATCHES_ALL)
    private val hiBoldRegex = Regex(
        "<hi\\s+rend=\"bold\"[^>]*>(.*?)</hi>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val hiItalicRegex = Regex(
        "<hi\\s+rend=\"italic\"[^>]*>(.*?)</hi>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val hiSupRegex = Regex(
        "<hi\\s+rend=\"sup\"[^>]*>(.*?)</hi>",
        RegexOption.DOT_MATCHES_ALL,
    )
    private val hiRegex = Regex("</?(hi)[^>]*>", RegexOption.IGNORE_CASE)
    private val entryRegex = Regex("</?entry[^>]*>")
    private val remainingTagRegex = Regex(
        "</?(teiHeader|body|text|div\\d?|ab|seg|bibl|biblScope|" +
            "persName|placeName|date|foreign|mentioned|soCalled|" +
            "term|gloss|list|item|p|sense|lb)[^>]*>",
        RegexOption.IGNORE_CASE,
    )
    private val htmlEntityRegex = Regex("&(amp|lt|gt|quot|apos|nbsp);")

    fun stripMarkup(teiText: String): String {
        if (teiText.isBlank()) return ""
        if (!teiText.contains('<') && !teiText.contains('&')) return teiText.trim()

        var text = teiText
        text = entryRegex.replace(text, "")
        text = orthRegex.replace(text, "$1")
        text = formRegex.replace(text, "")
        text = pronRegex.replace(text) { "[ ${it.groupValues[1]}] " }
        text = gramPosRegex.replace(text) { "${it.groupValues[1]} " }
        text = gramGrpRegex.replace(text, "")
        text = gramRegex.replace(text, "")
        text = senseWithNRegex.replace(text) { "${it.groupValues[1]}. " }
        text = senseRegex.replace(text, "")
        text = text.replace("</sense>", " ")
        text = defRegex.replace(text, "$1")
        text = quoteRegex.replace(text, "$1")
        text = citRegex.replace(text, "")
        text = refWithOsisRegex.replace(text, "$2")
        text = refRegex.replace(text, "$1")
        text = xrRegex.replace(text, "$1")
        text = etymRegex.replace(text) { "[Etym: ${it.groupValues[1]}] " }
        text = noteRegex.replace(text) { "(${it.groupValues[1]}) " }
        text = hiBoldRegex.replace(text, "$1")
        text = hiItalicRegex.replace(text, "$1")
        text = hiSupRegex.replace(text, "$1")
        text = hiRegex.replace(text, "")
        text = remainingTagRegex.replace(text, "")
        text = decodeEntities(text)

        return text.replace(Regex("\\s+"), " ").trim()
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
