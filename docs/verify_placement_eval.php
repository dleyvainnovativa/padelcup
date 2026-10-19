<?php
/**
 * Verifier for voleo-live-warnings. PlacementEvaluator is pure (no framework),
 * so this loads the REAL class and exercises evaluate() directly.
 * Run: php docs/verify_placement_eval.php
 */

require __DIR__ . '/../app/Services/Tournament/PlacementEvaluator.php';

use App\Services\Tournament\PlacementEvaluator;

$checks = [];
function check(string $name, bool $cond): void { global $checks; $checks[] = [$name, $cond]; }
function has(array $w, string $needle): bool {
    foreach ($w as $line) if (str_contains($line, $needle)) return true;
    return false;
}

// 1) Feeder not scheduled → warn.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'feeders' => [['label' => 'Ganador (A / B)', 'scheduled' => false, 'endsTs' => null]],
]);
check('feeder unscheduled warns', has($w, 'aún sin programar'));

// 2) Feeder scheduled but ends AFTER this start → warn "empieza antes".
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'feeders' => [['label' => 'Ganador', 'scheduled' => true, 'endsTs' => 2000]],
]);
check('feeder ends after start warns', has($w, 'antes de que termine el partido previo'));

// 3) Feeder scheduled and ends BEFORE this start → no feeder warning.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 3000,
    'feeders' => [['label' => 'Ganador', 'scheduled' => true, 'endsTs' => 2000]],
]);
check('feeder ends before start → clean', count($w) === 0);

// 4) Source group with unscheduled matches → warn.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'sourceGroups' => [['name' => 'Grupo A', 'endsTs' => null, 'unscheduled' => 2]],
]);
check('group unscheduled warns', has($w, 'Grupo A') && has($w, 'sin programar'));

// 5) Source group finishes AFTER this start → warn.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'sourceGroups' => [['name' => 'Grupo B', 'endsTs' => 2000, 'unscheduled' => 0]],
]);
check('group ends after start warns', has($w, 'antes de que termine el Grupo B'));

// 6) Source group fully scheduled and finished before start → clean.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 5000,
    'sourceGroups' => [['name' => 'Grupo B', 'endsTs' => 2000, 'unscheduled' => 0]],
]);
check('group finished before start → clean', count($w) === 0);

// 7) Per-day load would exceed the limit → warn (count 2, +1 = 3 > max 2).
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'dayLoad' => [['name' => 'Ana', 'count' => 2]],
    'maxPerDay' => 2,
]);
check('over daily limit warns', has($w, 'Ana') && has($w, '3 partidos'));

// 8) Per-day load exactly at the limit → clean (count 1, +1 = 2 == max 2).
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'dayLoad' => [['name' => 'Ana', 'count' => 1]],
    'maxPerDay' => 2,
]);
check('at daily limit → clean', count($w) === 0);

// 9) maxPerDay null → per-day check skipped even with a high count.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'dayLoad' => [['name' => 'Ana', 'count' => 9]],
    'maxPerDay' => null,
]);
check('null maxPerDay skips per-day check', count($w) === 0);

// 10) Duplicate warnings are de-duplicated.
$w = PlacementEvaluator::evaluate([
    'startsTs' => 1000,
    'sourceGroups' => [
        ['name' => 'Grupo A', 'endsTs' => null, 'unscheduled' => 1],
        ['name' => 'Grupo A', 'endsTs' => null, 'unscheduled' => 1],
    ],
]);
check('duplicate warnings deduped', count($w) === 1);

// 11) Clean context → no warnings.
check('empty context → clean', count(PlacementEvaluator::evaluate(['startsTs' => 1000])) === 0);

$passed = 0;
foreach ($checks as [$name, $ok]) {
    echo ($ok ? '  OK  ' : ' FAIL ') . $name . PHP_EOL;
    if ($ok) $passed++;
}
$total = count($checks);
echo PHP_EOL . "{$passed}/{$total} checks passed" . PHP_EOL;
exit($passed === $total ? 0 : 1);
