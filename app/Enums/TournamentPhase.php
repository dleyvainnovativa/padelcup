<?php

namespace App\Enums;

enum TournamentPhase: string
{
    case Setup = 'setup';       // nothing played — free regeneration allowed
    case Locked = 'locked';     // first result confirmed — surgical changes only
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Configuración',
            self::Locked => 'En curso',
            self::Completed => 'Finalizado',
        };
    }

    /** Whether groups/brackets may be freely regenerated. */
    public function allowsRegeneration(): bool
    {
        return $this === self::Setup;
    }
}
