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
        'is_singles',
        'player1_id',
        'player2_id',
        'display_name',
        'seed',
        'schedule_preferences',
    ];

    protected function casts(): array
    {
        return [
            'schedule_preferences' => 'array',
            'is_singles' => 'boolean',
        ];
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

    /**
     * A doubles unit is "complete" once its second player exists. A singles
     * unit is complete the moment player1 exists — there is no second slot to
     * wait on, so a lone player2_id === null must NOT read as incomplete.
     */
    public function isComplete(): bool
    {
        if ($this->is_singles) {
            return $this->player1_id !== null;
        }

        return $this->player2_id !== null;
    }

    /**
     * Number of players this unit expects — also the number of fees due.
     * Singles: 1. Doubles: 2. Used by PaymentReconciler to know how many
     * paid halves confirm the registration.
     */
    public function expectedPlayerCount(): int
    {
        return $this->is_singles ? 1 : 2;
    }

    public function name(): string
    {
        if (filled($this->display_name)) {
            return $this->display_name;
        }

        $p1 = $this->player1?->name ?? '—';

        // Singles: the unit IS one player — no partner slot, no "/ (pendiente)".
        if ($this->is_singles) {
            return $p1;
        }

        $p2 = $this->player2?->name ?? '(pendiente)';
        return "{$p1} / {$p2}";
    }
}
