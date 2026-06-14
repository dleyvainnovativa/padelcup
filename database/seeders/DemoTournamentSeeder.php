<?php

namespace Database\Seeders;

use App\Enums\CategoryFormat;
use App\Models\Category;
use App\Models\Court;
use App\Models\Pair;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds an admin, a manager, and one realistic demo tournament with
 * categories, a venue + courts, and a handful of pairs so the dashboard
 * shows real data immediately.
 */
class DemoTournamentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@padelcup.mx'],
            [
                'name' => 'Admin PadelCup',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'terms_accepted_at' => now(),
                'terms_version' => '1.0',
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@padelcup.mx'],
            [
                'name' => 'Daniel Manager',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'terms_accepted_at' => now(),
                'terms_version' => '1.0',
            ]
        );

        $tournament = Tournament::create([
            'manager_id' => $manager->id,
            'name' => 'Torneo Apertura 2026',
            'slug' => 'torneo_apertura_2026',
            'description' => 'Torneo recreativo de pádel en CDMX.',
            'starts_on' => now()->addWeeks(2)->toDateString(),
            'ends_on' => now()->addWeeks(2)->addDays(8)->toDateString(),
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => now()->addWeek(),
            'platform_fee_centavos' => 5000, // $50
        ]);

        $venue = Venue::create([
            'tournament_id' => $tournament->id,
            'name' => 'Club Raqueta CDMX',
            'address' => 'Av. Insurgentes Sur, CDMX',
        ]);

        foreach (range(1, 4) as $n) {
            Court::create([
                'venue_id' => $venue->id,
                'name' => "Cancha {$n}",
                'sort_order' => $n,
            ]);
        }

        $categories = [
            ['5ta Femenil', CategoryFormat::RoundRobin, 12, 3],
            ['5ta Varonil', CategoryFormat::Hybrid, 16, 1],
            ['Mixtos Intermedios', CategoryFormat::Elimination, 16, 6],
            ['Abierto Femenil', CategoryFormat::RoundRobin, 5, 5],
        ];

        foreach ($categories as $i => [$name, $format, $cap, $count]) {
            $category = Category::create([
                'tournament_id' => $tournament->id,
                'name' => $name,
                'format' => $format,
                'preferred_group_size' => 4,
                'min_pairs' => 4,
                'max_pairs' => $cap,
                'price_centavos' => 120000, // $1,200
                'tint' => ($i % 6) + 1,
            ]);

            // A few demo pairs
            for ($p = 0; $p < $count; $p++) {
                $a = Player::factory()->create(['created_by' => $manager->id]);
                $b = Player::factory()->create(['created_by' => $manager->id]);
                Pair::create([
                    'category_id' => $category->id,
                    'player1_id' => $a->id,
                    'player2_id' => $b->id,
                ]);
            }
        }
    }
}
