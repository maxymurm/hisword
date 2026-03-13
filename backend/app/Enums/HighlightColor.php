<?php

namespace App\Enums;

enum HighlightColor: string
{
    case Yellow = 'yellow';
    case Green = 'green';
    case Blue = 'blue';
    case Pink = 'pink';
    case Purple = 'purple';
    case Orange = 'orange';
    case Red = 'red';
    case Teal = 'teal';

    public function hex(): string
    {
        return match ($this) {
            self::Yellow => '#FFEB3B',
            self::Green  => '#4CAF50',
            self::Blue   => '#2196F3',
            self::Pink   => '#E91E63',
            self::Purple => '#9C27B0',
            self::Orange => '#FF9800',
            self::Red    => '#F44336',
            self::Teal   => '#009688',
        };
    }
}
