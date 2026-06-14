<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case PendingPayment = 'pending_payment'; // self-reg awaiting payment (holds slot w/ TTL)
    case Confirmed = 'confirmed';            // in the pool, counts for generation
    case Withdrawn = 'withdrawn';            // dropped out
    case Cancelled = 'cancelled';            // never completed / removed

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pago pendiente',
            self::Confirmed => 'Confirmada',
            self::Withdrawn => 'Retirada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function pillVariant(): string
    {
        return match ($this) {
            self::Confirmed => 'ok',
            self::PendingPayment => 'warn',
            self::Withdrawn, self::Cancelled => 'bad',
        };
    }

    /** Only confirmed registrations feed group/bracket generation. */
    public function isInPool(): bool
    {
        return $this === self::Confirmed;
    }
}
