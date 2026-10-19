<?php
/**
 * Logic mirror for voleo-group-phase-filters. The sandbox has no Laravel vendor,
 * so this reproduces GameMatch::phaseKey()/publicPhase() and
 * SchedulePhase::presentIn() exactly and asserts the behaviour.
 * Run: php docs/verify_public_phase.php
 */

// Mirror of App\Support\SchedulePhase::LABELS (chronological order).
const LABELS = [
    'groups' => 'Grupos',
    'r32' => 'Treintaidosavos',
    'r16' => 'Octavos',
    'quarterfinal' => 'Cuartos de final',
    'semifinal' => 'Semifinal',
    'final' => 'Final',
];

function phase_keys(): array { return array_keys(LABELS); }
function phase_label(string $k): string { return LABELS[$k] ?? $k; }

// Mirror of GameMatch::phaseKey().
function phase_key(?int $groupId, ?int $round, int $totalRounds): string {
    if ($groupId) return 'groups';
    if ($totalRounds < 1 || !$round) return 'groups';
    $distance = $totalRounds - $round;
    return match ($distance) {
        0 => 'final',
        1 => 'semifinal',
        2 => 'quarterfinal',
        3 => 'r16',
        4 => 'r32',
        default => 'r32',
    };
}

// Mirror of GameMatch::publicPhase().
function public_phase(?int $groupId, ?int $round, int $totalRounds): array {
    $key = phase_key($groupId, $round, $totalRounds);
    $order = phase_keys();
    $idx = array_search($key, $order, true);
    return ['key' => $key, 'sortIndex' => $idx === false ? 99 : $idx, 'label' => phase_label($key)];
}

// Mirror of SchedulePhase::presentIn() (preserve canonical order).
function present_in(array $matchKeys): array {
    $present = array_unique($matchKeys);
    return array_values(array_filter(phase_keys(), fn($k) => in_array($k, $present, true)));
}

$checks = [];
function check(string $name, bool $cond): void { global $checks; $checks[] = [$name, $cond]; }

// A 3-round bracket: rounds 1=QF, 2=SF, 3=Final.
check('group match → groups', public_phase(5, 1, 3)['key'] === 'groups');
check('final (distance 0)', public_phase(null, 3, 3)['key'] === 'final');
check('semifinal (distance 1)', public_phase(null, 2, 3)['key'] === 'semifinal');
check('quarterfinal (distance 2)', public_phase(null, 1, 3)['key'] === 'quarterfinal');

// sortIndex orders chronologically: groups < quarterfinal < semifinal < final.
$g = public_phase(5, 1, 3)['sortIndex'];
$qf = public_phase(null, 1, 3)['sortIndex'];
$sf = public_phase(null, 2, 3)['sortIndex'];
$f = public_phase(null, 3, 3)['sortIndex'];
check('sortIndex chronological', $g < $qf && $qf < $sf && $sf < $f);

// Labels.
check('final label is Spanish', public_phase(null, 3, 3)['label'] === 'Final');

// presentIn keeps canonical order regardless of input order.
$present = present_in(['final', 'groups', 'semifinal', 'quarterfinal']);
check('presentIn canonical order', $present === ['groups', 'quarterfinal', 'semifinal', 'final']);

// Filtering a match set by a phase key keeps only that phase (mirrors the
// controller filter: $m->publicPhase()['key'] === $calPhase).
$matches = [
    ['g' => 5, 'r' => 1, 't' => 3],   // groups
    ['g' => null, 'r' => 1, 't' => 3], // QF
    ['g' => null, 'r' => 3, 't' => 3], // Final
];
$onlyFinal = array_values(array_filter($matches, fn($m) => public_phase($m['g'], $m['r'], $m['t'])['key'] === 'final'));
check('phase filter keeps only matches of that phase', count($onlyFinal) === 1 && $onlyFinal[0]['r'] === 3);

$passed = 0;
foreach ($checks as [$name, $ok]) {
    echo ($ok ? '  OK  ' : ' FAIL ') . $name . PHP_EOL;
    if ($ok) $passed++;
}
$total = count($checks);
echo PHP_EOL . "{$passed}/{$total} checks passed" . PHP_EOL;
exit($passed === $total ? 0 : 1);
