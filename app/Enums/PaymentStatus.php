<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';   // charge initiated, not settled
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Overdue = 'overdue';   // manager-created, pay-later window passed

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Sin pagar',
            self::Pending => 'Procesando',
            self::Paid => 'Pagado',
            self::Refunded => 'Reembolsado',
            self::Overdue => 'Vencido',
        };
    }

    public function pillVariant(): string
    {
        return match ($this) {
            self::Paid => 'ok',
            self::Pending, self::Unpaid => 'warn',
            self::Overdue, self::Refunded => 'bad',
        };
    }
}
