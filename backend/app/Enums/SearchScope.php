<?php

namespace App\Enums;

enum SearchScope: string
{
    case All = 'all';
    case OldTestament = 'ot';
    case NewTestament = 'nt';
    case Book = 'book';
}
