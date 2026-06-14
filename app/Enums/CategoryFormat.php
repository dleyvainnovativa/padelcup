<?php

namespace App\Enums;

enum CategoryFormat: string
{
    case RoundRobin = 'round_robin';
    case Elimination = 'elimination';
    case Hybrid = 'hybrid'; // groups -> knockout

    public function label(): string
    {
        return match ($this) {
            self::RoundRobin => 'Round-robin',
            self::Elimination => 'Eliminación',
            self::Hybrid => 'Grupos → eliminación',
        };
    }

    public function hasGroups(): bool
    {
        return $this === self::RoundRobin || $this === self::Hybrid;
    }

    public function hasBracket(): bool
    {
        return $this === self::Elimination || $this === self::Hybrid;
    }
}
