<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export of the tournament's champions / finalists (and 3rd place when a
 * real playoff exists), one row per category. Built for the manager's end-of-
 * tournament prize handout, so it also spells out the individual player names.
 *
 * Reads PodiumService so the file matches the on-screen Ganadores table exactly.
 * A1-style coordinates throughout (stable across PhpSpreadsheet versions).
 */
class WinnersExport
{
    public function __construct(private PodiumService $podiums) {}

    public function build(Tournament $tournament): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setTitle($tournament->name . ' — Ganadores')->setCreator('Voleo');

        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Ganadores');

        $headers = ['Categoría', 'Estatus', 'Campeón', 'Jugadores campeón',
                    'Finalista', 'Jugadores finalista', '3er lugar', 'Jugadores 3er lugar', 'Resultado final'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($this->podiums->forTournament($tournament) as $p) {
            if (! $p['has_bracket']) {
                $status = 'Sin llave';
            } elseif ($p['decided']) {
                $status = 'Definido';
            } else {
                $status = 'Pendiente';
            }

            $sheet->fromArray([
                $p['category'],
                $status,
                $p['champion'] ?? '',
                implode(' / ', $p['champion_players'] ?? []),
                $p['runner_up'] ?? '',
                implode(' / ', $p['runner_up_players'] ?? []),
                $p['third'] ?? '',
                implode(' / ', $p['third_players'] ?? []),
                $p['final_score'] ?? '',
            ], null, 'A' . $row);
            $row++;
        }

        $this->style($sheet, $row - 1, count($headers));

        return $ss;
    }

    private function style(Worksheet $sheet, int $lastRow, int $cols): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);

        // Header style.
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F2937');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        for ($c = 1; $c <= $cols; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }
}
