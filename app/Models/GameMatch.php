<?php

namespace App\Models;

use App\Enums\MatchResultType;
use App\Enums\MatchState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'group_id',
        'pair_a_id',
        'pair_b_id',
        'round',
        'slot',
        'feeder_a_id',
        'feeder_b_id',
        'feeder_a_source',
        'feeder_b_source',
        'is_third_place',
        'court_id',
        'starts_at',
        'duration_minutes',
        'state',
        'result_type',
        'sets',
        'winner_pair_id',
        'incident_note',
        'proposed_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'sets' => 'array',
            'is_third_place' => 'boolean',
            'starts_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'state' => MatchState::class,
            'result_type' => MatchResultType::class,
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function group()
    {
        return $this->belongsTo(Group::class);
    }
    public function pairA()
    {
        return $this->belongsTo(Pair::class, 'pair_a_id');
    }
    public function pairB()
    {
        return $this->belongsTo(Pair::class, 'pair_b_id');
    }
    public function winner()
    {
        return $this->belongsTo(Pair::class, 'winner_pair_id');
    }

    /** The losing pair id of a decided match (null if undecided). */
    public function loserPairId(): ?int
    {
        if (! $this->winner_pair_id) return null;
        return $this->winner_pair_id === $this->pair_a_id ? $this->pair_b_id : $this->pair_a_id;
    }
    public function court()
    {
        return $this->belongsTo(Court::class);
    }
    public function feederA()
    {
        return $this->belongsTo(GameMatch::class, 'feeder_a_id');
    }
    public function feederB()
    {
        return $this->belongsTo(GameMatch::class, 'feeder_b_id');
    }

    /**
     * Human label for a side of the match, used on the schedule board. Returns
     * the pair name when known, otherwise a descriptor of where it comes from
     * (e.g. "Ganador P1·P2" for a Mexicano R2 feeder slot not yet resolved).
     */
    public function sideLabel(string $side): string
    {
        $pair = $side === 'a' ? $this->pairA : $this->pairB;
        if ($pair) {
            return $pair->name();
        }

        $feeder = $side === 'a' ? $this->feederA : $this->feederB;
        $source = $side === 'a' ? $this->feeder_a_source : $this->feeder_b_source;
        if ($feeder) {
            $word = $source === 'loser' ? 'Perdedor' : 'Ganador';
            // Show the feeder match's pairs compactly if known.
            $fa = $feeder->pairA?->name();
            $fb = $feeder->pairB?->name();
            $of = ($fa && $fb) ? " ({$fa} / {$fb})" : '';
            return "{$word}{$of}";
        }

        return 'Por definir';
    }

    /** A grouping/context label: "Categoría · Grupo X · Ronda N". */
    public function contextLabel(): string
    {
        $parts = [];
        if ($this->category) $parts[] = $this->category->name;
        if ($this->group) $parts[] = $this->group->name;
        if ($this->round) {
            // Group matches: "R1/R2". Bracket matches (no group): F/SF/QF/...
            $parts[] = $this->group_id
                ? 'R' . $this->round
                : $this->bracketRoundAbbr();
        }
        return implode(' · ', $parts);
    }

    /**
     * Total rounds in this category's bracket (matches with no group).
     * Cached per category for the request to avoid N+1 on boards/lists.
     */
    public function bracketTotalRounds(): int
    {
        static $cache = [];
        $cid = $this->category_id;
        if (! array_key_exists($cid, $cache)) {
            $cache[$cid] = (int) static::where('category_id', $cid)
                ->whereNull('group_id')
                ->max('round');
        }
        return $cache[$cid];
    }

    /**
     * Abbreviated bracket round by distance from the final:
     *  0 → F, 1 → SF, 2 → QF, 3 → 8F, 4 → 16F, 5 → 32F ...
     */
    public function bracketRoundAbbr(): string
    {
        $total = $this->bracketTotalRounds();
        if ($total < 1 || ! $this->round) return 'R' . $this->round;

        $distance = $total - $this->round; // 0 = final
        return match ($distance) {
            0 => 'F',
            1 => 'SF',
            2 => 'QF',
            default => (2 ** $distance) . 'F', // 3 → 8F, 4 → 16F, 5 → 32F
        };
    }

    /** Full Spanish bracket round name (Final, Semifinal, Cuartos, Octavos...). */
    public function bracketRoundName(): string
    {
        $total = $this->bracketTotalRounds();
        if ($total < 1 || ! $this->round) return 'Ronda ' . $this->round;

        $distance = $total - $this->round;
        return match ($distance) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Cuartos de final',
            3 => 'Octavos de final',
            4 => 'Dieciseisavos',
            5 => 'Treintaidosavos',
            default => 'Ronda ' . $this->round,
        };
    }

    /**
     * Scheduling phase key for this match, used to match it to a phase window.
     * Group matches → 'groups'. Bracket matches → their round by distance from
     * the final: final / semifinal / quarterfinal / r16 / r32.
     */
    public function phaseKey(): string
    {
        if ($this->group_id) return 'groups';

        $total = $this->bracketTotalRounds();
        if ($total < 1 || ! $this->round) return 'groups';

        $distance = $total - $this->round;
        return match ($distance) {
            0 => 'final',
            1 => 'semifinal',
            2 => 'quarterfinal',
            3 => 'r16',
            4 => 'r32',
            default => 'r32',
        };
    }

    /**
     * Schedule status for board coloring:
     *  - 'played'    : has a confirmed result
     *  - 'playing'   : now is within the match's time window, no result yet
     *  - 'scheduled' : has a time but hasn't started / no result
     * Time-based (CDMX). Only meaningful once scheduled.
     */
    public function scheduleStatus(): string
    {
        if ($this->state === MatchState::Confirmed || $this->winner_pair_id) {
            return 'played';
        }
        if ($this->starts_at) {
            $start = $this->starts_at->copy();
            $end = $start->copy()->addMinutes((int) ($this->duration_minutes ?: 60));
            $now = now();
            if ($now->betweenIncluded($start, $end)) {
                return 'playing';
            }
        }
        return 'scheduled';
    }

    /** Both pairs known → results can be entered. */
    public function isReadyForResult(): bool
    {
        return (bool) ($this->pair_a_id && $this->pair_b_id);
    }

    public function isConfirmed(): bool
    {
        return $this->state === MatchState::Confirmed;
    }

    public function isReady(): bool
    {
        return $this->pair_a_id !== null && $this->pair_b_id !== null;
    }

    /** All player ids involved (for player-level scheduling conflicts, Phase 7). */
    public function playerIds(): array
    {
        return array_merge(
            $this->pairA?->playerIds() ?? [],
            $this->pairB?->playerIds() ?? [],
        );
    }

    /** Sets won by each side from the sets array. Returns [aSets, bSets]. */
    public function setsWon(): array
    {
        $a = $b = 0;
        foreach ($this->sets ?? [] as $set) {
            [$ga, $gb] = [$set[0] ?? 0, $set[1] ?? 0];
            if ($ga > $gb) $a++;
            elseif ($gb > $ga) $b++;
        }
        return [$a, $b];
    }

    /** Games won by each side across all sets. Returns [aGames, bGames]. */
    public function gamesWon(): array
    {
        $a = $b = 0;
        foreach ($this->sets ?? [] as $set) {
            $a += $set[0] ?? 0;
            $b += $set[1] ?? 0;
        }
        return [$a, $b];
    }
}
