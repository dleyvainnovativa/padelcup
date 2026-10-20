<?php

namespace App\Services\Ranking;

use App\Enums\RankingAchievement;
use App\Models\RankingSystem;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds the ranking-system Excel export:
 *   - Sheet 1 "Resumen": the overall (cross-tournament) leaderboard.
 *   - One sheet per tournament: that tournament's leaderboard, each player's
 *     points broken down by achievement (per-player point contributions).
 *   - Last sheet "Puntos": the system's points schedule (config).
 *
 * Reuses RankingLeaderboard so the numbers match the on-screen tables exactly.
 * Uses A1-style coordinates throughout (stable across PhpSpreadsheet versions).
 */
class RankingExport
{
    public function __construct(private RankingLeaderboard $leaderboard) {}

    public function build(RankingSystem $system): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setTitle($system->name . ' — Ranking')->setCreator('Voleo');

        $achievements = RankingAchievement::cases(); // natural progression order

        // Sheet 1 — overall summary.
        $summary = $ss->getActiveSheet();
        $summary->setTitle('Resumen');
        $this->writeBoard($summary, $this->leaderboard->forSystem($system, true), $achievements, true);

        // One sheet per tournament that fed the ranking.
        $used = ['resumen', 'puntos'];
        foreach ($this->leaderboard->tournaments($system) as $t) {
            $board = $this->leaderboard->forSystem($system, true, (int) $t['id']);
            if ($board->isEmpty()) {
                continue;
            }
            $sheet = $ss->createSheet();
            $sheet->setTitle($this->uniqueTitle($t['name'] ?? ('Torneo ' . $t['id']), $used));
            $this->writeBoard($sheet, $board, $achievements, false);
        }

        // Last sheet — points schedule.
        $cfg = $ss->createSheet();
        $cfg->setTitle('Puntos');
        $this->writeConfig($cfg, $system, $achievements);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    private function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * Write a leaderboard: Pos | Jugador | [Torneos] | Total | <one col per achievement>.
     *
     * @param  \Illuminate\Support\Collection  $board  rows from RankingLeaderboard (with breakdown)
     * @param  array<RankingAchievement>  $achievements
     */
    private function writeBoard(Worksheet $sheet, $board, array $achievements, bool $withTournaments): void
    {
        $headers = ['Pos', 'Jugador'];
        if ($withTournaments) {
            $headers[] = 'Torneos';
        }
        $headers[] = 'Total';
        foreach ($achievements as $a) {
            $headers[] = $a->label();
        }

        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->col($i + 1) . '1', $h);
        }
        $this->styleHeader($sheet, count($headers));

        $r = 2;
        foreach ($board as $row) {
            $breakdown = $row['breakdown'] ?? [];
            $c = 1;
            $sheet->setCellValue($this->col($c++) . $r, $row['rank'] ?? '');
            $sheet->setCellValue($this->col($c++) . $r, $row['name'] ?? '—');
            if ($withTournaments) {
                $sheet->setCellValue($this->col($c++) . $r, (int) ($row['tournaments'] ?? 0));
            }
            $sheet->setCellValue($this->col($c++) . $r, (int) ($row['points'] ?? 0));
            foreach ($achievements as $a) {
                $sheet->setCellValue($this->col($c++) . $r, (int) ($breakdown[$a->value] ?? 0));
            }
            $r++;
        }

        $this->autosize($sheet, count($headers));
        $sheet->freezePane('A2');
    }

    /** Write the points schedule: Logro | Puntos, plus the stacking mode. */
    private function writeConfig(Worksheet $sheet, RankingSystem $system, array $achievements): void
    {
        $sheet->setCellValue('A1', 'Logro');
        $sheet->setCellValue('B1', 'Puntos');
        $this->styleHeader($sheet, 2);

        $r = 2;
        $schedule = $system->schedule(); // [achievement_value => points]
        foreach ($achievements as $a) {
            $sheet->setCellValue('A' . $r, $a->label());
            $sheet->setCellValue('B' . $r, (int) ($schedule[$a->value] ?? 0));
            $r++;
        }

        $r++;
        $sheet->setCellValue('A' . $r, 'Acumulación');
        $sheet->setCellValue('B' . $r, $system->isCumulative() ? 'Acumulativa' : 'Mejor resultado');

        $this->autosize($sheet, 2);
    }

    private function styleHeader(Worksheet $sheet, int $cols): void
    {
        $range = 'A1:' . $this->col($cols) . '1';
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0E3B2E'); // Voleo forest green
    }

    private function autosize(Worksheet $sheet, int $cols): void
    {
        for ($c = 1; $c <= $cols; $c++) {
            $sheet->getColumnDimension($this->col($c))->setAutoSize(true);
        }
    }

    /**
     * A valid, unique worksheet title: strip chars Excel forbids, trim to 31,
     * and disambiguate collisions (compared case-insensitively).
     */
    private function uniqueTitle(string $name, array &$used): string
    {
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name);
        $clean = trim(preg_replace('/\s+/', ' ', (string) $clean)) ?: 'Torneo';
        $base = mb_substr($clean, 0, 31);

        $title = $base;
        $i = 2;
        while (in_array(mb_strtolower($title), $used, true)) {
            $suffix = ' (' . $i . ')';
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $i++;
        }
        $used[] = mb_strtolower($title);

        return $title;
    }
}
