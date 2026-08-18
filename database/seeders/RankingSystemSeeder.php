<?php

namespace Database\Seeders;

use App\Enums\RankingAchievement;
use App\Models\RankingSystem;
use Illuminate\Database\Seeder;

/**
 * Seeds a default "PadelCup" ranking system with placeholder points, ready to
 * clone/edit per association once the AVP provides real numbers.
 *
 * created_by is left null here (system-level default). If you prefer it owned by
 * a specific manager, pass a user id. Because the CRUD scopes by created_by, a
 * null-owner system won't appear in any manager's list — so for a visible
 * starter, set OWNER_USER_ID below to the manager you want to see it.
 *
 * Run: php artisan db:seed --class=RankingSystemSeeder
 */
class RankingSystemSeeder extends Seeder
{
    /** Set to a user id to make the seeded system visible/editable for them. */
    private const OWNER_USER_ID = null;

    public function run(): void
    {
        RankingSystem::updateOrCreate(
            ['name' => 'PadelCup (predeterminado)'],
            [
                'owner_label' => 'PadelCup',
                'scope'       => 'player',
                'stacking'    => 'cumulative',
                'points'      => RankingAchievement::defaultSchedule(),
                'is_active'   => true,
                'created_by'  => self::OWNER_USER_ID,
            ]
        );
    }
}
