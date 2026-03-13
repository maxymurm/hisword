<?php

namespace App\Enums;

enum ModuleType: string
{
    case Bible = 'bible';
    case Commentary = 'commentary';
    case Dictionary = 'dictionary';
    case Devotional = 'devotional';
    case GenBook = 'genbook';
}
