<?php

namespace App\Enums;

enum GrowthLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
            self::None => 'None',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::None => 0,
        };
    }

    public function isPresent(): bool
    {
        return $this !== self::None;
    }
}
