<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RankingSystem;
use App\Services\Ranking\RankingPlayerDetail;
use Illuminate\Http\Request;

class RankingPlayerController extends Controller
{
    public function __construct(private RankingPlayerDetail $detail) {}

    /** GET ranking-systems/{rankingSystem}/player/{key} */
    public function show(Request $request, RankingSystem $rankingSystem, string $key)
    {
        abort_unless($rankingSystem->created_by === $request->user()->id, 403);

        $scope = $request->query('cat', 'all');
        $data = $this->detail->for($rankingSystem, $key, $scope);
        abort_if($data === null, 404);

        return view('dashboard.rankings.player', [
            'system'   => $rankingSystem,
            'p'        => $data,
            'scope'    => $scope,
            'isPublic' => false,
        ]);
    }
}
