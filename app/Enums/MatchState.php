<?php

namespace App\Enums;

enum MatchState: string
{
    case Scheduled = 'scheduled';   // created, not yet played/proposed
    case Proposed = 'proposed';     // a score was proposed, awaiting manager
    case Confirmed = 'confirmed';   // manager-approved; counts for standings

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::Proposed => 'Propuesto',
            self::Confirmed => 'Confirmado',
        };
    }

    public function pillVariant(): string
    {
        return match ($this) {
            self::Confirmed => 'ok',
            self::Proposed => 'warn',
            self::Scheduled => 'neutral',
        };
    }
}
