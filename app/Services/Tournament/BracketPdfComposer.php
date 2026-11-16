<?php

namespace App\Services\Tournament;

use Illuminate\Support\Collection;

/**
 * Pure geometry for a single-elimination bracket PDF — no framework deps, so it
 * can be unit-mirrored. Given the bracket matches (feeder links decide the tree),
 * returns absolute box positions + connector segments + the page size that fits
 * the whole bracket on ONE page (points). Two layouts:
 *
 *   'linear' — rounds left→right, champion on the right (image 2).
 *   'mirror' — the draw splits and converges to a centre final (image 1), which
 *              halves the row count so a round-of-64 fits readably.
 *
 * The blade consumes boxes[matchId] + the match objects (for names), and draws
 * each lines[] entry as a 1pt-thick rect.
 */
class BracketPdfComposer
{
    // Geometry (points). A box holds two stacked sides.
    public const BOX_W = 150;
    public const ROW_H = 34;          // full match box height
    public const ROW_GAP = 44;        // vertical pitch between round-1 matches
    private const COL_GAP = 28;
    private const MARGIN = 24;
    public const HEADER_H = 74;       // logo + title band

    private int $colW;

    public function __construct()
    {
        $this->colW = self::BOX_W + self::COL_GAP;
    }

    /** @param Collection<int,\App\Models\GameMatch> $matches bracket matches (group_id null, not third-place) */
    public function compose(Collection $matches, string $layout = 'linear'): array
    {
        $matches = $matches->filter(fn($m) => ! $m->is_third_place)->values();
        if ($matches->isEmpty()) {
            return ['width' => 400, 'height' => 200, 'boxes' => [], 'lines' => [], 'rounds' => [],
                'boxW' => self::BOX_W, 'rowH' => self::ROW_H, 'headerH' => self::HEADER_H];
        }

        $byId = $matches->keyBy('id');
        $maxRound = (int) $matches->max('round');
        $root = $matches->firstWhere('round', $maxRound);
        $children = fn($m) => collect([$m->feeder_a_id, $m->feeder_b_id])
            ->filter()->map(fn($id) => $byId->get($id))->filter()->values();

        return $layout === 'mirror'
            ? $this->mirror($matches, $root, $children, $maxRound)
            : $this->linear($matches, $root, $children, $maxRound);
    }

    /** In-order DFS: leaves get sequential slots; a node sits at the mean of its children. */
    private function assignY(object $start, callable $children): array
    {
        $y = [];
        $leaf = 0;
        $walk = function ($m) use (&$walk, &$y, &$leaf, $children) {
            $ch = $children($m);
            if ($ch->isEmpty()) {
                $y[$m->id] = $leaf + 0.5;
                $leaf++;
                return $y[$m->id];
            }
            $ys = $ch->map($walk);
            $y[$m->id] = $ys->avg();
            return $y[$m->id];
        };
        $walk($start);
        return [$y, $leaf];
    }

    private function linear(Collection $matches, object $root, callable $children, int $R): array
    {
        [$y, $nLeaf] = $this->assignY($root, $children);

        $width = self::MARGIN * 2 + $R * $this->colW + 46;            // + champion stub
        $height = self::MARGIN + self::HEADER_H + $nLeaf * self::ROW_GAP + self::MARGIN;

        $boxes = [];
        foreach ($matches as $m) {
            $boxes[$m->id] = [
                'x' => self::MARGIN + ($m->round - 1) * $this->colW,
                'y' => self::MARGIN + self::HEADER_H + ($y[$m->id] - 0.5) * self::ROW_GAP,
                'side' => 'L',
            ];
        }
        return $this->finish($matches, $children, $boxes, $width, $height, $R);
    }

    private function mirror(Collection $matches, object $root, callable $children, int $R): array
    {
        $kids = $children($root);
        $side = [$root->id => 'C'];
        $mark = function ($m, $s) use (&$mark, $children, &$side) {
            $side[$m->id] = $s;
            foreach ($children($m) as $c) $mark($c, $s);
        };
        if ($kids->count() > 0) $mark($kids[0], 'L');
        if ($kids->count() > 1) $mark($kids[1], 'R');

        [$yL, $nL] = $kids->count() > 0 ? $this->assignY($kids[0], $children) : [[], 0];
        [$yR, $nR] = $kids->count() > 1 ? $this->assignY($kids[1], $children) : [[], 0];

        $wing = ($R - 1) * $this->colW;
        $width = self::MARGIN * 2 + 2 * $wing + self::BOX_W + 40;     // two wings + centre final
        $height = self::MARGIN + self::HEADER_H + max($nL, $nR) * self::ROW_GAP + self::MARGIN;
        $midY = self::MARGIN + self::HEADER_H + (max($nL, $nR) / 2) * self::ROW_GAP - self::ROW_H / 2;

        $boxes = [];
        foreach ($matches as $m) {
            $s = $side[$m->id] ?? 'L';
            if ($s === 'L') {
                $x = self::MARGIN + ($m->round - 1) * $this->colW;
                $yy = self::MARGIN + self::HEADER_H + (($yL[$m->id] ?? 0) - 0.5) * self::ROW_GAP;
            } elseif ($s === 'R') {
                $x = $width - self::MARGIN - self::BOX_W - ($m->round - 1) * $this->colW;
                $yy = self::MARGIN + self::HEADER_H + (($yR[$m->id] ?? 0) - 0.5) * self::ROW_GAP;
            } else { // centre final
                $x = ($width - self::BOX_W) / 2;
                $yy = $midY;
            }
            $boxes[$m->id] = ['x' => $x, 'y' => $yy, 'side' => $s];
        }
        return $this->finish($matches, $children, $boxes, $width, $height, $R);
    }

    /** Add connector segments between each match and its feeders. */
    private function finish(Collection $matches, callable $children, array $boxes, float $w, float $h, int $R): array
    {
        $lines = [];
        foreach ($matches as $m) {
            $pb = $boxes[$m->id];
            foreach ($children($m) as $c) {
                $cb = $boxes[$c->id] ?? null;
                if (! $cb) continue;
                $cyMid = $cb['y'] + self::ROW_H / 2;
                $pyMid = $pb['y'] + self::ROW_H / 2;
                if (($pb['side'] ?? 'L') === 'R') {
                    // right wing: lines run leftward (child is to the right of parent)
                    $x0 = $cb['x'];
                    $x1 = $pb['x'] + self::BOX_W;
                } else {
                    $x0 = $cb['x'] + self::BOX_W;
                    $x1 = $pb['x'];
                }
                $mx = ($x0 + $x1) / 2;
                $lines[] = $this->seg($x0, $cyMid, $mx, $cyMid);   // child → mid (horizontal)
                $lines[] = $this->seg($mx, $cyMid, $mx, $pyMid);   // mid vertical
                $lines[] = $this->seg($mx, $pyMid, $x1, $pyMid);   // mid → parent (horizontal)
            }
        }
        return [
            'width' => round($w), 'height' => round($h),
            'boxes' => $boxes, 'lines' => $lines, 'rounds' => $R,
            'boxW' => self::BOX_W, 'rowH' => self::ROW_H, 'headerH' => self::HEADER_H,
        ];
    }

    /** A thin rect for one connector segment (normalized so w/h ≥ 1pt). */
    private function seg(float $x0, float $y0, float $x1, float $y1): array
    {
        $x = min($x0, $x1);
        $y = min($y0, $y1);
        return ['x' => round($x, 1), 'y' => round($y, 1),
                'w' => max(1, round(abs($x1 - $x0), 1)), 'h' => max(1, round(abs($y1 - $y0), 1))];
    }
}
