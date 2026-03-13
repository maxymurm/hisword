package org.androidbible.data.sword.gbf

object GbfTextFilter {

    private val footnoteRegex = Regex("<RF>(.*?)<Rf>", RegexOption.DOT_MATCHES_ALL)
    private val italicRegex = Regex("<FI>(.*?)<Fi>", RegexOption.DOT_MATCHES_ALL)
    private val boldRegex = Regex("<FB>(.*?)<Fb>", RegexOption.DOT_MATCHES_ALL)
    private val redLetterRegex = Regex("<FR>(.*?)<Fr>", RegexOption.DOT_MATCHES_ALL)
    private val otQuoteRegex = Regex("<FO>(.*?)<Fo>", RegexOption.DOT_MATCHES_ALL)
    private val superscriptRegex = Regex("<FS>(.*?)<Fs>", RegexOption.DOT_MATCHES_ALL)
    private val underlineRegex = Regex("<FU>(.*?)<Fu>", RegexOption.DOT_MATCHES_ALL)
    private val titleRegex = Regex("<TS>(.*?)<Ts>", RegexOption.DOT_MATCHES_ALL)
    private val strongsRegex = Regex("<W([HG])(\\d+)>")
    private val morphRegex = Regex("<WT[^>]*>")
    private val crossRefRegex = Regex("<RX\\s*([^>]*)>", RegexOption.DOT_MATCHES_ALL)
    private val remainingTagRegex = Regex("<[A-Z][A-Za-z0-9]*>")

    fun stripMarkup(gbfText: String): String {
        if (gbfText.isBlank()) return ""
        if (!gbfText.contains('<')) return gbfText.trim()

        var text = gbfText
        text = footnoteRegex.replace(text, "")
        text = italicRegex.replace(text, "$1")
        text = boldRegex.replace(text, "$1")
        text = redLetterRegex.replace(text, "$1")
        text = otQuoteRegex.replace(text, "$1")
        text = superscriptRegex.replace(text, "$1")
        text = underlineRegex.replace(text, "$1")
        text = titleRegex.replace(text, "$1 ")
        text = text.replace("<FN>", "").replace("<Fn>", "")
        text = strongsRegex.replace(text, "")
        text = morphRegex.replace(text, "")
        text = crossRefRegex.replace(text, "")
        text = text.replace("<CM>", " ")
        text = text.replace("<CL>", " ")
        text = text.replace("<CI>", " ")
        text = remainingTagRegex.replace(text, "")

        return text.replace(Regex("\\s+"), " ").trim()
    }

    fun extractWithStrongs(gbfText: String): String {
        if (gbfText.isBlank()) return ""
        if (!gbfText.contains('<')) return gbfText.trim()

        var text = gbfText
        text = footnoteRegex.replace(text, "")
        text = italicRegex.replace(text, "$1")
        text = boldRegex.replace(text, "$1")
        text = redLetterRegex.replace(text, "$1")
        text = otQuoteRegex.replace(text, "$1")
        text = superscriptRegex.replace(text, "$1")
        text = underlineRegex.replace(text, "$1")
        text = titleRegex.replace(text, "$1 ")
        text = text.replace("<FN>", "").replace("<Fn>", "")

        text = strongsRegex.replace(text) { match ->
            val type = match.groupValues[1]
            val number = match.groupValues[2]
            " [$type$number]"
        }

        text = morphRegex.replace(text, "")
        text = crossRefRegex.replace(text, "")
        text = text.replace("<CM>", " ")
        text = text.replace("<CL>", " ")
        text = text.replace("<CI>", " ")
        text = remainingTagRegex.replace(text, "")

        return text.replace(Regex("\\s+"), " ").trim()
    }

    fun extractStrongsNumbers(gbfText: String): List<String> {
        return strongsRegex.findAll(gbfText).map { match ->
            "${match.groupValues[1]}${match.groupValues[2]}"
        }.toList()
    }
}
