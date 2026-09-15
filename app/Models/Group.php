<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'position'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Pairs in this group, in manager-defined order.
     *
     * `position` is authoritative: buildMexicanoMatches() reads this order for
     * R1 seeding, and the board / match listing render in it. withPivot exposes
     * it so callers can read/tweak; orderByPivot makes every read stable.
     */
    public function pairs()
    {
        return $this->belongsToMany(Pair::class, 'group_pair')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class);
    }
}
