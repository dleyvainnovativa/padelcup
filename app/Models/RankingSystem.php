<?php

namespace App\Models;

use App\Enums\RankingAchievement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named ranking with its own points schedule. Owned by an association (AVP,
 * regional league) or PadelCup itself. Feeds off many tournaments (pivot) and
 * accumulates points into the ranking_points ledger.
 */
class RankingSystem extends Model
{
    protected $fillable = [
        'name', 'owner_label', 'scope', 'stacking', 'points', 'is_active', 'created_by',
    ];

    protected $casts = [
        'points'    => 'array',   // { achievement_key => points }
        'is_active' => 'boolean',
    ];

    /** Tournaments that feed this ranking. Pivot carries finalized_at. */
    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'ranking_system_tournament')
            ->withPivot('finalized_at')
            ->withTimestamps();
    }

    /** Every ledger row for this system. */
    public function points(): HasMany
    {
        return $this->hasMany(RankingPoint::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Points schedule helpers ------------------------------------------

    /** Points for one achievement (0 if unset). */
    public function pointsFor(RankingAchievement|string $achievement): int
    {
        $key = $achievement instanceof RankingAchievement ? $achievement->value : $achievement;
        return (int) (($this->points ?? [])[$key] ?? 0);
    }

    public function isCumulative(): bool
    {
        return ($this->stacking ?? 'cumulative') === 'cumulative';
    }

    /**
     * Return the full schedule as [RankingAchievement => points], filling any
     * missing achievement with 0. Used by the points editor so the form always
     * shows every achievement even if the stored JSON is partial.
     */
    public function schedule(): array
    {
        $out = [];
        foreach (RankingAchievement::cases() as $a) {
            $out[$a->value] = $this->pointsFor($a);
        }
        return $out;
    }

    /** Convenience: seed a new system with the default PadelCup schedule. */
    public static function defaultPoints(): array
    {
        return RankingAchievement::defaultSchedule();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
