<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Player record within a PlayerClaim.
 */
class PlayerClaimItem extends Model
{
    protected $fillable = ['player_claim_id', 'player_id'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(PlayerClaim::class, 'player_claim_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
