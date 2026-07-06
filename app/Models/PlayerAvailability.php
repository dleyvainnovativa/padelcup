<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAvailability extends Model
{
    protected $fillable = [
        'tournament_id',
        'normalized_name',
        'day',
        'earliest_time',
        'latest_time',
    ];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * BACKWARD-COMPATIBLE floor map (unchanged shape), used by the current
     * scheduler (2c) and the calendar cheatsheet:
     *   [ normalized_name => [ 'Y-m-d' => 'HH:MM' (available FROM) ] ]
     * This intentionally exposes only the "from" time so existing consumers keep
     * working untouched. Use windowsFor() when you need the full range.
     */
    public static function mapFor(Tournament $tournament): array
    {
        $map = [];
        foreach (static::where('tournament_id', $tournament->id)->get() as $row) {
            $day = $row->day instanceof \Carbon\Carbon ? $row->day->format('Y-m-d') : (string) $row->day;
            $map[$row->normalized_name][$day] = substr((string) $row->earliest_time, 0, 5);
        }
        return $map;
    }

    /**
     * Full availability WINDOWS for a tournament:
     *   [ normalized_name => [ 'Y-m-d' => ['from' => 'HH:MM', 'until' => 'HH:MM'|null] ] ]
     * 'until' is null when no upper bound was set. Use this in the scheduler when
     * it's upgraded to honor the upper bound, and anywhere the range is shown.
     */
    public static function windowsFor(Tournament $tournament): array
    {
        $map = [];
        foreach (static::where('tournament_id', $tournament->id)->get() as $row) {
            $day = $row->day instanceof \Carbon\Carbon ? $row->day->format('Y-m-d') : (string) $row->day;
            $map[$row->normalized_name][$day] = [
                'from' => substr((string) $row->earliest_time, 0, 5),
                'until' => $row->latest_time ? substr((string) $row->latest_time, 0, 5) : null,
            ];
        }
        return $map;
    }
}
