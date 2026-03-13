<?php

namespace App\Services\Sword\Filters;

/**
 * Converts GBF (General Bible Format) to HTML.
 *
 * GBF is a legacy SWORD markup format using custom tag syntax.
 *
 * Key GBF tags:
 * - <RF>...<Rf> - Footnotes
 * - <FI>...<Fi> - Italics
 * - <FB>...<Fb> - Bold
 * - <FR>...<Fr> - Red letter
 * - <FO>...<Fo> - Old Testament quote
 * - <FS>...<Fs> - Superscript
 * - <FU>...<Fu> - Underline
 * - <FN>...<Fn> - Normal (end formatting)
 * - <WH####> - Hebrew Strong's number
 * - <WG####> - Greek Strong's number
 * - <WT...> - Morphology
 * - <RX ref> - Cross reference
 * - <CM> - Paragraph mark
 * - <CL> - Line break
 * - <CI> - Indent
 */
class GbfFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'GBF';
    }

    public function toHtml(string $text, array $options = []): string
    {
        $showStrongs = $options['strongs'] ?? false;
        $showFootnotes = $options['footnotes'] ?? true;

        $html = $text;

        // Footnotes: <RF>...<Rf>
        if ($showFootnotes) {
            $html = preg_replace_callback(
                '/<RF>(.*?)<Rf>/s',
                fn ($m) => '<sup class="footnote" title="' . htmlspecialchars($m[1]) . '">[*]</sup>',
                $html
            );
        } else {
            $html = preg_replace('/<RF>.*?<Rf>/s', '', $html);
        }

        // Formatting pairs
        $html = preg_replace('/<FI>(.*?)<Fi>/s', '<em>$1</em>', $html);
        $html = preg_replace('/<FB>(.*?)<Fb>/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/<FR>(.*?)<Fr>/s', '<span class="red-letter">$1</span>', $html);
        $html = preg_replace('/<FO>(.*?)<Fo>/s', '<span class="ot-quote">$1</span>', $html);
        $html = preg_replace('/<FS>(.*?)<Fs>/s', '<sup>$1</sup>', $html);
        $html = preg_replace('/<FU>(.*?)<Fu>/s', '<u>$1</u>', $html);
        $html = str_replace('<FN>', '', $html);
        $html = str_replace('<Fn>', '', $html);

        // Strong's numbers
        if ($showStrongs) {
            $html = preg_replace_callback(
                '/<W([HG])(\d+)>/',
                fn ($m) => '<sup class="strongs-number"><a class="strongs-link" data-strongs="' . $m[1] . $m[2] . '">' . $m[1] . $m[2] . '</a></sup>',
                $html
            );
        } else {
            $html = preg_replace('/<W[HG]\d+>/', '', $html);
        }

        // Morphology
        $html = preg_replace('/<WT[^>]*>/', '', $html);

        // Cross references
        $html = preg_replace(
            '/<RX\s*([^>]*)>/s',
            '<a class="cross-ref" data-ref="$1">[+]</a>',
            $html
        );

        // Paragraph, line break, indent
        $html = str_replace('<CM>', '<br class="paragraph-break" /><br />', $html);
        $html = str_replace('<CL>', '<br />', $html);
        $html = str_replace('<CI>', '&nbsp;&nbsp;', $html);

        // Title begin/end
        $html = preg_replace('/<TS>(.*?)<Ts>/s', '<h4 class="section-title">$1</h4>', $html);

        // Clean up any remaining GBF tags
        $html = preg_replace('/<[A-Z][A-Za-z0-9]*>/', '', $html);

        return trim($html);
    }

    public function toPlainText(string $text): string
    {
        // Remove all GBF tags
        $text = preg_replace('/<[^>]+>/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
