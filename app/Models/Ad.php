<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ad extends Model
{
    protected $fillable = [
        'title', 'image_path', 'link_url', 'scope', 'tournament_id',
        'is_active', 'sort_order', 'clicks',
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

    public function isGlobal(): bool
    {
        return $this->scope === 'global';
    }

    /**
     * Active ads to display on a given tournament's public pages: the
     * tournament's own ads, plus global ads unless the tournament opts out.
     * Ordered by sort_order then id.
     */
    public static function forTournament(Tournament $tournament): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($tournament) {
                $q->where(function ($q) use ($tournament) {
                        $q->where('scope', 'tournament')->where('tournament_id', $tournament->id);
                    });
                if (! $tournament->hide_global_ads) {
                    $q->orWhere('scope', 'global');
                }
            })
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }
}
