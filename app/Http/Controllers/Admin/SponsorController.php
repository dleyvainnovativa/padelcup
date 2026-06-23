<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SponsorController extends Controller
{
    public function index()
    {
        // Admin-created sponsors only (manager sponsors stay in their tournament).
        $sponsors = Sponsor::with('tournament')
            ->where('is_admin', true)
            ->orderBy('scope')->orderBy('sort_order')->orderBy('id')
            ->get();
        $tournaments = Tournament::orderBy('name')->get(['id', 'name']);

        return view('admin.sponsors.index', compact('sponsors', 'tournaments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'scope' => ['required', 'in:global,tournament'],
            'tournament_id' => ['nullable', 'required_if:scope,tournament', 'exists:tournaments,id'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store('sponsors', config('filesystems.default'));

        Sponsor::create([
            'name' => $data['name'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'scope' => $data['scope'],
            'tournament_id' => $data['scope'] === 'tournament' ? $data['tournament_id'] : null,
            'image_path' => $path,
            'is_admin' => true,
            'is_active' => true,
            'sort_order' => (int) (Sponsor::where('is_admin', true)->max('sort_order') + 1),
        ]);

        return back()->with('status', 'Patrocinador creado.');
    }

    public function update(Request $request, Sponsor $sponsor)
    {
        abort_unless($sponsor->is_admin, 404);
        $sponsor->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Patrocinador actualizado.');
    }

    public function destroy(Sponsor $sponsor)
    {
        abort_unless($sponsor->is_admin, 404);
        if ($sponsor->image_path) {
            Storage::disk(config('filesystems.default'))->delete($sponsor->image_path);
        }
        $sponsor->delete();

        return back()->with('status', 'Patrocinador eliminado.');
    }
}
