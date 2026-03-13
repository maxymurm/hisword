<?php

namespace App\Services\Sword\Versification;

/**
 * NRSV Versification.
 *
 * Nearly identical to KJV with minor verse count differences:
 * - 3 John: 15 verses (KJV: 14)
 * - Revelation 12: 18 verses (KJV: 17)
 * Psalm superscription differences are handled via mapping, not structure.
 */
class NrsvVersification extends AbstractVersification
{
    protected function otBooks(): array
    {
        return KjvVersification::OT_BOOKS;
    }

    protected function ntBooks(): array
    {
        return KjvVersification::NT_BOOKS;
    }

    protected function verseCounts(): array
    {
        $counts = KjvVersification::VERSE_COUNTS;

        // 3 John: 15 verses (KJV: 14)
        $counts['3John'] = [15];

        // Revelation 12: 18 verses (KJV: 17)
        $counts['Rev'][11] = 18; // index 11 = chapter 12

        return $counts;
    }

    protected function bookNames(): array
    {
        return KjvVersification::BOOK_NAMES;
    }
}
