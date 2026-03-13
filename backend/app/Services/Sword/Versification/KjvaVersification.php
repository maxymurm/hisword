<?php

namespace App\Services\Sword\Versification;

/**
 * KJVA Versification (KJV + Apocrypha).
 *
 * Same 39 OT canonical + 27 NT books as KJV,
 * with 14 apocryphal books appended to the OT.
 */
class KjvaVersification extends AbstractVersification
{
    public const APOCRYPHAL_BOOKS = [
        '1Esd', '2Esd', 'Tob', 'Jdt', 'AddEsth', 'Wis', 'Sir',
        'Bar', 'PrAzar', 'Sus', 'Bel', 'PrMan', '1Macc', '2Macc',
    ];

    private const APOCRYPHAL_VERSE_COUNTS = [
        '1Esd'    => [58,30,24,63,73,34,15,96,55],
        '2Esd'    => [40,48,36,52,56,59,70,63,47,59,46,51,58,48,63,78],
        'Tob'     => [22,14,17,21,22,17,18,21,6,12,19,22,18,15],
        'Jdt'     => [16,28,10,15,24,21,32,36,14,23,23,20,20,19,13,25],
        'AddEsth' => [1,1,1,1,1,1,1,1,1,13,12,6,18,19,16,24],
        'Wis'     => [16,24,19,20,23,25,30,21,18,21,26,27,19,31,19,29,21,25,22],
        'Sir'     => [30,18,31,31,15,37,36,19,18,31,34,18,26,27,20,30,32,33,30,32,28,27,28,34,26,29,30,26,28,25,31,24,31,26,20,26,31,34,35,30,24,25,33,22,26,20,25,25,16,29,30],
        'Bar'     => [22,35,37,37,9,73],
        'PrAzar'  => [68],
        'Sus'     => [64],
        'Bel'     => [42],
        'PrMan'   => [1],
        '1Macc'   => [64,70,60,61,68,63,50,32,73,89,74,53,53,49,41,24],
        '2Macc'   => [36,32,40,50,27,31,42,36,29,38,38,45,26,46,39],
    ];

    private const APOCRYPHAL_NAMES = [
        '1Esd'    => '1 Esdras',
        '2Esd'    => '2 Esdras',
        'Tob'     => 'Tobit',
        'Jdt'     => 'Judith',
        'AddEsth' => 'Additions to Esther',
        'Wis'     => 'Wisdom of Solomon',
        'Sir'     => 'Sirach',
        'Bar'     => 'Baruch',
        'PrAzar'  => 'Prayer of Azariah',
        'Sus'     => 'Susanna',
        'Bel'     => 'Bel and the Dragon',
        'PrMan'   => 'Prayer of Manasseh',
        '1Macc'   => '1 Maccabees',
        '2Macc'   => '2 Maccabees',
    ];

    protected function otBooks(): array
    {
        return array_merge(KjvVersification::OT_BOOKS, self::APOCRYPHAL_BOOKS);
    }

    protected function ntBooks(): array
    {
        return KjvVersification::NT_BOOKS;
    }

    protected function verseCounts(): array
    {
        return array_merge(KjvVersification::VERSE_COUNTS, self::APOCRYPHAL_VERSE_COUNTS);
    }

    protected function bookNames(): array
    {
        return array_merge(KjvVersification::BOOK_NAMES, self::APOCRYPHAL_NAMES);
    }
}
