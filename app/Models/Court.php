<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $fillable = ['venue_id', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function availabilities()
    {
        return $this->hasMany(CourtAvailability::class);
    }
}
