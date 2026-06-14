<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseWindow extends Model
{
    protected $table = 'tournament_phase_windows';

    protected $fillable = ['tournament_id', 'phase', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
