#!/usr/bin/env python3
"""
Verifier for voleo-format-change-reset.zip — changing a category's format now
clears the dropped stage's matches even on a STARTED category (with results),
behind a confirmation, instead of leaving them orphaned and un-deletable.

Part A: logic mirror of the confirmation gate + force-regenerate decision.
Part B: structural — controller gate, edit-form confirm, dependency present.

Run from project root: python3 docs/verify_format_change_reset.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: gate + regenerate decision -----------------------------------
# Returns ('blocked'|'regenerate'|'noop'); regenerate always runs with force.
def decide(format_changing, has_results, confirmed):
    if not format_changing:
        return "noop"
    if has_results and not confirmed:
        return "blocked"          # ask for confirmation; nothing saved yet
    return "regenerate"           # force: clears dropped stage, rebuilds

check("A: started category, unconfirmed → blocked (not stuck, not destroyed)",
      decide(True, True, False) == "blocked")
check("A: started category, confirmed → regenerate (force)",
      decide(True, True, True) == "regenerate")
check("A: fresh category (no results) → regenerate regardless of confirm",
      decide(True, False, False) == "regenerate")
check("A: no format change → noop", decide(False, True, False) == "noop")

# Which stage gets cleared for the new format (mirror of regenerateAll).
def cleared(fmt):
    has_groups = fmt in ("round_robin", "hybrid")
    has_bracket = fmt in ("elimination", "hybrid")
    return {"groups": not has_groups, "bracket": not has_bracket}
check("A: hybrid→elimination clears the group matches",
      cleared("elimination")["groups"] is True)
check("A: elimination keeps the bracket", cleared("elimination")["bracket"] is False)

# ---- Part B: structural ---------------------------------------------------
ctrl = read("app/Http/Controllers/Dashboard/CategoryController.php")
check("B: detects a format change", "$formatChanging = $request->filled('format') && $request->input('format') !== $oldFormat;" in ctrl)
check("B: checks for confirmed results", "->where('state', \\App\\Enums\\MatchState::Confirmed)->exists();" in ctrl)
check("B: gate BEFORE save when results + unconfirmed",
      "if ($formatChanging && $hasResults && ! $request->boolean('confirm_structure_reset')) {" in ctrl)
check("B: gate returns without saving (no half-switch)",
      "Confirma el cambio de formato" in ctrl and "return back()->withInput()->withErrors([" in ctrl)
check("B: regenerate forced after a confirmed/fresh change",
      "$res = $structureRegen->regenerateAll($category->fresh(), force: true);" in ctrl)
check("B: old 'usa Regenerar' dead-end removed",
      "usa «Regenerar» en Grupos o Llave" not in ctrl)
check("B: reports deleted results count", "resultado(s) borrados)." in ctrl)

edit = read("resources/views/dashboard/categories/edit.blade.php")
check("B: form carries original format + has-results flags",
      'data-orig-format="{{ $category->format->value }}"' in edit and 'data-has-results="{{ $catHasResults ? \'1\' : \'0\' }}"' in edit)
check("B: hidden confirm field present", 'name="confirm_structure_reset"' in edit and 'id="confirmStructureReset"' in edit)
check("B: no native window.confirm (uses the themed modal instead)", "window.confirm" not in edit)
check("B: format error surfaced on the field", "@error('format')" in edit)

# Themed modal confirm (replaces the native confirm).
mod = read("resources/js/modules/categoryFormatConfirm.js")
check("B: module uses the core modal confirm", "import { confirm as confirmModal } from '../core/modal';" in mod)
check("B: module gates on real format change + results",
      "sel.value !== form.dataset.origFormat" in mod and "form.dataset.hasResults !== '1'" in mod)
check("B: module sets the flag + resubmits on OK",
      "flag.value = '1';" in mod and "form.dataset.formatConfirmed = '1';" in mod and "form.submit();" in mod)
check("B: module uses danger variant", "variant: 'danger'," in mod)
appjs = read("resources/js/app.js")
check("B: app.js wires the module on the edit form",
      "getElementById('categoryEditForm')" in appjs and "initCategoryFormatConfirm()" in appjs)

# Dependency: the clearing branches + force param (from voleo-import-format).
regen = read("app/Services/Tournament/StructureRegenerationService.php")
check("B: regenerateAll has force param", "function regenerateAll(Category $category, bool $force = false)" in regen)
check("B: regenerateAll clears dropped stages",
      "whereNotNull('group_id')->delete();" in regen and "whereNull('group_id')->delete();" in regen)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
