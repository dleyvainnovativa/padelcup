<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'user_id',
        'created_by',
        'normalized_name',
    ];

    protected static function booted(): void
    {
        // Keep normalized_name in sync for fuzzy dedupe
        static::saving(function (Player $player) {
            $player->normalized_name = static::normalize($player->name);
        });

        // Auto-link to an existing user account when a player is created with an
        // email that matches a registered user. Email is a strong signal, so no
        // manual claim is needed (name-based claims stay manual — see
        // PlayerClaimService). Only fills an empty user_id; never reassigns.
        static::created(function (Player $player) {
            if ($player->user_id || blank($player->email)) return;
            $userId = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($player->email))])->value('id');
            if ($userId) {
                $player->forceFill(['user_id' => $userId])->saveQuietly();
            }
        });
    }

    /** Lowercased, accent-stripped, single-spaced name for matching. */
    public static function normalize(?string $name): string
    {
        $name = Str::lower(trim((string) $name));
        $name = Str::ascii($name);               // strip accents (José -> jose)
        return preg_replace('/\s+/', ' ', $name);
    }

    // --- Relationships -------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pairsAsPlayer1()
    {
        return $this->hasMany(Pair::class, 'player1_id');
    }

    public function pairsAsPlayer2()
    {
        return $this->hasMany(Pair::class, 'player2_id');
    }

    /** All pairs this player belongs to (either slot). */
    public function pairs()
    {
        return Pair::where('player1_id', $this->id)
            ->orWhere('player2_id', $this->id);
    }

    /** All pairs this player belongs to (either slot). */
    public function allPairsQuery()
    {
        return Pair::query()
            ->where(function ($query) {
                $query->where('player1_id', $this->id)
                    ->orWhere('player2_id', $this->id);
            });
    }

    public function isContactless(): bool
    {
        return blank($this->email) && blank($this->phone);
    }

    // --- Scopes --------------------------------------------------------

    public function scopePossibleDuplicatesOf(Builder $query, Player $player): Builder
    {
        return $query->where('id', '!=', $player->id)
            ->where(function (Builder $q) use ($player) {
                $q->where('normalized_name', static::normalize($player->name));
                if (filled($player->email)) {
                    $q->orWhere('email', $player->email);
                }
                if (filled($player->phone)) {
                    $q->orWhere('phone', $player->phone);
                }
            });
    }
}