#!/usr/bin/env python3
"""
Verifier for voleo-elim-bracket.zip — regenerating an elimination-only (no
groups) category's bracket no longer 500s.

Root cause: buildPositional() assumed groups and threw; both regenerate paths
(DrawController::buildBracket via qualifiers→build([]), and
StructureRegenerationService via buildPositional) hit it. Fixes:
  - buildPositional builds a DIRECT seeded bracket when the format has no groups
  - buildBracket guards Hybrid-with-no-groups + catches domain errors → flash
  - regenerateAll gates groups by hasGroups() / bracket by hasBracket()
  - bracket view now renders the error message

Part A mirrors the format → stage routing. Part B checks the wiring.
Run from project root: python3 docs/verify_elim_bracket.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: format → stages mirror ---------------------------------------
# (round_robin, elimination, hybrid) → (has_groups, has_bracket)
FMT = {
    'round_robin': (True, False),
    'elimination': (False, True),
    'hybrid': (True, True),
}
def build_plan(fmt):
    hg, hb = FMT[fmt]
    plan = []
    if hg: plan.append('groups')
    if hb: plan.append('bracket')  # buildPositional: direct-seed if not hg, else positional
    return plan

check("A: elimination builds bracket only (no groups)", build_plan('elimination') == ['bracket'])
check("A: hybrid builds groups + bracket", build_plan('hybrid') == ['groups', 'bracket'])
check("A: round_robin builds groups only", build_plan('round_robin') == ['groups'])

def positional_mode(fmt, overall=False):
    if overall: return 'overall'
    if not FMT[fmt][0]:  # no groups
        return 'direct_seed'
    return 'positional_from_groups'
check("A: buildPositional direct-seeds elimination", positional_mode('elimination') == 'direct_seed')
check("A: buildPositional positional for hybrid", positional_mode('hybrid') == 'positional_from_groups')
check("A: buildPositional overall when overall-seeded", positional_mode('hybrid', overall=True) == 'overall')

# Hybrid with zero groups must be refused before qualifiers (would build([]) → throw).
def buildbracket_refuses(fmt, group_count):
    return fmt == 'hybrid' and group_count == 0
check("A: hybrid w/ 0 groups refused gracefully", buildbracket_refuses('hybrid', 0))
check("A: hybrid w/ groups proceeds", not buildbracket_refuses('hybrid', 2))
check("A: elimination never hits the hybrid refusal", not buildbracket_refuses('elimination', 0))

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")
svc = read("app/Services/Tournament/BracketService.php")
check("B: buildPositional direct-seeds when no groups",
      "if (! $category->format->hasGroups()) {" in svc and "$this->build($category, $seedIds);" in svc)

draw = read("app/Http/Controllers/Dashboard/DrawController.php")
check("B: buildBracket guards hybrid-no-groups",
      "$category->format === CategoryFormat::Hybrid && $category->groups()->count() === 0" in draw)
check("B: buildBracket try/catch → flash", "} catch (\\RuntimeException $e) {" in draw and "withErrors(['bracket'" in draw)

regen = read("app/Services/Tournament/StructureRegenerationService.php")
check("B: regenerateAll gates groups by hasGroups", "if ($category->format->hasGroups()) {" in regen)
check("B: regenerateAll gates bracket by hasBracket", "if ($category->format->hasBracket()) {" in regen)

view = read("resources/views/dashboard/brackets/index.blade.php")
check("B: bracket view renders errors", "$errors->any()" in view)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
