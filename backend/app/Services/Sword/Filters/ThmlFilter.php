<?php

namespace App\Services\Sword\Filters;

/**
 * Converts ThML (Theological Markup Language) to HTML.
 *
 * ThML is used by older SWORD modules. It's similar to HTML with
 * special tags for scripture references, notes, and Strong's numbers.
 *
 * Key tags:
 * - <scripRef passage="Gen.1.1">Gen 1:1</scripRef>
 * - <note>footnote text</note>
 * - <sync type="Strongs" value="H1234" />
 * - <sync type="morph" value="..." />
 * - <font color="red"> - red letter
 * - Standard HTML elements (p, br, i, b, div, etc.)
 */
class ThmlFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'ThML';
    }

    public function toHtml(string $text, array $options = []): string
    {
        $showStrongs = $options['strongs'] ?? false;
        $showFootnotes = $options['footnotes'] ?? true;

        $html = $text;

        // Scripture references
        $html = preg_replace(
            '/<scripRef\s+passage="([^"]*)"[^>]*>(.*?)<\/scripRef>/s',
            '<a class="scripture-ref" data-ref="$1">$2</a>',
            $html
        );
        $html = preg_replace(
            '/<scripRef[^>]*>(.*?)<\/scripRef>/s',
            '<a class="scripture-ref">$1</a>',
            $html
        );

        // Strong's sync markers
        if ($showStrongs) {
            $html = preg_replace_callback(
                '/<sync\s+type="Strongs"\s+value="(\d+)"\s*\/?>/',
                fn ($m) => '<sup class="strongs-number"><a class="strongs-link" data-strongs="' . $m[1] . '">' . $m[1] . '</a></sup>',
                $html
            );
        } else {
            $html = preg_replace('/<sync\s+type="Strongs"[^>]*\/?>/', '', $html);
        }

        // Morphology sync markers
        $html = preg_replace('/<sync\s+type="morph"[^>]*\/?>/', '', $html);
        // Other sync markers
        $html = preg_replace('/<sync[^>]*\/?>/', '', $html);

        // Notes
        if ($showFootnotes) {
            $html = preg_replace_callback(
                '/<note[^>]*>(.*?)<\/note>/s',
                fn ($m) => '<sup class="footnote" title="' . htmlspecialchars(strip_tags($m[1])) . '">[*]</sup>',
                $html
            );
        } else {
            $html = preg_replace('/<note[^>]*>.*?<\/note>/s', '', $html);
        }

        // Added (italicized) words
        $html = preg_replace(
            '/<added>(.*?)<\/added>/s',
            '<em class="added-word">$1</em>',
            $html
        );

        // Divine name
        $html = preg_replace(
            '/<span\s+class="divineName"[^>]*>(.*?)<\/span>/s',
            '<span class="divine-name">$1</span>',
            $html
        );

        // Red letter (ThML uses font color)
        $html = preg_replace(
            '/<font\s+color="red"\s*>(.*?)<\/font>/s',
            '<span class="red-letter">$1</span>',
            $html
        );

        // Remove font tags
        $html = preg_replace('/<\/?font[^>]*>/', '', $html);

        // Clean up ThML-specific tags
        $html = preg_replace('/<\/?ThML[^>]*>/', '', $html);
        $html = preg_replace('/<\/?ThML\.body[^>]*>/', '', $html);

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
