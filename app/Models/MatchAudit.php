<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_match_id',
        'user_id',
        'action',
        'before',
        'after',
        'note',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'game_match_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
