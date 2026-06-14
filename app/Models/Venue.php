<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = ['tournament_id', 'name', 'address'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function courts()
    {
        return $this->hasMany(Court::class);
    }
}
