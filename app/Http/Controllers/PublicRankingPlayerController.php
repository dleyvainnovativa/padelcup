<?php

namespace App\Http\Controllers;

use App\Models\RankingSystem;
use App\Services\Ranking\RankingPlayerDetail;
use Illuminate\Http\Request;

class PublicRankingPlayerController extends Controller
{
    public function __construct(private RankingPlayerDetail $detail) {}

    /** GET r/{rankingSystem}/jugador/{key} */
    public function show(Request $request, RankingSystem $rankingSystem, string $key)
    {
        abort_unless($rankingSystem->is_active, 404);

        $scope = $request->query('cat', 'all');
        $data = $this->detail->for($rankingSystem, $key, $scope);
        abort_if($data === null, 404);

        return view('public.rankings.player', [
            'system' => $rankingSystem,
            'p'      => $data,
            'scope'  => $scope,
        ]);
    }
}
