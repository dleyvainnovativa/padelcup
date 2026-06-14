<?php

namespace App\Enums;

enum GroupFormat: string
{
    case RoundRobin = 'round_robin';
    case Mexicano = 'mexicano';

    public function label(): string
    {
        return match ($this) {
            self::RoundRobin => 'Round robin (todos contra todos)',
            self::Mexicano => 'Mexicano (2 rondas)',
        };
    }
}
