<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pair extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'player1_id',
        'player2_id',
        'display_name',
        'seed',
        'schedule_preferences',
    ];

    protected function casts(): array
    {
        return ['schedule_preferences' => 'array'];
    }

    // --- Relationships -------------------------------------------------

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function registration()
    {
        return $this->hasOne(Registration::class);
    }

    // --- Helpers -------------------------------------------------------

    /** Player IDs in this pair (1 or 2). Used by the scheduler conflict check. */
    public function playerIds(): array
    {
        return array_values(array_filter([$this->player1_id, $this->player2_id]));
    }

    public function sharesPlayerWith(Pair $other): bool
    {
        return (bool) array_intersect($this->playerIds(), $other->playerIds());
    }

    public function isComplete(): bool
    {
        return $this->player2_id !== null;
    }

    public function name(): string
    {
        if (filled($this->display_name)) {
            return $this->display_name;
        }
        $p1 = $this->player1?->name ?? '—';
        $p2 = $this->player2?->name ?? '(pendiente)';
        return "{$p1} / {$p2}";
    }
}
