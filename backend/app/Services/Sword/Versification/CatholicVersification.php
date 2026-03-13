<?php

namespace App\Services\Sword\Versification;

/**
 * Catholic Versification.
 *
 * 46 OT books (includes deuterocanonical: Tobit, Judith, 1-2 Maccabees,
 * Wisdom, Sirach, Baruch). Joel = 4 chapters, Malachi = 3 chapters,
 * Daniel = 14 chapters, Baruch = 6 chapters (ch 6 = Epistle of Jeremiah).
 * NT identical to KJV.
 */
class CatholicVersification extends AbstractVersification
{
    public const OT_BOOKS = [
        'Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth',
        '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh',
        'Tob', 'Jdt', 'Esth',
        '1Macc', '2Macc',
        'Job', 'Ps', 'Prov', 'Eccl', 'Song',
        'Wis', 'Sir',
        'Isa', 'Jer', 'Lam',
        'Bar',
        'Ezek',
        'Dan',
        'Hos', 'Joel', 'Amos', 'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag',
        'Zech', 'Mal',
    ];

    private const EXTRA_VERSE_COUNTS = [
        'Tob'   => [22,14,17,21,23,19,17,21,6,14,19,22,18,15],
        'Jdt'   => [16,28,10,15,24,21,32,36,14,23,23,20,20,19,14,25],
        '1Macc' => [64,70,60,61,68,63,50,32,73,89,74,54,53,49,41,24],
        '2Macc' => [36,32,40,50,27,31,42,36,29,38,38,46,26,46,39],
        'Wis'   => [16,24,19,20,23,25,30,21,19,21,26,27,19,31,19,29,21,25,22],
        'Sir'   => [30,18,31,31,17,37,36,19,18,31,34,18,26,27,20,30,32,33,30,32,28,27,28,34,26,29,30,26,28,25,31,24,33,31,26,31,31,34,35,30,27,25,35,23,26,20,25,25,16,29,30],
        'Bar'   => [22,35,38,37,9,72],
        'Dan'   => [21,49,100,34,30,29,28,27,27,21,45,13,64,43],
        'Joel'  => [20,27,5,21],
        'Mal'   => [14,17,24],
        'Hos'   => [9,25,5,19,15,11,16,14,17,15,11,15,15,10],
        'Ps'    => [6,12,9,9,13,11,18,10,21,18,7,9,6,7,5,11,15,51,15,10,14,32,6,10,22,12,14,9,11,13,25,11,22,23,28,13,40,23,14,18,14,12,5,27,18,12,10,15,21,24,21,11,7,9,24,14,12,12,18,14,9,13,12,11,14,20,8,36,37,6,24,20,28,23,11,13,21,72,13,20,17,8,19,13,14,17,7,19,53,17,16,16,5,23,11,13,12,9,9,5,9,29,22,35,45,48,43,14,31,7,10,10,9,8,18,19,2,29,176,7,8,9,4,8,5,6,5,6,8,8,3,18,3,3,21,26,9,8,24,14,10,8,12,15,21,10,20,14,9,6],
    ];

    private const EXTRA_NAMES = [
        'Tob'   => 'Tobit',
        'Jdt'   => 'Judith',
        '1Macc' => '1 Maccabees',
        '2Macc' => '2 Maccabees',
        'Wis'   => 'Wisdom of Solomon',
        'Sir'   => 'Sirach',
        'Bar'   => 'Baruch',
    ];

    protected function otBooks(): array
    {
        return self::OT_BOOKS;
    }

    protected function ntBooks(): array
    {
        return KjvVersification::NT_BOOKS;
    }

    protected function verseCounts(): array
    {
        return array_merge(KjvVersification::VERSE_COUNTS, self::EXTRA_VERSE_COUNTS);
    }

    protected function bookNames(): array
    {
        return array_merge(KjvVersification::BOOK_NAMES, self::EXTRA_NAMES);
    }
}
