<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id',
        'player_id',
        'payer_user_id',
        'connected_account_id',
        'amount_centavos',
        'platform_fee_centavos',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_refund_id',
        'status',
        'refunded_centavos',
        'paid_at',
        'refunded_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function amountFormatted(): string
    {
        return '$' . number_format($this->amount_centavos / 100, 2) . ' MXN';
    }
}
