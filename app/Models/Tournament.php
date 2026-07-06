<?php

namespace App\Models;

use App\Enums\ExpiryPolicy;
use App\Enums\TournamentPhase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
     * Slot labels for a SPECIFIC day, stepping by that day's duration (per-day
     * override or default). Lets a longer-duration day draw its own rows.
     */
    public function timeSlotsForDay(\Carbon\Carbon|string $day): array
    {
        return $this->timeSlotsForDuration($this->durationForDay($day));
    }

    /** Slot labels from play_start to play_end stepping by $step minutes. */
    private function timeSlotsForDuration(int $step): array
    {
        $step = max(1, $step);
        $slots = [];
        $start = \Carbon\Carbon::parse($this->play_start ?? '08:00', 'America/Mexico_City');
        $end = \Carbon\Carbon::parse($this->play_end ?? '23:00', 'America/Mexico_City');

        $cursor = $start->copy();
        while ($cursor->copy()->addMinutes($step)->lte($end)) {
            $slots[] = $cursor->format('H:i');
            $cursor->addMinutes($step);
        }
        return $slots;
    }

    /**
     * [ 'Y-m-d' => ['slots' => [...], 'step' => minutes] ] for every play day —
     * each day's own grid rows + step, for the schedule view.
     *
     * @return array<string, array{slots: array<int,string>, step: int}>
     */
    public function daySlotMap(): array
    {
        $map = [];
        foreach ($this->playDays() as $d) {
            $step = $this->durationForDay($d);
            $map[$d->format('Y-m-d')] = [
                'slots' => $this->timeSlotsForDuration($step),
                'step' => $step,
            ];
        }
        return $map;
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
}
