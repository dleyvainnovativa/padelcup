<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'role',
        'terms_accepted_at',
        'terms_version',
        'email_verified_at',
        'stripe_account_id',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
        'stripe_onboarded_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'stripe_onboarded_at' => 'datetime',
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class, 'manager_id');
    }

    public function canReceivePayments(): bool
    {
        return (bool) $this->stripe_charges_enabled;
    }

    // --- Roles ---------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }
    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }

    /** Initials for the topbar avatar. */
    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->take(2)
            ->implode('');
    }

    // Player profile links (the Player/User split) are added in Phase 1.
}
