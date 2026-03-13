<?php

namespace App\Services\Sword\Filters;

/**
 * Plain text filter - pass-through with minimal processing.
 * Used for modules with SourceType=Plain or unknown source types.
 */
class PlainFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'Plain';
    }

    public function toHtml(string $text, array $options = []): string
    {
        // Escape HTML entities and convert newlines
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $html = nl2br($html);
        return $html;
    }

    public function toPlainText(string $text): string
    {
        return trim($text);
    }
}
