<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Tournament\SchedulingService;
use Illuminate\Console\Command;

/**
 * Re-prune matches whose scheduled time no longer lands on a valid slot for its
 * day — e.g. after narrowing a day's hours (day_hours). Normally this runs
 * automatically when the schedule config is saved; this command fixes data that
 * was placed BEFORE the per-day-hours change, or when the auto-prune didn't run.
 *
 * Usage:
 *   php artisan schedule:prune 23            # prune tournament id 23
 *   php artisan schedule:prune 23 --dry-run  # preview what WOULD move
 */
class PruneInvalidSchedule extends Command
{
    protected $signature = 'schedule:prune {tournament : Tournament id} {--dry-run : Show what would move without changing anything}';
    protected $description = 'Move matches that no longer land on a valid per-day slot back to "sin programar".';

    public function handle(SchedulingService $scheduling): int
    {
        $tournament = Tournament::find($this->argument('tournament'));
        if (! $tournament) {
            $this->error('Tournament not found.');
            return self::FAILURE;
        }

        $this->info("Tournament: {$tournament->name} (#{$tournament->id})");

        // Build the per-day slot map for reporting.
        $daySlots = $tournament->daySlotMap();

        $matches = \App\Models\GameMatch::whereHas('category', fn ($q) =>
                $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->with('category:id,name')
            ->get();

        $validDates = $tournament->playDays()->map->format('Y-m-d')->all();

        $wouldMove = [];
        foreach ($matches as $m) {
            $local = $m->starts_at->timezone('America/Mexico_City');
            $date = $local->format('Y-m-d');
            $time = $local->format('H:i');
            $slotsForDate = $daySlots[$date]['slots'] ?? [];

            if (! in_array($date, $validDates, true) || ! in_array($time, $slotsForDate, true)) {
                $wouldMove[] = [$m->id, $m->category->name ?? '—', $date, $time];
            }
        }

        if (empty($wouldMove)) {
            $this->info('Nothing to prune — all placements are on valid slots.');
            return self::SUCCESS;
        }

        $this->warn(count($wouldMove) . ' match(es) are on invalid slots:');
        $this->table(['Match', 'Category', 'Date', 'Time'], $wouldMove);

        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing changed. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        $moved = $scheduling->pruneInvalidSchedule($tournament);
        $this->info("Moved {$moved} match(es) back to \"sin programar\".");
        return self::SUCCESS;
    }
}
