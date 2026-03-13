<?php

namespace App\Services\Sword\Versification;

/**
 * Synodal (Russian Orthodox) Versification.
 *
 * 51 OT books (adds PrMan, 1Esd, Tob, Jdt, Wis, Sir, EpJer, Bar,
 * 1-3 Macc, 2Esd, Dan=14ch, Ps=151). NT books in different order
 * (Catholic epistles before Pauline).
 */
class SynodalVersification extends AbstractVersification
{
    public const OT_BOOKS = [
        'Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth',
        '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr',
        'PrMan',
        'Ezra', 'Neh',
        '1Esd',
        'Tob', 'Jdt',
        'Esth', 'Job', 'Ps', 'Prov', 'Eccl', 'Song',
        'Wis', 'Sir',
        'Isa', 'Jer', 'Lam',
        'EpJer', 'Bar',
        'Ezek', 'Dan',
        'Hos', 'Joel', 'Amos', 'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal',
        '1Macc', '2Macc', '3Macc', '2Esd',
    ];

    public const NT_BOOKS = [
        'Matt', 'Mark', 'Luke', 'John', 'Acts',
        'Jas', '1Pet', '2Pet', '1John', '2John', '3John', 'Jude',
        'Rom', '1Cor', '2Cor', 'Gal', 'Eph', 'Phil', 'Col',
        '1Thess', '2Thess', '1Tim', '2Tim', 'Titus', 'Phlm', 'Heb', 'Rev',
    ];

    private const EXTRA_VERSE_COUNTS = [
        'PrMan' => [12],
        '1Esd'  => [58,31,24,63,70,34,15,92,55],
        'Tob'   => [22,14,17,21,22,18,17,21,6,13,18,22,18,15],
        'Jdt'   => [16,28,10,15,24,21,32,36,14,23,23,20,20,19,14,25],
        'Wis'   => [16,24,19,20,24,27,30,21,19,21,27,28,19,31,19,29,20,25,21],
        'Sir'   => [30,18,31,35,18,37,39,22,23,34,34,18,32,27,20,31,31,33,28,31,31,31,37,37,29,27,33,30,31,27,37,25,33,26,23,29,34,39,42,32,29,26,36,27,31,23,31,28,18,31,38],
        'EpJer' => [72],
        'Bar'   => [22,35,38,37,9],
        'Dan'   => [21,49,100,34,31,28,28,27,27,21,45,13,64,42],
        'Ps'    => [6,12,9,9,13,11,18,10,39,7,9,6,7,5,11,15,51,15,10,14,32,6,10,22,12,14,9,11,13,25,11,22,23,28,13,40,23,14,18,14,12,5,27,18,12,10,15,21,23,21,11,7,9,24,14,12,12,18,14,9,13,12,11,14,20,8,36,37,6,24,20,28,23,11,13,21,72,13,20,17,8,19,13,14,17,7,19,53,17,16,16,5,23,11,13,12,9,9,5,8,29,22,35,45,48,43,14,31,7,10,10,9,26,9,10,2,29,176,7,8,9,4,8,5,6,5,6,8,8,3,18,3,3,21,26,9,8,24,14,10,7,12,15,21,10,11,9,14,9,6,7],
        '1Macc' => [64,70,60,61,68,63,50,32,73,89,74,53,53,49,41,24],
        '2Macc' => [36,33,40,50,27,31,42,36,29,38,38,45,26,46,39],
        '3Macc' => [25,24,22,16,36,37,20],
        '2Esd'  => [40,48,36,52,56,59,70,63,47,60,46,51,58,48,63,78],
    ];

    private const EXTRA_NAMES = [
        'PrMan' => 'Prayer of Manasseh',
        '1Esd'  => '1 Esdras',
        'Tob'   => 'Tobit',
        'Jdt'   => 'Judith',
        'Wis'   => 'Wisdom of Solomon',
        'Sir'   => 'Sirach',
        'EpJer' => 'Epistle of Jeremiah',
        'Bar'   => 'Baruch',
        '1Macc' => '1 Maccabees',
        '2Macc' => '2 Maccabees',
        '3Macc' => '3 Maccabees',
        '2Esd'  => '2 Esdras',
    ];

    protected function otBooks(): array
    {
        return self::OT_BOOKS;
    }

    protected function ntBooks(): array
    {
        return self::NT_BOOKS;
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
