<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public record that one player replaced another in a pair. Read by the public
 * player page to show "reemplazó a …" / "reemplazado por …". Display-facing, not
 * just an admin audit.
 */
class PlayerSubstitution extends Model
{
    protected $fillable = [
        'tournament_id', 'category_id', 'pair_id',
        'old_player_id', 'new_player_id', 'reason', 'created_by',
    ];

    public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function pair(): BelongsTo { return $this->belongsTo(Pair::class); }
    public function oldPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'old_player_id'); }
    public function newPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'new_player_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
