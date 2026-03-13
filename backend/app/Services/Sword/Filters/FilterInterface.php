<?php

namespace App\Services\Sword\Filters;

/**
 * Interface for SWORD markup format filters.
 * Converts raw SWORD markup to HTML for browser rendering.
 */
interface FilterInterface
{
    /**
     * Convert raw markup text to HTML.
     *
     * @param string $text   Raw markup text from SWORD binary
     * @param array  $options  Rendering options (e.g., show strongs, footnotes, red letters)
     * @return string HTML output
     */
    public function toHtml(string $text, array $options = []): string;

    /**
     * Strip all markup and return plain text.
     */
    public function toPlainText(string $text): string;

    /**
     * Get the filter name.
     */
    public function getName(): string;
}
