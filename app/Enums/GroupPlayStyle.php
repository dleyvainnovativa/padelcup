<?php

namespace App\Enums;

enum MexicanoPairing: string
{
    case Cross = 'cross';     // W(A) vs L(B), W(B) vs L(A)
    case Classic = 'classic'; // W(A) vs W(B), L(A) vs L(B)

    public function label(): string
    {
        return match ($this) {
            self::Cross => 'Cruzado (ganador vs perdedor)',
            self::Classic => 'Clásico (ganadores y perdedores)',
        };
    }
}
