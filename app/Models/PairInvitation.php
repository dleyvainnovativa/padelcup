<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PairInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'pair_id',
        'registration_id',
        'invited_by_user_id',
        'invitee_email',
        'target_player_id',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PairInvitation $inv) {
            if (blank($inv->token)) {
                $inv->token = Str::random(48);
            }
        });
    }

    public function pair()
    {
        return $this->belongsTo(Pair::class);
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
