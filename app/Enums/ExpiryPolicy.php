<?php

namespace App\Enums;

enum ExpiryPolicy: string
{
    case AutoRefund = 'auto_refund';
    case HoldCredit = 'hold_credit';
    case ManualReview = 'manual_review';

    public function label(): string
    {
        return match ($this) {
            self::AutoRefund => 'Reembolso automático',
            self::HoldCredit => 'Guardar como crédito',
            self::ManualReview => 'Revisión manual',
        };
    }
}
