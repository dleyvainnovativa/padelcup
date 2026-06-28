<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAvailability extends Model
{
    protected $fillable = [
        'tournament_id', 'normalized_name', 'day', 'earliest_time',
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
     * Availability map for a tournament, for use by the scheduler (2c) and UI:
     *   [ normalized_name => [ 'Y-m-d' => 'HH:MM', ... ], ... ]
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
}
