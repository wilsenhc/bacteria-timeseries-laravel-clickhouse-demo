<?php

namespace App\Enums;

enum BacteriaObservationType: string
{
    case Level = 'level';
    case Count = 'count';

    public function label(): string
    {
        return match ($this) {
            self::Level => 'Level',
            self::Count => 'Count',
        };
    }
}
