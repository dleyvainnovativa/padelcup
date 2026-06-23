<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::with('tournament')->orderBy('scope')->orderBy('sort_order')->orderBy('id')->get();
        $tournaments = Tournament::orderBy('name')->get(['id', 'name']);

        return view('admin.ads.index', compact('ads', 'tournaments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'scope' => ['required', 'in:global,tournament'],
            'tournament_id' => ['nullable', 'required_if:scope,tournament', 'exists:tournaments,id'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store('ads', config('filesystems.default'));

        Ad::create([
            'title' => $data['title'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'scope' => $data['scope'],
            'tournament_id' => $data['scope'] === 'tournament' ? $data['tournament_id'] : null,
            'image_path' => $path,
            'is_active' => true,
            'sort_order' => (int) (Ad::max('sort_order') + 1),
        ]);

        return back()->with('status', 'Anuncio creado.');
    }

    public function update(Request $request, Ad $ad)
    {
        $ad->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Anuncio actualizado.');
    }

    public function destroy(Ad $ad)
    {
        if ($ad->image_path) {
            Storage::disk(config('filesystems.default'))->delete($ad->image_path);
        }
        $ad->delete();

        return back()->with('status', 'Anuncio eliminado.');
    }

    /** Public click-through: increment counter, then redirect to the target. */
    public function click(Ad $ad)
    {
        $ad->increment('clicks');

        return $ad->link_url
            ? redirect()->away($ad->link_url)
            : redirect()->back();
    }
}
