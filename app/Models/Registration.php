<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pair_id',
        'category_id',
        'source',
        'status',
        'payment_status',
        'hold_expires_at',
        'terms_accepted_at',
        'terms_version',
    ];

    protected function casts(): array
    {
        return [
            'source' => RegistrationSource::class,
            'status' => RegistrationStatus::class,
            'payment_status' => PaymentStatus::class,
            'hold_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function pair()
    {
        return $this->belongsTo(Pair::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invitation()
    {
        return $this->hasOne(PairInvitation::class);
    }

    public function isInPool(): bool
    {
        return $this->status->isInPool();
    }
}
