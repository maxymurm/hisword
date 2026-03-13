<?php

namespace App\Services\Sword\Filters;

/**
 * Converts OSIS (Open Scripture Information Standard) XML to HTML.
 *
 * OSIS is the most common markup format for modern SWORD modules (KJV, ESV, etc.).
 *
 * Key OSIS tags handled:
 * - <w lemma="strong:H1234" morph="..."> - Words with Strong's/morphology
 * - <note type="x-footnote"> - Footnotes
 * - <note type="crossReference"> - Cross-references
 * - <title>, <title type="psalm"> - Headings
 * - <divineName> - LORD in small caps
 * - <transChange type="added"> - Translator-added words (italics)
 * - <q who="Jesus"> - Red letter words
 * - <milestone type="x-p" /> - Paragraph breaks
 * - <lb /> - Line breaks
 * - <lg>, <l> - Poetry/line groups
 */
class OsisFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'OSIS';
    }

    public function toHtml(string $text, array $options = []): string
    {
        $showStrongs = $options['strongs'] ?? false;
        $showMorph = $options['morph'] ?? false;
        $showFootnotes = $options['footnotes'] ?? true;
        $showHeadings = $options['headings'] ?? true;
        $showRedLetters = $options['redLetters'] ?? true;
        $showCrossRefs = $options['crossRefs'] ?? true;

        // Pre-process: ensure valid XML by wrapping in root
        $text = $this->preProcess($text);

        // Process OSIS tags to HTML
        $html = $text;

        // Divine name: <divineName>LORD</divineName> → small caps
        $html = preg_replace(
            '/<divineName>(.*?)<\/divineName>/s',
            '<span class="divine-name">$1</span>',
            $html
        );

        // Red letters: <q who="Jesus"> (who= may appear anywhere in attributes)
        if ($showRedLetters) {
            $html = preg_replace(
                '/<q\s+[^>]*who="Jesus"[^>]*>(.*?)<\/q>/s',
                '<span class="red-letter">$1</span>',
                $html
            );
            // Self-closing q markers
            $html = preg_replace('/<q\s+[^>]*who="Jesus"[^>]*sID="[^"]*"[^>]*\/>/', '<span class="red-letter">', $html);
            $html = preg_replace('/<q\s+[^>]*who="Jesus"[^>]*eID="[^"]*"[^>]*\/>/', '</span>', $html);
        }
        // Remove other q tags
        $html = preg_replace('/<\/?q[^>]*>/', '', $html);

        // TransChange (added words): italics
        $html = preg_replace(
            '/<transChange\s+type="added"\s*>(.*?)<\/transChange>/s',
            '<em class="added-word">$1</em>',
            $html
        );
        $html = preg_replace('/<\/?transChange[^>]*>/', '', $html);

        // Remove self-closing <w /> tags (empty article references with no English text)
        $html = preg_replace('/<w\s+[^>]*\/>/s', '', $html);

        // Words with Strong's numbers
        $html = preg_replace_callback(
            '/<w\s+([^>]*)>(.*?)<\/w>/s',
            function ($m) use ($showStrongs, $showMorph) {
                return $this->processWord($m[1], $m[2], $showStrongs, $showMorph);
            },
            $html
        );

        // Titles/headings
        if ($showHeadings) {
            $html = preg_replace(
                '/<title\s+type="psalm"\s*>(.*?)<\/title>/s',
                '<h4 class="psalm-title">$1</h4>',
                $html
            );
            $html = preg_replace(
                '/<title\s+canonical="true"\s*>(.*?)<\/title>/s',
                '<h4 class="canonical-title">$1</h4>',
                $html
            );
            $html = preg_replace(
                '/<title[^>]*>(.*?)<\/title>/s',
                '<h4 class="section-title">$1</h4>',
                $html
            );
        } else {
            $html = preg_replace('/<title[^>]*>.*?<\/title>/s', '', $html);
        }

        // Footnotes
        if ($showFootnotes) {
            $html = preg_replace_callback(
                '/<note\s+type="x-footnote"[^>]*>(.*?)<\/note>/s',
                fn ($m) => '<sup class="footnote" title="' . htmlspecialchars(strip_tags($m[1])) . '">[*]</sup>',
                $html
            );
        }

        // Cross references
        if ($showCrossRefs) {
            $html = preg_replace_callback(
                '/<note\s+type="crossReference"[^>]*>(.*?)<\/note>/s',
                fn ($m) => '<sup class="cross-ref" title="' . htmlspecialchars(strip_tags($m[1])) . '">[+]</sup>',
                $html
            );
        }

        // Remove remaining notes
        $html = preg_replace('/<note[^>]*>.*?<\/note>/s', '', $html);

        // Scripture references
        $html = preg_replace(
            '/<reference\s+osisRef="([^"]*)"[^>]*>(.*?)<\/reference>/s',
            '<a class="scripture-ref" data-ref="$1">$2</a>',
            $html
        );

        // Milestones (paragraph markers)
        $html = preg_replace('/<milestone\s+type="x-p"\s*\/>/', '<br class="paragraph-break" />', $html);
        $html = preg_replace('/<milestone[^>]*\/>/', '', $html);

        // Line breaks
        $html = preg_replace('/<lb\s*\/>/', '<br />', $html);

        // Verse/chapter/div markers (structural OSIS tags, strip before poetry conversion)
        $html = preg_replace('/<\/?verse[^>]*>/', '', $html);
        $html = preg_replace('/<\/?chapter[^>]*>/', '', $html);
        $html = preg_replace('/<\/?div[^>]*>/', '', $html);

        // Poetry: line groups and lines
        $html = preg_replace('/<lg[^>]*>/', '<div class="poetry">', $html);
        $html = str_replace('</lg>', '</div>', $html);
        $html = preg_replace('/<l\s+level="(\d+)"[^>]*>/', '<div class="poetry-line indent-$1">', $html);
        $html = preg_replace('/<l[^>]*>/', '<div class="poetry-line">', $html);
        $html = str_replace('</l>', '</div>', $html);

        // Selah
        $html = preg_replace(
            '/<selah>(.*?)<\/selah>/s',
            '<span class="selah">$1</span>',
            $html
        );

        // Foreign words
        $html = preg_replace(
            '/<foreign[^>]*>(.*?)<\/foreign>/s',
            '<em class="foreign">$1</em>',
            $html
        );

        // Clean up remaining XML tags
        $html = preg_replace('/<\/?(?:seg|rdg|reading|catchWord|hi)[^>]*>/', '', $html);
        $html = preg_replace('/<(?:xml|!)[^>]*>/', '', $html);

        return trim($html);
    }

    public function toPlainText(string $text): string
    {
        // Remove all XML tags
        $text = preg_replace('/<[^>]+>/', '', $text);
        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Extract structured Strong's/morphology data from OSIS XML.
     *
     * @return list<array{word: string, strongs: string[], morph: string|null}>
     */
    public function extractStrongs(string $text): array
    {
        $results = [];

        preg_match_all('/<w\s+([^>]*)>(.*?)<\/w>/s', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $attrs = $m[1];
            $word = strip_tags($m[2]);

            $strongs = [];
            if (preg_match('/lemma="([^"]*)"/', $attrs, $lm)) {
                preg_match_all('/strong:([HG]\d+)/', $lm[1], $sm);
                $strongs = $sm[1] ?? [];
            }

            if (empty($strongs)) {
                continue;
            }

            $morph = null;
            if (preg_match('/morph="([^"]*)"/', $attrs, $mm)) {
                $morph = $mm[1];
            }

            $results[] = [
                'word' => trim($word),
                'strongs' => $strongs,
                'morph' => $morph,
            ];
        }

        return $results;
    }

    /**
     * Process a <w> (word) tag with Strong's numbers and morphology.
     */
    private function processWord(string $attrs, string $content, bool $showStrongs, bool $showMorph): string
    {
        $classes = ['sword-word'];
        $dataAttrs = '';

        // Extract Strong's lemma
        if (preg_match('/lemma="([^"]*)"/', $attrs, $m)) {
            $lemma = $m[1];
            // Parse multiple Strong's: "strong:H1234 strong:H5678"
            $strongs = [];
            preg_match_all('/strong:([HG]\d+)/', $lemma, $sm);
            if (!empty($sm[1])) {
                $strongs = $sm[1];
                $dataAttrs .= ' data-strongs="' . implode(',', $strongs) . '"';
                $classes[] = 'has-strongs';
            }
        }

        // Extract morphology
        if (preg_match('/morph="([^"]*)"/', $attrs, $m)) {
            $morph = $m[1];
            $dataAttrs .= ' data-morph="' . htmlspecialchars($morph) . '"';
            if ($showMorph) {
                $classes[] = 'has-morph';
            }
        }

        $classStr = implode(' ', $classes);
        $html = '<span class="' . $classStr . '"' . $dataAttrs . '>' . $content;

        // Append Strong's indicators
        if ($showStrongs && !empty($strongs)) {
            $html .= '<sup class="strongs-number">';
            foreach ($strongs as $s) {
                $html .= '<a class="strongs-link" data-strongs="' . $s . '">' . $s . '</a>';
            }
            $html .= '</sup>';
        }

        $html .= '</span>';

        return $html;
    }

    /**
     * Pre-process text: clean up common issues.
     */
    private function preProcess(string $text): string
    {
        // Remove XML declarations
        $text = preg_replace('/<\?xml[^>]*\?>/', '', $text);
        // Remove OSIS document-level wrappers if present
        $text = preg_replace('/<\/?osisText[^>]*>/', '', $text);
        $text = preg_replace('/<\/?osis[^>]*>/', '', $text);

        return trim($text);
    }
}
