<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPrediction extends Model
{
    protected $fillable = [
        'tournament_id',
        'game_match_id',
        'user_id',
        'sets',
        'points',
        'correct',
        'scored_at',
    ];

    protected $casts = [
        'sets' => 'array',
        'correct' => 'boolean',
        'scored_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'game_match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
