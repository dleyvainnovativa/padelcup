<?php

namespace App\Enums;

enum MatchResultType: string
{
    case Normal = 'normal';
    case Walkover = 'walkover';     // no-show
    case Retirement = 'retirement'; // injury mid-match
    case Default_ = 'default';      // administrative

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Walkover => 'Walkover (no se presentó)',
            self::Retirement => 'Retiro (lesión)',
            self::Default_ => 'Default',
        };
    }
}
