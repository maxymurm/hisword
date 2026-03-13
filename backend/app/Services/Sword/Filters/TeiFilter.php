<?php

namespace App\Services\Sword\Filters;

/**
 * Converts TEI (Text Encoding Initiative) markup to HTML.
 *
 * TEI is used primarily for dictionary/lexicon SWORD modules.
 *
 * Key tags:
 * - <entry> - Dictionary entry container
 * - <form><orth>word</orth></form> - The headword/lemma
 * - <def> - Definition
 * - <sense n="1"> - Numbered sense/meaning
 * - <cit><quote> - Citation/quotation
 * - <ref osisRef="..."> - Scripture reference
 * - <gramGrp><gram type="pos"> - Part of speech
 * - <etym> - Etymology
 * - <xr> - Cross-reference to another entry
 * - <note> - Notes
 */
class TeiFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'TEI';
    }

    public function toHtml(string $text, array $options = []): string
    {
        $html = $text;

        // Entry container
        $html = preg_replace('/<entry[^>]*>/', '<div class="dict-entry">', $html);
        $html = str_replace('</entry>', '</div>', $html);

        // Form / orthography (headword)
        $html = preg_replace('/<orth[^>]*>(.*?)<\/orth>/s', '<strong class="headword">$1</strong>', $html);
        $html = preg_replace('/<\/?form[^>]*>/', '', $html);

        // Pronunciation
        $html = preg_replace('/<pron[^>]*>(.*?)<\/pron>/s', '<span class="pronunciation">[$1]</span>', $html);

        // Part of speech
        $html = preg_replace(
            '/<gram\s+type="pos"[^>]*>(.*?)<\/gram>/s',
            '<span class="part-of-speech">$1</span>',
            $html
        );
        $html = preg_replace('/<\/?gramGrp[^>]*>/', '', $html);
        $html = preg_replace('/<\/?gram[^>]*>/', '', $html);

        // Senses with numbering
        $html = preg_replace_callback(
            '/<sense\s+n="([^"]*)"[^>]*>/s',
            fn ($m) => '<div class="dict-sense"><span class="sense-number">' . $m[1] . '.</span> ',
            $html
        );
        $html = preg_replace('/<sense[^>]*>/', '<div class="dict-sense">', $html);
        $html = str_replace('</sense>', '</div>', $html);

        // Definitions
        $html = preg_replace('/<def[^>]*>(.*?)<\/def>/s', '<span class="definition">$1</span>', $html);

        // Citations and quotes
        $html = preg_replace('/<quote[^>]*>(.*?)<\/quote>/s', '<q class="citation">$1</q>', $html);
        $html = preg_replace('/<\/?cit[^>]*>/', '', $html);

        // Scripture references
        $html = preg_replace(
            '/<ref\s+osisRef="([^"]*)"[^>]*>(.*?)<\/ref>/s',
            '<a class="scripture-ref" data-ref="$1">$2</a>',
            $html
        );
        $html = preg_replace(
            '/<ref[^>]*>(.*?)<\/ref>/s',
            '<a class="dict-ref">$1</a>',
            $html
        );

        // Cross-references
        $html = preg_replace(
            '/<xr[^>]*>(.*?)<\/xr>/s',
            '<span class="cross-ref">$1</span>',
            $html
        );

        // Etymology
        $html = preg_replace(
            '/<etym[^>]*>(.*?)<\/etym>/s',
            '<span class="etymology">[Etym: $1]</span>',
            $html
        );

        // Notes
        $html = preg_replace(
            '/<note[^>]*>(.*?)<\/note>/s',
            '<span class="dict-note">($1)</span>',
            $html
        );

        // Highlighting
        $html = preg_replace('/<hi\s+rend="bold"[^>]*>(.*?)<\/hi>/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/<hi\s+rend="italic"[^>]*>(.*?)<\/hi>/s', '<em>$1</em>', $html);
        $html = preg_replace('/<hi\s+rend="sup"[^>]*>(.*?)<\/hi>/s', '<sup>$1</sup>', $html);
        $html = preg_replace('/<\/?hi[^>]*>/', '', $html);

        // List structures
        $html = preg_replace('/<list[^>]*>/', '<ul class="dict-list">', $html);
        $html = str_replace('</list>', '</ul>', $html);
        $html = preg_replace('/<item[^>]*>/', '<li>', $html);
        $html = str_replace('</item>', '</li>', $html);

        // Paragraphs
        $html = preg_replace('/<p[^>]*>/', '<p>', $html);

        // Clean up remaining TEI tags
        $html = preg_replace('/<\/?(?:teiHeader|body|text|div\d?|ab|seg|bibl|biblScope|persName|placeName|date|foreign|mentioned|soCalled|term|gloss)[^>]*>/', '', $html);

        return trim($html);
    }

    public function toPlainText(string $text): string
    {
        $text = preg_replace('/<[^>]+>/', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
