#!/usr/bin/env python3
"""
Verifier for voleo-autocat.zip (#2 — per-category auto-schedule into ranges).

Part A: logic mirror of the scoping/window-override rules added to autoSchedule
(category filter, category-range-wins over phase windows, windowed selection).
Part B: structural — params, controller action, route, UI panel, CSS.

Run from project root: python3 docs/verify_autocat.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: scoping + allowed-window resolution mirror -------------------
def scoped(only_cat, ranges):
    return only_cat is not None and bool(ranges)

def allowed_window(phase, scoped_flag, category_ranges, has_phases, phase_windows):
    # Mirror: scoped → category ranges win; else phase window (or None).
    if scoped_flag:
        return category_ranges
    if has_phases:
        return phase_windows.get(phase, [])
    return None

RANGES = [[1000, 2000]]
PW = {'groups': [[500, 900]]}

# Scoped run: category ranges override the phase window.
s = scoped(7, RANGES)
check("A: scoped when category + ranges given", s is True)
check("A: not scoped without ranges", scoped(7, []) is False)
check("A: not scoped without category", scoped(None, RANGES) is False)
check("A: scoped → category range wins over phase window",
      allowed_window('groups', True, RANGES, True, PW) == RANGES)
check("A: unscoped with phases → phase window used",
      allowed_window('groups', False, None, True, PW) == PW['groups'])
check("A: unscoped, no phases → no window (None)",
      allowed_window('groups', False, None, False, {}) is None)

# windowed = has_phases OR scoped (drives placeholder inclusion + match query)
def windowed(has_phases, scoped_flag): return has_phases or scoped_flag
check("A: scoped run is windowed even without phase windows", windowed(False, True) is True)

# Range validity (controller drops end<=start)
def valid_ranges(rs):
    return [r for r in rs if r['end'] > r['start']]
check("A: invalid range (end<=start) dropped",
      valid_ranges([{'start': 100, 'end': 100}, {'start': 100, 'end': 200}]) == [{'start': 100, 'end': 200}])

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")
svc = read("app/Services/Tournament/SchedulingService.php")
check("B: autoSchedule takes onlyCategoryId", "?int $onlyCategoryId = null" in svc)
check("B: autoSchedule takes categoryRanges", "?array $categoryRanges = null" in svc)
check("B: scoped + windowed flags", "$scoped = $onlyCategoryId !== null && ! empty($categoryRanges);" in svc and "$windowed = $hasPhases || $scoped;" in svc)
check("B: query filters category", "$query->where('category_id', $onlyCategoryId);" in svc)
check("B: query uses windowed for placeholders", "if ($windowed) {" in svc)
check("B: allowed = category ranges when scoped", "$allowed = $scoped" in svc and "? $categoryRanges" in svc)

ctrl = read("app/Http/Controllers/Dashboard/ScheduleController.php")
check("B: autoCategory action", "public function autoCategory(" in ctrl)
check("B: validates ranges", "'ranges'          => ['required', 'array', 'min:1']" in ctrl)
check("B: passes scope + ranges to engine", "(int) $category->id, $ranges," in ctrl)
check("B: returns JSON summary", "'ok' => true" in ctrl and "'message' =>" in ctrl)

routes = read("routes/web.php")
check("B: autoCategory route", "schedule.autoCategory" in routes and "auto-categoria" in routes)

view = read("resources/views/dashboard/schedule/index.blade.php")
check("B: toolbar button", "showAutoCat = !showAutoCat" in view)
check("B: panel with category + ranges", 'x-model="cat"' in view and 'x-for="(r, i) in ranges"' in view)
check("B: panel posts to autoCategory", "route('schedule.autoCategory'" in view)
check("B: add/remove range", "addRange()" in view and "removeRange(i)" in view)

css = read("resources/css/_theme-append-autocat.css")
check("B: panel CSS", ".ac-range" in css and ".ac-actions" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
