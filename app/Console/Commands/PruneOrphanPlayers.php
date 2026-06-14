<?php

namespace App\Console\Commands;

use App\Models\Player;
use Illuminate\Console\Command;

/**
 * Removes "orphan" players — those not referenced by any pair (player1 or
 * player2). Useful for cleaning up after repeated CSV imports that created
 * loose player records. Safe: only deletes players with zero pair links and
 * no linked user account.
 *
 * Usage:
 *   php artisan players:prune-orphans          (dry run, shows count)
 *   php artisan players:prune-orphans --force   (actually deletes)
 */
class PruneOrphanPlayers extends Command
{
    protected $signature = 'players:prune-orphans {--force : Actually delete the orphans}';

    protected $description = 'Delete players not attached to any pair (cleanup after imports)';

    public function handle(): int
    {
        $orphans = Player::query()
            ->whereDoesntHave('pairsAsPlayer1')
            ->whereDoesntHave('pairsAsPlayer2')
            ->whereNull('user_id') // never touch players linked to a login account
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No hay jugadores huérfanos. Nada que limpiar.');
            return self::SUCCESS;
        }

        $this->warn("Jugadores huérfanos encontrados: {$orphans->count()}");
        $this->table(
            ['ID', 'Nombre', 'Email', 'Teléfono'],
            $orphans->map(fn($p) => [$p->id, $p->name, $p->email ?? '—', $p->phone ?? '—'])->all()
        );

        if (! $this->option('force')) {
            $this->line('');
            $this->comment('Esto fue una simulación. Ejecuta con --force para eliminar.');
            return self::SUCCESS;
        }

        $count = $orphans->count();
        Player::whereIn('id', $orphans->pluck('id'))->forceDelete();

        $this->info("Eliminados {$count} jugadores huérfanos.");
        return self::SUCCESS;
    }
}
