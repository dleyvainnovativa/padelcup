<?php

namespace App\Models;

use App\Enums\RankingAchievement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger row: player X earned `points` for `achievement` in a given
 * category/tournament, counting toward a given ranking system. Immutable in
 * spirit — rewritten wholesale on re-finalize, never incremented.
 */
class RankingPoint extends Model
{
    protected $fillable = [
        'ranking_system_id', 'tournament_id', 'category_id',
        'player_id', 'pair_id', 'achievement', 'points', 'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'points'     => 'integer',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(RankingSystem::class, 'ranking_system_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(Pair::class);
    }

    /** The achievement as an enum (null if a stale/unknown key). */
    public function achievementEnum(): ?RankingAchievement
    {
        return RankingAchievement::tryFrom($this->achievement);
    }
}
