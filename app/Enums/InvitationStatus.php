<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';     // sent, awaiting partner accept + pay
    case Accepted = 'accepted';   // partner joined and paid
    case Expired = 'expired';     // TTL passed
    case Cancelled = 'cancelled'; // registering player cancelled / resolved

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Accepted => 'Aceptada',
            self::Expired => 'Expirada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function pillVariant(): string
    {
        return match ($this) {
            self::Accepted => 'ok',
            self::Pending => 'warn',
            self::Expired, self::Cancelled => 'bad',
        };
    }
}
