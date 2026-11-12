#!/usr/bin/env python3
"""
Verifier for voleo-import-format.zip — per-category structure format on import
(round-robin / hybrid / elimination) + format-change cleanup on the edit screen.

Part A: logic mirrors — format→enum, which stages each format builds, the
        regenerate clear rule, and the preview elim-match count.
Part B: structural — import service, preview UI + JS, regenerateAll clears,
        CategoryController reconcile.

Run from project root: python3 docs/verify_import_format.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: mirrors ------------------------------------------------------
def resolve_category_format(flag):
    return {'round_robin': 'round_robin', 'elimination': 'elimination'}.get(flag, 'hybrid')
check("A: 'round_robin' → round_robin", resolve_category_format('round_robin') == 'round_robin')
check("A: 'elimination' → elimination", resolve_category_format('elimination') == 'elimination')
check("A: null/unknown → hybrid (historical default)",
      resolve_category_format(None) == 'hybrid' and resolve_category_format('xyz') == 'hybrid')

# Which stages a format builds (mirror of format->hasGroups/hasBracket).
def stages(fmt):
    has_groups = fmt in ('round_robin', 'hybrid')
    has_bracket = fmt in ('elimination', 'hybrid')
    return has_groups, has_bracket
check("A: round_robin builds groups only", stages('round_robin') == (True, False))
check("A: elimination builds bracket only", stages('elimination') == (False, True))
check("A: hybrid builds groups + bracket", stages('hybrid') == (True, True))

# regenerate clear rule: a stage the new format lacks must be cleared.
def clears(fmt):
    hg, hb = stages(fmt)
    return {'clears_groups': not hg, 'clears_bracket': not hb}
check("A: switching to elimination clears groups", clears('elimination')['clears_groups'] is True)
check("A: switching to round_robin clears bracket", clears('round_robin')['clears_bracket'] is True)
check("A: hybrid clears nothing", clears('hybrid') == {'clears_groups': False, 'clears_bracket': False})

# Preview elimination match count = nextPow2(pairs) - 1 (direct knockout).
def next_pow2(n):
    p = 2
    while p < n:
        p *= 2
    return p
def elim_matches(pairs):
    return next_pow2(pairs) - 1 if pairs >= 2 else 0
check("A: 8 pairs → 7 elimination matches", elim_matches(8) == 7)
check("A: 6 pairs → 7 (bracket of 8) matches", elim_matches(6) == 7)
check("A: 1 pair → 0 matches", elim_matches(1) == 0)

# ---- Part B: structural ---------------------------------------------------
svc = read("app/Services/Registration/TournamentImportService.php")
check("B: resolveCategoryFormat helper", "private function resolveCategoryFormat(" in svc)
check("B: settingsFor exposes category_format", "'category_format' => $this->resolveCategoryFormat(" in svc)
check("B: new category uses chosen format",
      "'format' => $cfg['category_format'] ?? CategoryFormat::Hybrid," in svc)
check("B: commit generation is format-aware (no hardcoded Hybrid branch)",
      "$cat->format->hasGroups()" in svc and "$cat->format->hasBracket()" in svc
      and "if ($category->format === CategoryFormat::Hybrid)" not in svc)
check("B: preview row carries existing category_format",
      "'category_format' => $existingCat?->format?->value," in svc)
# Regression: a disabled group control (elimination) no longer posts 'size', so
# settingsFor must not read $found['size'] without a default in the true branch.
check("B: settingsFor size is null-safe in BOTH branches (no undefined-key crash)",
      "? (int) ($found['size'] ?? 3) : 3," in svc and "? (int) $found['size'] :" not in svc)

view = read("resources/views/dashboard/tournaments/import-preview.blade.php")
check("B: Formato column header added", "<th>Formato</th>" in view and "<th>Formato grupo</th>" in view)
check("B: category_format select posts", "settings[{{ $row['category'] }}][category_format]" in view)
check("B: existing format shown read-only", "$cfLocked" in view and "disabled" in view)
check("B: tfoot colspan bumped to 8", 'colspan="8"' in view)
check("B: JS reads catformat", 'row.querySelector(\'[data-f="catformat"]\')' in view)
check("B: JS nextPow2 for elimination", "function nextPow2(n)" in view and "nextPow2(pairs) - 1" in view)
check("B: JS gates groups/bracket by format",
      "const hasGroups = catFmt !== 'elimination';" in view and "const hasBracket = catFmt !== 'round_robin';" in view)

regen = read("app/Services/Tournament/StructureRegenerationService.php")
check("B: regenerateAll clears orphaned groups when no groups",
      "GameMatch::where('category_id', $category->id)->whereNotNull('group_id')->delete();" in regen
      and "Group::where('category_id', $category->id)->delete();" in regen)
check("B: regenerateAll clears orphaned bracket when no bracket",
      "GameMatch::where('category_id', $category->id)->whereNull('group_id')->delete();" in regen)
check("B: Group imported", "use App\\Models\\Group;" in regen)

ctrl = read("app/Http/Controllers/Dashboard/CategoryController.php")
check("B: update injects StructureRegenerationService",
      "StructureRegenerationService $structureRegen" in ctrl)
check("B: captures old format before update", "$oldFormat = $category->format?->value;" in ctrl)
check("B: reconciles on format change",
      "if ($category->format?->value !== $oldFormat) {" in ctrl and "$structureRegen->regenerateAll(" in ctrl)
check("B: results path points to Regenerar, not silent destroy",
      "=== 'has_results'" in ctrl and "usa «Regenerar»" in ctrl)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
