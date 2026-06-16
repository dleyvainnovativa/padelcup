<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Sponsor extends Model
{
    protected $fillable = [
        'tournament_id', 'name', 'image_path', 'link_url', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) return null;
        return Storage::disk(config('filesystems.default'))->url($this->image_path);
    }
}
