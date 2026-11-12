#!/usr/bin/env python3
"""
Verifier for voleo-search-current.zip — topbar search prioritizes the tournament
the manager is currently viewing (?t=<slug>): its categories + players come first
in a `current` block, separated by a divider, and are excluded from the global groups.

Part A: scope-split logic mirror.
Part B: structural — controller, blade context, JS render + fetch, CSS.

Run from project root: python3 docs/verify_search_current.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: scope split --------------------------------------------------
def split(all_tids, current_tid):
    if current_tid is None:
        return {"current_tids": [], "other_tids": list(all_tids)}
    return {
        "current_tids": [current_tid],
        "other_tids": [t for t in all_tids if int(t) != int(current_tid)],
    }
check("A: current tournament pulled out, excluded from others",
      split([1, 2, 3], 2) == {"current_tids": [2], "other_tids": [1, 3]})
check("A: no current → everything is 'other'",
      split([1, 2, 3], None) == {"current_tids": [], "other_tids": [1, 2, 3]})
check("A: current is the only tournament → others empty",
      split([5], 5) == {"current_tids": [5], "other_tids": []})

# A row from the current tournament never also appears in the global list.
def no_dup(current_items, other_items):
    return set(current_items).isdisjoint(set(other_items))
check("A: no row listed in both sections",
      no_dup(["catA", "catB"], ["catC"]) and not no_dup(["catA"], ["catA", "catC"]))

# ---- Part B: structural ---------------------------------------------------
ctrl = read("app/Http/Controllers/Dashboard/DashboardSearchController.php")
check("B: reads current tournament from ?t, scoped to the manager",
      "$request->filled('t')" in ctrl and "$manager->tournaments()->where('slug', $request->query('t'))->first()" in ctrl)
check("B: current block returned", "$out['current'] = [" in ctrl and "$this->categoriesIn([$current->id], $like)" in ctrl)
check("B: others exclude the current tournament",
      "$tids->reject(fn ($id) => (int) $id === (int) $current->id)" in ctrl)
check("B: helper methods extracted", "private function categoriesIn(" in ctrl and "private function playersIn(" in ctrl)
check("B: default current is null", "'current' => null," in ctrl)
check("B: still slug-based URLs", "route('categories.show', [$c->tournament, $c->id])" in ctrl)

view = read("resources/views/layouts/app.blade.php")
check("B: topbar exposes current tournament slug",
      "$searchCtxTournament = request()->route('tournament');" in view
      and "data-current-tournament=" in view)
check("B: slug resolved from a bound model too",
      "$searchCtxTournament instanceof \\App\\Models\\Tournament" in view)

js = read("resources/js/modules/topbarSearch.js")
check("B: JS sends t param", "currentTournament" in js and "&t=${encodeURIComponent(currentTournament)}" in js)
check("B: JS renders current section + divider",
      "tbs__group--current" in js and "'<hr class=\"tbs__sep\">'" in js)
check("B: current rows are compact (no redundant tournament name)",
      "rowHtml('categories', r, true)" in js and "rowHtml('players', r, true)" in js)
check("B: global rows not compact", "rowHtml(g, r, false)" in js)

css = read("resources/css/theme.css")
check("B: divider + current header CSS", ".tbs__sep" in css and ".tbs__group--current" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
