<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Tournament\TournamentTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Server-side JSON backup of tournaments, one file per tournament, overwritten
 * each run. Designed for a Hostinger hourly cron (no queue worker):
 *
 *   php /path/to/artisan backup:tournaments
 *
 * Files land in storage/app/backups/tournament-{id}.json. Restore by feeding a
 * file back through the existing tournament JSON importer.
 *
 * By default it backs up every non-completed tournament (active/setup); pass an
 * id to back up just one, or --all to include completed ones too.
 */
class BackupTournaments extends Command
{
    protected $signature = 'backup:tournaments
                            {tournament? : Only back up this tournament id}
                            {--all : Include completed tournaments too}';

    protected $description = 'Save a JSON backup of each tournament to server storage (overwrites per tournament).';

    public function handle(TournamentTransferService $transfer): int
    {
        $query = Tournament::query();

        if ($id = $this->argument('tournament')) {
            $query->whereKey($id);
        } elseif (! $this->option('all')) {
            // Skip completed by default — they don't change anymore.
            $query->where('phase', '!=', \App\Enums\TournamentPhase::Completed->value);
        }

        $tournaments = $query->get();
        if ($tournaments->isEmpty()) {
            $this->info('No hay torneos que respaldar.');
            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $ok = 0;
        foreach ($tournaments as $t) {
            try {
                $data = $transfer->export($t);
                $payload = [
                    'backed_up_at' => now()->toIso8601String(),
                    'tournament_id' => $t->id,
                    'tournament_name' => $t->name,
                    'data' => $data,
                ];
                $path = "backups/tournament-{$t->id}.json";
                $disk->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $ok++;
                $this->line("OK  {$t->name} -> {$path}");
            } catch (\Throwable $e) {
                $this->error("ERR {$t->name} (#{$t->id}): {$e->getMessage()}");
            }
        }

        $this->info("Respaldados {$ok}/{$tournaments->count()} torneos.");
        return self::SUCCESS;
    }
}
