<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SponsorController extends Controller
{
    public function index(Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        return view('dashboard.sponsors.index', [
            'tournament' => $tournament,
            'sponsors' => $tournament->sponsors()->get(),
        ]);
    }

    public function store(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store('sponsors', config('filesystems.default'));

        $tournament->sponsors()->create([
            'name' => $data['name'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'image_path' => $path,
            'sort_order' => (int) ($tournament->sponsors()->max('sort_order') + 1),
            'is_active' => true,
        ]);

        return back()->with('status', 'Patrocinador agregado.');
    }

    public function update(Request $request, Tournament $tournament, Sponsor $sponsor)
    {
        $this->authorize('update', $tournament);
        abort_unless($sponsor->tournament_id === $tournament->id, 404);

        $sponsor->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Patrocinador actualizado.');
    }

    public function destroy(Tournament $tournament, Sponsor $sponsor)
    {
        $this->authorize('update', $tournament);
        abort_unless($sponsor->tournament_id === $tournament->id, 404);

        if ($sponsor->image_path) {
            Storage::disk(config('filesystems.default'))->delete($sponsor->image_path);
        }
        $sponsor->delete();

        return back()->with('status', 'Patrocinador eliminado.');
    }
}
