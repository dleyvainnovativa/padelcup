<?php

namespace App\Models;

use App\Enums\ExpiryPolicy;
use App\Enums\TournamentPhase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\RankingSystem;
use App\Models\RankingPoint;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Tournament extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manager_id',
        'name',
        'slug',
        'description',
        'rules',
        'logo_path',
        'cover_image_path',
        'starts_on',
        'ends_on',
        'play_start',
        'play_end',
        'match_duration_minutes',
        'min_rest_minutes',
        'registration_opens_at',
        'registration_closes_at',
        'phase',
        'locked_at',
        'is_listed',
        'invitation_ttl_hours',
        'expiry_policy',
        'platform_fee_centavos',
        'iva_enabled',
        'hide_global_ads',
        'day_durations',
        'day_hours',
        'tiebreak_order'
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'phase' => TournamentPhase::class,
            'locked_at' => 'datetime',
            'expiry_policy' => ExpiryPolicy::class,
            'iva_enabled' => 'boolean',
            'is_listed' => 'boolean',
            'hide_global_ads' => 'boolean',
            'day_durations' => 'array',
            'day_hours' => 'array',
            'tiebreak_order' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tournament $t) {
            if (blank($t->slug)) {
                $t->slug = static::uniqueSlug($t->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        // Include soft-deleted rows: the DB unique index counts them too, so a
        // trashed tournament still occupies its slug.
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // --- Relationships -------------------------------------------------

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function venues()
    {
        return $this->hasMany(Venue::class);
    }

    public function courts()
    {
        return $this->hasManyThrough(Court::class, Venue::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function phaseWindows()
    {
        return $this->hasMany(PhaseWindow::class);
    }

    public function sponsors()
    {
        return $this->hasMany(Sponsor::class)->orderBy('sort_order')->orderBy('id');
    }

    // --- Helpers -------------------------------------------------------

    public function isSetup(): bool
    {
        return $this->phase === TournamentPhase::Setup;
    }
    public function isLocked(): bool
    {
        return $this->phase === TournamentPhase::Locked;
    }

    /** Public URL for the cover image, or null if none uploaded. */
    public function coverImageUrl(): ?string
    {
        if (blank($this->cover_image_path)) return null;
        return \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->url($this->cover_image_path);
    }

    /** Lock the tournament on the first confirmed result (idempotent). */
    public function lock(): void
    {
        if ($this->phase === TournamentPhase::Setup) {
            $this->forceFill([
                'phase' => TournamentPhase::Locked,
                'locked_at' => now(),
            ])->save();
        }
    }

    /** Tournament play days (inclusive), as Carbon dates. */
    public function playDays(): \Illuminate\Support\Collection
    {
        $start = $this->starts_on ?? today('America/Mexico_City');
        $end = $this->ends_on ?? $start;
        $days = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $days->push($cursor->copy());
            $cursor->addDay();
        }
        return $days;
    }

    /**
     * Time-slot labels for the grid, stepping by match_duration_minutes from
     * play_start to play_end. Returns ['08:00', '09:30', ...].
     */
    public function timeSlots(): array
    {
        return $this->timeSlotsForDuration((int) ($this->match_duration_minutes ?: 75));
    }
    /**
     * Match duration (minutes) for a specific play day. Uses a per-day override
     * from day_durations ({'Y-m-d' => minutes}) if present, else the tournament
     * default (match_duration_minutes). Lets the last day run longer for SF/F.
     */
    public function durationForDay(\Carbon\Carbon|string $day): int
    {
        $default = (int) ($this->match_duration_minutes ?: 75);
        $key = $day instanceof \Carbon\Carbon ? $day->format('Y-m-d') : (string) $day;
        $overrides = $this->day_durations ?? [];
        $val = $overrides[$key] ?? null;
        return $val ? (int) $val : $default;
    }

    /**
     * [ 'Y-m-d' => durationMinutes ] for every play day — the resolved duration
     * (override or default) per day, for the scheduler.
     *
     * @return array<string,int>
     */
    public function dayDurationMap(): array
    {
        $map = [];
        foreach ($this->playDays() as $d) {
            $map[$d->format('Y-m-d')] = $this->durationForDay($d);
        }
        return $map;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    public function ads()
    {
        return $this->hasMany(\App\Models\Ad::class);
    }
    public function matches()
    {
        return $this->hasManyThrough(
            GameMatch::class,
            Category::class,
            'tournament_id', // Foreign key on categories table
            'category_id',   // Foreign key on game_matches table
            'id',            // Local key on tournaments table
            'id'             // Local key on categories table
        );
    }
    // --- Ranking systems this tournament feeds (pivot carries finalized_at) -----
    public function rankingSystems(): BelongsToMany
    {
        return $this->belongsToMany(RankingSystem::class, 'ranking_system_tournament')
            ->withPivot('finalized_at')
            ->withTimestamps();
    }

    // --- Ledger rows produced by this tournament (all systems) ------------------
    public function rankingPoints(): HasMany
    {
        return $this->hasMany(RankingPoint::class);
    }

// --- Convenience flags for the UI -------------------------------------------

    /** Does this tournament feed ANY ranking system? */
    public function awardsRankingPoints(): bool
    {
        return $this->rankingSystems()->exists();
    }

    /** Has it been finalized for a given system? (pivot finalized_at set) */
    public function isFinalizedFor(RankingSystem|int $system): bool
    {
        $id = $system instanceof RankingSystem ? $system->id : $system;
        $row = $this->rankingSystems()->where('ranking_system_id', $id)->first();
        return $row && $row->pivot->finalized_at !== null;
    }


    public function hoursForDay(\Carbon\Carbon|string $day): array
    {
        $defStart = $this->play_start ?? '08:00';
        $defEnd   = $this->play_end ?? '23:00';

        $key = $day instanceof \Carbon\Carbon ? $day->format('Y-m-d') : (string) $day;
        $ov = ($this->day_hours ?? [])[$key] ?? null;

        if (is_array($ov) && ! empty($ov['start']) && ! empty($ov['end']) && $ov['start'] < $ov['end']) {
            return [(string) $ov['start'], (string) $ov['end']];
        }
        return [(string) $defStart, (string) $defEnd];
    }


// ── CHANGE 4 — timeSlotsForDuration(): accept optional start/end ─────────────
// REPLACE the existing method with this version (adds two optional params;
// callers that don't pass them get the global window, exactly as before).

    /** Slot labels from a start to an end time, stepping by $step minutes.
     *  $start/$end default to the tournament's global window when null. */
    private function timeSlotsForDuration(int $step, ?string $start = null, ?string $end = null): array
    {
        $step = max(1, $step);
        $slots = [];
        $startT = \Carbon\Carbon::parse($start ?? $this->play_start ?? '08:00', 'America/Mexico_City');
        $endT   = \Carbon\Carbon::parse($end ?? $this->play_end ?? '23:00', 'America/Mexico_City');

        $cursor = $startT->copy();
        while ($cursor->copy()->addMinutes($step)->lte($endT)) {
            $slots[] = $cursor->format('H:i');
            $cursor->addMinutes($step);
        }
        return $slots;
    }


// ── CHANGE 5 — timeSlotsForDay(): feed the per-day window ────────────────────
// REPLACE the existing method with this version.

    /**
     * Slot labels for a SPECIFIC day, stepping by that day's duration and bounded by
     * that day's hours (per-day override or global default).
     */
    public function timeSlotsForDay(\Carbon\Carbon|string $day): array
    {
        [$start, $end] = $this->hoursForDay($day);
        return $this->timeSlotsForDuration($this->durationForDay($day), $start, $end);
    }


// ── CHANGE 6 — daySlotMap(): include the per-day window ──────────────────────
// REPLACE the existing method with this version (adds start/end to each entry
// for any view/JS that wants to show the day's window; existing keys unchanged).

    /**
     * [ 'Y-m-d' => ['slots'=>[...], 'step'=>minutes, 'start'=>'HH:MM', 'end'=>'HH:MM'] ]
     * for every play day — each day's own grid rows, step, and window.
     *
     * @return array<string, array{slots: array<int,string>, step: int, start: string, end: string}>
     */
    public function daySlotMap(): array
    {
        $map = [];
        foreach ($this->playDays() as $d) {
            $step = $this->durationForDay($d);
            [$start, $end] = $this->hoursForDay($d);
            $map[$d->format('Y-m-d')] = [
                'slots' => $this->timeSlotsForDuration($step, $start, $end),
                'step'  => $step,
                'start' => $start,
                'end'   => $end,
            ];
        }
        return $map;
    }
}
