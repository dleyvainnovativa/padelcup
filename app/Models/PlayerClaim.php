<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request by a User (role=player) to own one or more Player records.
 * Reviewed by an admin. See migration + DESIGN.md.
 */
class PlayerClaim extends Model
{
    protected $fillable = [
        'user_id', 'status', 'note', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlayerClaimItem::class);
    }

    /** The Player records this claim covers. */
    public function players()
    {
        return $this->belongsToMany(Player::class, 'player_claim_items');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => 'Aprobada',
            self::REJECTED => 'Rechazada',
            default => 'En revisión',
        };
    }
}
