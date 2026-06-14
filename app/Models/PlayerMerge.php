<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerMerge extends Model
{
    protected $fillable = [
        'canonical_player_id',
        'merged_player_id',
        'performed_by',
        'snapshot',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function canonical()
    {
        return $this->belongsTo(Player::class, 'canonical_player_id');
    }

    public function merged()
    {
        return $this->belongsTo(Player::class, 'merged_player_id');
    }
}
