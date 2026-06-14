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

    public function pairs()
    {
        return $this->belongsToMany(Pair::class, 'group_pair')->withTimestamps();
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class);
    }
}
