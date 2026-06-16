<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Pair;
use App\Models\Payment;
use App\Models\Tournament;
use App\Enums\PaymentStatus;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $manager = auth()->user();
        $tournamentIds = $manager->tournaments()->pluck('id');

        // --- Tournament tallies ---
        $tournaments = $manager->tournaments()
            ->withCount('categories')
            ->orderByDesc('starts_on')
            ->get();

        $activeCount = $tournaments->filter(fn($t) => ! $t->isSetup() && ! $this->isPast($t))->count();
        $setupCount = $tournaments->filter(fn($t) => $t->isSetup())->count();

        // --- Players / pairs across all my tournaments ---
        $pairsQuery = Pair::whereHas('category', fn($q) => $q->whereIn('tournament_id', $tournamentIds));
        $pairCount = (clone $pairsQuery)->count();
        $playerCount = (clone $pairsQuery)->get()->flatMap->playerIds()->unique()->count();

        // --- Revenue (net of platform fees), paid only ---
        $payments = Payment::whereHas('registration.category', fn($q) => $q->whereIn('tournament_id', $tournamentIds))
            ->where('status', PaymentStatus::Paid)
            ->get();
        $grossCentavos = $payments->sum('amount_centavos');
        $feeCentavos = $payments->sum('platform_fee_centavos');
        $refundCentavos = $payments->sum('refunded_centavos');
        $netCentavos = $grossCentavos - $feeCentavos - $refundCentavos;

        // --- Upcoming matches (next 10, across all tournaments) ---
        $now = now('America/Mexico_City');
        $upcoming = GameMatch::whereHas('category', fn($q) => $q->whereIn('tournament_id', $tournamentIds))
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', $now)
            ->whereNotNull('pair_a_id')->whereNotNull('pair_b_id')
            ->with(['category.tournament', 'court', 'pairA', 'pairB'])
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        // --- Today's match count ---
        $todayCount = GameMatch::whereHas('category', fn($q) => $q->whereIn('tournament_id', $tournamentIds))
            ->whereNotNull('starts_at')
            ->whereDate('starts_at', $now->toDateString())
            ->count();

        // --- Needs attention ---
        $pendingResults = GameMatch::whereHas('category', fn($q) => $q->whereIn('tournament_id', $tournamentIds))
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $now)
            ->where('state', '!=', \App\Enums\MatchState::Confirmed->value)
            ->whereNotNull('pair_a_id')->whereNotNull('pair_b_id')
            ->count();

        return view('dashboard.index', [
            'tournaments' => $tournaments,
            'activeCount' => $activeCount,
            'setupCount' => $setupCount,
            'pairCount' => $pairCount,
            'playerCount' => $playerCount,
            'netCentavos' => $netCentavos,
            'grossCentavos' => $grossCentavos,
            'upcoming' => $upcoming,
            'todayCount' => $todayCount,
            'pendingResults' => $pendingResults,
        ]);
    }

    private function isPast(Tournament $t): bool
    {
        $end = $t->ends_on ?? $t->starts_on;
        return $end !== null && $end->lessThan(today('America/Mexico_City'));
    }
}
