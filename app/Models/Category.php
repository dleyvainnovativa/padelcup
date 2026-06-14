<?php

namespace App\Models;

use App\Enums\CategoryFormat;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'name',
        'format',
        'group_format',
        'mexicano_pairing',
        'preferred_group_size',
        'advance_per_group',
        'extra_qualifiers',
        'min_pairs',
        'max_pairs',
        'price_centavos',
        'registration_opens_at',
        'registration_closes_at',
        'tint',
        'has_third_place',
        'whatsapp_group_url',
    ];

    protected function casts(): array
    {
        return [
            'format' => CategoryFormat::class,
            'group_format' => \App\Enums\GroupFormat::class,
            'mexicano_pairing' => \App\Enums\MexicanoPairing::class,
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'has_third_place' => 'boolean',
        ];
    }

    // --- Relationships -------------------------------------------------

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function pairs()
    {
        return $this->hasMany(Pair::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class)->orderBy('position');
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class);
    }

    /** Confirmed pairs eligible for the draw (in the pool). */
    public function poolPairs()
    {
        return $this->pairs()->whereHas('registration', function ($q) {
            $q->where('status', RegistrationStatus::Confirmed->value);
        });
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function confirmedRegistrations()
    {
        return $this->registrations()->where('status', RegistrationStatus::Confirmed->value);
    }

    // --- Capacity ------------------------------------------------------

    /** Count of pairs occupying a slot (confirmed + holding). */
    public function occupiedSlots(): int
    {
        return $this->registrations()
            ->whereIn('status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::PendingPayment->value,
            ])
            ->count();
    }

    public function isFull(): bool
    {
        return $this->max_pairs !== null && $this->occupiedSlots() >= $this->max_pairs;
    }

    public function belowMinimum(): bool
    {
        return $this->occupiedSlots() < $this->min_pairs;
    }

    // --- Money ---------------------------------------------------------

    public function priceFormatted(): string
    {
        return '$' . number_format($this->price_centavos / 100, 2) . ' MXN';
    }

    public function tintClass(): string
    {
        return 'cat-tint-' . (($this->tint - 1) % 6 + 1);
    }

    /**
     * Total pairs advancing to the bracket in a hybrid category:
     * (winners per group × number of groups) + extra qualifiers.
     * groupCount is passed in since groups are generated in Phase 5.
     */
    public function qualifiersTotal(int $groupCount): int
    {
        return ($this->advance_per_group * $groupCount) + $this->extra_qualifiers;
    }
}
