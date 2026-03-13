package org.androidbible.data.bintex

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class Yes2TextDecoderTest {

    @Test
    fun plainTextNoMarkup() {
        val input = "In the beginning God created the heavens and the earth."
        assertEquals(input, Yes2TextDecoder.toPlainText(input))
    }

    @Test
    fun plainTextStripsItalicTags() {
        assertEquals("the word", Yes2TextDecoder.toPlainText("@9the word@7"))
    }

    @Test
    fun plainTextStripsRedLetterTags() {
        assertEquals("I am the way", Yes2TextDecoder.toPlainText("@0I am the way@5"))
    }

    @Test
    fun plainTextDoubleAtBecomesAt() {
        assertEquals("email@example.com", Yes2TextDecoder.toPlainText("email@@example.com"))
    }

    @Test
    fun plainTextLineBreak() {
        assertEquals("line1\nline2", Yes2TextDecoder.toPlainText("line1@8line2"))
    }

    @Test
    fun segmentsNormal() {
        val segments = Yes2TextDecoder.toSegments("Hello world")
        assertEquals(1, segments.size)
        assertEquals("Hello world", segments[0].text)
        assertEquals(TextStyle.NORMAL, segments[0].style)
    }

    @Test
    fun segmentsItalic() {
        val segments = Yes2TextDecoder.toSegments("before @9italic@7 after")
        assertEquals(3, segments.size)
        assertEquals("before ", segments[0].text)
        assertEquals(TextStyle.NORMAL, segments[0].style)
        assertEquals("italic", segments[1].text)
        assertEquals(TextStyle.ITALIC, segments[1].style)
        assertEquals(" after", segments[2].text)
        assertEquals(TextStyle.NORMAL, segments[2].style)
    }

    @Test
    fun segmentsRedLetter() {
        val segments = Yes2TextDecoder.toSegments("@0words of Jesus@5")
        assertEquals(1, segments.size)
        assertEquals("words of Jesus", segments[0].text)
        assertEquals(TextStyle.RED_LETTER, segments[0].style)
    }

    @Test
    fun segmentsEscapedAt() {
        val segments = Yes2TextDecoder.toSegments("at @@ sign")
        assertEquals(1, segments.size)
        assertEquals("at @ sign", segments[0].text)
    }

    @Test
    fun segmentsLineBreakInline() {
        val segments = Yes2TextDecoder.toSegments("line1@8line2")
        assertEquals(1, segments.size)
        assertTrue(segments[0].text.contains('\n'))
    }
}
