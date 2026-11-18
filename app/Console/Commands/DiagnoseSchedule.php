<?php

namespace App\Console\Commands;

use App\Models\PlayerAvailability;
use App\Models\Tournament;
use App\Services\Tournament\SchedulingService;
use App\Support\SchedulePhase;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Dry-run the REAL auto-scheduler against a tournament and explain, match by
 * match, what it placed and WHY anything didn't. Writes nothing to the DB.
 *
 * It calls SchedulingService::autoSchedule in dry-run + trace mode, so the
 * output reflects the exact engine the "Auto-programar" button uses — not a
 * re-implementation.
 *
 * Usage:
 *   php artisan schedule:diagnose 42                 # by tournament id
 *   php artisan schedule:diagnose canaco-padel-costa # by slug
 *   php artisan schedule:diagnose 42 --limit-off     # ignore the 2/día preference
 *   php artisan schedule:diagnose 42 --all           # also list every placed match
 */
class DiagnoseSchedule extends Command
{
    protected $signature = 'schedule:diagnose {tournament : Tournament id or slug}
        {--limit-off : Do not prefer max 2 matches per player per day}
        {--all : Also list every scheduled match, not just the problems}';

    protected $description = 'Dry-run the auto-scheduler and explain why QF/SF/F (or groups) do or do not get placed.';

    public function handle(SchedulingService $scheduler): int
    {
        $arg = $this->argument('tournament');
        $tournament = is_numeric($arg)
            ? Tournament::find((int) $arg)
            : Tournament::where('slug', $arg)->first();

        if (! $tournament) {
            $this->error("Tournament not found: {$arg}");
            return self::FAILURE;
        }

        $courts = $tournament->courts()->with('availabilities')->get();
        if ($courts->isEmpty()) {
            $this->error('No courts/availability configured.');
            return self::FAILURE;
        }
        $courtName = $courts->pluck('name', 'id');

        $duration = (int) ($tournament->match_duration_minutes ?: 75);
        $limitPerDay = ! $this->option('limit-off');
        $maxPerDay = $tournament->maxMatchesPerDay();

        $this->info("Tournament: {$tournament->name} (#{$tournament->id})");
        $this->line("  duración {$duration} min · descanso " . (int) ($tournament->min_rest_minutes ?? 30)
            . " min · máx {$maxPerDay}/día" . ($limitPerDay ? '' : ' (ignorado)') . " · canchas {$courts->count()}");
        $this->phaseWindowSummary($tournament);

        // ---- the real engine, dry-run + trace ----
        $trace = [];
        $result = $scheduler->autoSchedule(
            $tournament, $courts, $duration, $duration,
            $limitPerDay, $maxPerDay,
            null, null, null, false,
            true,            // dryRun
            $trace,          // trace (by ref)
        );

        $this->newLine();
        $this->info("Resultado (simulado, no se guardó nada):");
        $this->line("  programados {$result['scheduled']}   ·   en espera {$result['waiting']}   ·   sin lugar "
            . ($result['unplaced'] - $result['waiting']));

        // ---- per-phase table ----
        $rows = [];
        foreach (SchedulePhase::keys() as $ph) {
            $b = $result['by_phase'][$ph] ?? null;
            if (! $b) continue;
            $rows[] = [
                SchedulePhase::label($ph),
                $b['scheduled'] ?? 0,
                $b['waiting'] ?? 0,
                ($b['unplaced'] ?? 0) - ($b['waiting'] ?? 0),
            ];
        }
        $this->table(['Fase', 'Programados', 'En espera (deps)', 'Sin lugar'], $rows);

        // ---- per-category group/bracket completion ----
        $byCat = collect($trace)->groupBy('category');
        $catRows = [];
        foreach ($byCat as $cat => $items) {
            $g = $items->where('kind', 'group');
            $b = $items->where('kind', 'bracket');
            $catRows[] = [
                $cat,
                $g->where('outcome', 'scheduled')->count() . '/' . $g->count(),
                $b->where('outcome', 'scheduled')->count() . '/' . $b->count(),
                $b->where('outcome', 'scheduled')->count() === $b->count() ? 'ok' : '⚠ incompleto',
            ];
        }
        $this->table(['Categoría', 'Grupos', 'Llave (QF/SF/F)', 'Llave'], $catRows);

        // ---- problems: unplaced (hard) first, then waiting ----
        $this->printProblem('SIN LUGAR (no cupo — revisa ventana / disponibilidad):',
            collect($trace)->where('outcome', 'unplaced'));
        $this->printProblem('EN ESPERA (la llave no se programa hasta que sus grupos estén completos):',
            collect($trace)->where('outcome', 'waiting'));

        // ---- availability pre-flight vs the Grupos window ----
        $this->availabilityPreflight($tournament, $duration);

        if ($this->option('all')) {
            $this->newLine();
            $this->line('— Programados —');
            foreach (collect($trace)->where('outcome', 'scheduled') as $t) {
                $when = $t['start_ts'] ? Carbon::createFromTimestamp($t['start_ts'], 'America/Mexico_City')->format('D d M H:i') : '?';
                $this->line("  {$t['label']}  →  {$courtName[$t['court_id']]} · {$when}");
            }
        }

        return self::SUCCESS;
    }

    private function phaseWindowSummary(Tournament $tournament): void
    {
        if ($tournament->phaseWindows->isEmpty()) {
            $this->warn('  Sin ventanas de fase — se programa en cualquier horario disponible.');
            return;
        }
        $this->line('  Ventanas de fase:');
        foreach ($tournament->phaseWindows->sortBy('starts_at') as $pw) {
            $s = $pw->starts_at->timezone('America/Mexico_City')->format('D d M H:i');
            $e = $pw->ends_at->timezone('America/Mexico_City')->format('D d M H:i');
            $this->line('    ' . str_pad(SchedulePhase::label($pw->phase), 18) . " {$s}  →  {$e}");
        }
    }

    private function printProblem(string $title, \Illuminate\Support\Collection $items): void
    {
        if ($items->isEmpty()) return;
        $this->newLine();
        $this->warn($title);
        foreach ($items->groupBy('category') as $cat => $list) {
            $this->line("  {$cat}:");
            foreach ($list as $t) {
                $this->line("    • {$t['label']} — {$t['reason']}");
            }
        }
    }

    /**
     * Flag players whose availability window cannot fit one match inside the
     * Grupos phase window on ANY day it spans (heuristic: ignores court
     * contention and grid alignment — a hard "no cabe", not a congestion guess).
     */
    private function availabilityPreflight(Tournament $tournament, int $duration): void
    {
        $gw = $tournament->phaseWindows->firstWhere('phase', 'groups');
        if (! $gw) return;

        $map = PlayerAvailability::windowsFor($tournament); // norm => day => [from/until/off]
        if (empty($map)) return;

        $durSec = $duration * 60;
        // Days the Grupos window spans, with each day's open/close (day hours).
        $days = [];
        $cursor = $gw->starts_at->copy()->timezone('America/Mexico_City')->startOfDay();
        $last = $gw->ends_at->copy()->timezone('America/Mexico_City')->startOfDay();
        while ($cursor->lte($last)) {
            $key = $cursor->format('Y-m-d');
            [$dStart, $dEnd] = $tournament->hoursForDay($cursor);
            $open = Carbon::parse("$key $dStart", 'America/Mexico_City')->timestamp;
            $close = Carbon::parse("$key $dEnd", 'America/Mexico_City')->timestamp;
            $days[$key] = [
                'open' => max($open, $gw->starts_at->timestamp),
                'close' => min($close, $gw->ends_at->timestamp),
            ];
            $cursor->addDay();
        }

        $impossible = [];
        foreach ($map as $norm => $perDay) {
            $feasibleSomeDay = false;
            foreach ($days as $key => $bounds) {
                $rule = $perDay[$key] ?? null;
                if ($rule === null) { $feasibleSomeDay = true; break; } // no rule → free that day
                if (is_array($rule) && ! empty($rule['off'])) continue;  // off → not this day
                $from = is_array($rule) ? ($rule['from'] ?? null) : $rule;
                $until = is_array($rule) ? ($rule['until'] ?? null) : null;
                $earliest = $bounds['open'];
                if ($from !== null) {
                    $earliest = max($earliest, Carbon::parse("$key $from", 'America/Mexico_City')->timestamp);
                }
                $latestEnd = $bounds['close'];
                if ($until !== null) {
                    $latestEnd = min($latestEnd, Carbon::parse("$key $until", 'America/Mexico_City')->timestamp);
                }
                if ($earliest + $durSec <= $latestEnd) { $feasibleSomeDay = true; break; }
            }
            if (! $feasibleSomeDay) $impossible[$norm] = $perDay;
        }

        if (empty($impossible)) return;

        $this->newLine();
        $this->warn('DISPONIBILIDAD IMPOSIBLE — estos jugadores no caben en NINGÚN día de la ventana de Grupos:');
        foreach ($impossible as $norm => $perDay) {
            $rules = [];
            foreach ($perDay as $day => $r) {
                if (is_array($r) && ! empty($r['off'])) { $rules[] = "$day: no disponible"; continue; }
                $from = is_array($r) ? ($r['from'] ?? null) : $r;
                $until = is_array($r) ? ($r['until'] ?? null) : null;
                $rules[] = "$day: " . ($from ? "desde $from" : '') . ($until ? " hasta $until" : '');
            }
            $this->line("  • {$norm} — " . implode(' · ', $rules));
        }
        $this->line('  → Su grupo nunca se completa, así que TODA la llave de esa categoría queda en espera.');
        $this->line('  → Amplía la ventana de Grupos, ajusta su disponibilidad, o programa esos partidos a mano.');
    }
}
