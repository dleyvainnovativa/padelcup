#!/usr/bin/env python3
"""
Verifier for voleo-elim-structure-fix.zip — elimination-only categories no longer
stay permanently "bracket-stale", so the "Reconstruir llave" banner clears after a
rebuild. Plus no-groups wording in the banner and the rebuild flash.

Part A: logic mirror of CategoryStructureState bracket-stale (old vs new).
Part B: structural — service branch, banner wording, controller flash.

Run from project root: python3 docs/verify_elim_structure.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

def next_pow2(n):
    p = 2
    while p < n:
        p *= 2
    return p

# OLD (buggy) and NEW bracket-stale, differing only in the wanted-entrant source.
def stale_old(has_bracket, group_stale, bracket_slots, qualifiers_total):
    if not (has_bracket and not group_stale) or bracket_slots <= 0:
        return False
    return next_pow2(max(2, qualifiers_total)) != bracket_slots

def stale_new(has_bracket, has_groups, group_stale, bracket_slots, pool_pairs, qualifiers_total):
    if not (has_bracket and not group_stale) or bracket_slots <= 0:
        return False
    wanted = qualifiers_total if has_groups else pool_pairs
    return next_pow2(max(2, wanted)) != bracket_slots

# --- The bug: elimination-only, qualifiersTotal(0)=0, rebuilt bracket of 4 slots ---
check("A: OLD formula wrongly flags a rebuilt elim bracket stale",
      stale_old(True, False, 4, 0) is True)   # 2 != 4 → never clears
check("A: NEW formula clears a rebuilt elim bracket (pool=4, slots=4)",
      stale_new(True, False, False, 4, 4, 0) is False)
check("A: NEW elim bracket stale when pool grew but not yet rebuilt (pool=6, slots=4)",
      stale_new(True, False, False, 4, 6, 0) is True)
check("A: NEW elim bracket clears after rebuild to 8 (pool=6, slots=8)",
      stale_new(True, False, False, 8, 6, 0) is False)
check("A: NEW singles elim bracket (pool=4 → SF+F = 4 slots) not stale",
      stale_new(True, False, False, 4, 4, 0) is False)

# --- Hybrid (groups→bracket) behaviour is unchanged ---
check("A: hybrid bracket sized to qualifiers not stale",
      stale_new(True, True, False, 8, 99, 8) is False)
check("A: hybrid bracket wrong size is stale",
      stale_new(True, True, False, 8, 99, 4) is True)
check("A: hybrid new matches old when groups present",
      stale_new(True, True, False, 8, 99, 8) == stale_old(True, False, 8, 8))
check("A: group-stale short-circuits (never bracket-stale)",
      stale_new(True, False, True, 4, 6, 0) is False)

# ---- Part B: structural ---------------------------------------------------
svc = read("app/Services/Tournament/CategoryStructureState.php")
check("B: wanted entrants branches on has-groups",
      "$wantedEntrants = $hasGroups" in svc and "? $category->qualifiersTotal($groupCount)" in svc and ": $poolPairs;" in svc)
check("B: wanted slots from wantedEntrants", "$wantedSlots = $this->nextPow2(max(2, $wantedEntrants));" in svc)
check("B: old qualifiersTotal-only path removed",
      "$wantedQualifiers = $category->qualifiersTotal($groupCount);" not in svc)

banner = read("resources/views/dashboard/categories/partials/structure-banner.blade.php")
check("B: banner reads has_groups", "$hasGroups = $structure['has_groups'];" in banner)
check("B: banner bracketOnly has no-groups title", "Las parejas cambiaron @endif" in banner)
check("B: banner bracketOnly has no-groups text", "ya no coincide con la llave actual" in banner)
check("B: destructive text conditional on groups", "Regenerar {{ $hasGroups ? 'los grupos' : 'la llave' }}" in banner)
check("B: destructive confirm conditional on groups",
      "se regenerará {{ $hasGroups ? 'los grupos'" in banner)

ctrl = read("app/Http/Controllers/Dashboard/CategoryStructureController.php")
check("B: rebuild flash conditional on groups", "$category->format->hasGroups()" in ctrl)
check("B: elim rebuild flash is plain", "'Llave reconstruida.'" in ctrl)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
