<?php

namespace App\Services\Sword\Versification;

/**
 * German (Luther) Versification.
 *
 * Same 66 books as KJV but with key structural differences:
 * - Joel: 4 chapters (not 3)
 * - Malachi: 3 chapters (not 4)
 * - Psalms: superscriptions counted as verse 1
 */
class GermanVersification extends AbstractVersification
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

        // Joel: 4 chapters (KJV has 3)
        $counts['Joel'] = [20, 27, 5, 21];

        // Malachi: 3 chapters (KJV has 4)
        $counts['Mal'] = [14, 17, 24];

        // Psalms with superscriptions as verse 1
        $counts['Ps'] = [6,12,9,9,13,11,18,10,21,18,7,9,6,7,5,11,15,51,15,10,14,32,6,10,22,12,14,9,11,13,25,11,22,23,28,13,40,23,14,18,14,12,5,27,18,12,10,15,21,23,21,11,7,9,24,14,12,12,18,14,9,13,12,11,14,20,8,36,37,6,24,20,28,23,11,13,21,72,13,20,17,8,19,13,14,17,7,19,53,17,16,16,5,23,11,13,12,9,9,5,8,29,22,35,45,48,43,14,31,7,10,10,9,8,18,19,2,29,176,7,8,9,4,8,5,6,5,6,8,8,3,18,3,3,21,26,9,8,24,14,10,8,12,15,21,10,20,14,9,6];

        return $counts;
    }

    protected function bookNames(): array
    {
        return KjvVersification::BOOK_NAMES;
    }
}
