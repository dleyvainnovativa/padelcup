#!/usr/bin/env python3
"""
Verifier for voleo-autocat-phases.zip — phase picker + player-rules override +
bracket dependency-ordering safeguard on the per-category auto-scheduler.

Part A: logic mirror of the dependency-pending rule and the override effects.
Part B: structural — service signature/flags, controller validation/wiring,
        UI panel (phase checkboxes + override toggle), CSS.

Run from project root: python3 docs/verify_autocat_phases.py
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: dependency-pending + override mirrors -------------------------
# Mirror of SchedulingService::bracketDependencyPending.
def pending(group_id, feeders, end_of, seed_labels, letters, group_open):
    if group_id:
        return False
    for fid in feeders:
        if fid and fid not in end_of:
            return True
    for label in seed_labels:
        if not label or label == 'BYE':
            continue
        gids = []
        if re.fullmatch(r'Q\d+', label):
            gids = list(letters.values())
        else:
            m = re.fullmatch(r'([A-Z])\d+', label)
            if m and m.group(1) in letters:
                gids = [letters[m.group(1)]]
        for gid in gids:
            if group_open.get(gid, 0) > 0:
                return True
    return False

LET = {'A': 10, 'B': 11}

check("A: group match never pends", pending(5, [], {}, [], LET, {}) is False)
check("A: feeder not yet ended → pends",
      pending(None, [99], {}, [None, None], LET, {}) is True)
check("A: feeder ended → does not pend on feeder",
      pending(None, [99], {99: 1000}, [None, None], LET, {}) is False)
check("A: first round waits on an open source group (A1)",
      pending(None, [None, None], {}, ['A1', 'A2'], LET, {10: 3}) is True)
check("A: first round ok once its group is fully scheduled",
      pending(None, [None, None], {}, ['A1', 'A2'], LET, {10: 0, 11: 0}) is False)
check("A: Q-label waits on ANY open group",
      pending(None, [None, None], {}, ['Q1', 'Q2'], LET, {10: 0, 11: 2}) is True)
check("A: BYE label imposes no dependency",
      pending(None, [None, None], {}, ['A1', 'BYE'], LET, {10: 0}) is False)
check("A: elimination R1 with real pairs (no feeders/labels) never pends",
      pending(None, [None, None], {}, [None, None], LET, {}) is False)

# Override effects (mirror): rest→0, limit off, availability map emptied.
def override_effects(override, rest_default=30, limit_default=True):
    rest = 0 if override else rest_default
    limit = (not override) and limit_default
    avail_empty = override
    return rest, limit, avail_empty
check("A: override collapses rest gap to 0", override_effects(True)[0] == 0)
check("A: override disables per-day limit", override_effects(True)[1] is False)
check("A: override empties availability map", override_effects(True)[2] is True)
check("A: no override keeps rest + limit", override_effects(False) == (30, True, False))

# ---- Part B: structural ---------------------------------------------------
svc = read("app/Services/Tournament/SchedulingService.php")
check("B: autoSchedule takes onlyPhases", "?array $onlyPhases = null" in svc)
check("B: autoSchedule takes overridePlayerRules", "bool $overridePlayerRules = false" in svc)
check("B: override zeroes rest gap", "$restSec = $overridePlayerRules ? 0 :" in svc)
check("B: override disables limitPerDay", "$limitPerDay = false;" in svc)
check("B: override empties availability map",
      "$availabilityMap = $overridePlayerRules ? [] :" in svc)
check("B: groupOpen built from unscheduled group matches",
      "$groupOpen = [];" in svc and "whereNotNull('group_id')->whereNull('starts_at')" in svc)
check("B: phase filter applied to match set",
      "if ($onlyPhases !== null) {" in svc and "in_array($m->phaseKey(), $allowedPhases, true)" in svc)
check("B: dependency pending guard in placement loop",
      "if ($this->bracketDependencyPending($match, $endOf, $lettersByCat, $groupOpen)) {" in svc)
check("B: waiting counter + continue", "$waitingDeps++;" in svc and "'waiting' => $waitingDeps," in svc)
check("B: groupOpen decremented on placed group match",
      "$groupOpen[$match->group_id] = max(0, $groupOpen[$match->group_id] - 1);" in svc)
check("B: helper method defined",
      "private function bracketDependencyPending(" in svc)
check("B: helper checks feeders then group seed labels",
      "$match->feeder_a_id" in svc and "preg_match('/^Q\\d+$/', $label)" in svc)

ctrl = read("app/Http/Controllers/Dashboard/ScheduleController.php")
check("B: controller validates phases", "'phases'           => ['sometimes', 'array']," in ctrl)
check("B: controller validates override_players",
      "'override_players' => ['sometimes', 'boolean']," in ctrl)
check("B: controller intersects phases with canonical keys",
      "array_intersect((array) $data['phases'], \\App\\Support\\SchedulePhase::keys())" in ctrl)
check("B: controller rejects empty phase selection",
      "Selecciona al menos una fase." in ctrl)
check("B: controller passes phases + override to engine",
      "(int) $category->id, $ranges, $phases, $overridePlayers," in ctrl)
check("B: controller reports waiting matches", "a que se programe una fase previa." in ctrl)

view = read("resources/views/dashboard/schedule/index.blade.php")
check("B: view builds per-category phase map", "$autoCatPhases[(string) $cid] = \\App\\Support\\SchedulePhase::presentIn($ms);" in view)
check("B: panel carries catPhases + phases state",
      "catPhases: @js($autoCatPhases)" in view and "phases: []," in view)
check("B: panel carries overridePlayers state", "overridePlayers: false," in view)
check("B: select triggers onCatChange", '@change="onCatChange()"' in view)
check("B: phase checkboxes rendered", 'x-for="k in availablePhases()"' in view and '@change="togglePhase(k)"' in view)
check("B: override checkbox bound", 'x-model="overridePlayers"' in view and "Ignorar reglas de jugadores" in view)
check("B: submit sends phases + override",
      "phases: this.phases, override_players: this.overridePlayers" in view)
check("B: submit guards empty phase selection",
      "if (this.availablePhases().length && !this.phases.length)" in view)

css = read("voleo-autocat-phases/_theme-append-autocat-phases.css") if (ROOT / "voleo-autocat-phases/_theme-append-autocat-phases.css").exists() else read("resources/css/theme.css")
check("B: phase + override CSS present",
      ".ac-phase" in css and ".ac-override" in css and ".ac-hint" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
