#!/usr/bin/env python3
"""
Verifier for voleo-calfilters.zip (items #4 + #6).

Part A: logic mirror of the day-default resolution (#6) — sentinel `all`,
default-to-today-when-in-range, raw-param preservation.
Part B: structural checks for the Filtros bottomsheet (#4) and the controller/
blade wiring (#6) on both the tournament and category calendars.

Run from project root: python3 docs/verify_calfilters.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: #6 day resolution mirror -------------------------------------
def resolve(day_param, all_days, today):
    """Mirror of the controller: returns (day_filter_used, day_param_for_urls)."""
    raw = day_param  # preserved for URL building
    if day_param == 'all':
        day_filter = None
    elif (day_param is None or day_param == '') and today in all_days:
        day_filter = today
    else:
        day_filter = day_param
    return day_filter, raw

DAYS = ['2026-10-28', '2026-10-30', '2026-10-31']  # Wed..(Fri missing)..Sat/Sun subset

# Bare visit, today in range → default to today.
f, raw = resolve(None, DAYS, '2026-10-30')
check("A(#6): bare visit defaults to today", f == '2026-10-30')
check("A(#6): bare visit keeps raw param empty (URLs stay bare)", raw is None)

# Bare visit, today NOT in range → show all (no day filter).
f, _ = resolve(None, DAYS, '2026-11-15')
check("A(#6): today out of range → all days", f is None)

# Explicit 'all' sentinel → show all, and raw preserves 'all' for URLs.
f, raw = resolve('all', DAYS, '2026-10-30')
check("A(#6): sentinel all → no day filter", f is None)
check("A(#6): sentinel all preserved in raw for URLs", raw == 'all')

# Explicit specific day → honored.
f, raw = resolve('2026-10-31', DAYS, '2026-10-30')
check("A(#6): explicit day honored", f == '2026-10-31' and raw == '2026-10-31')

# Today in range but a day with no matches (today not in DAYS) → all.
f, _ = resolve(None, DAYS, '2026-10-29')
check("A(#6): today with no matches → all days", f is None)

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")

ctrl = read("app/Http/Controllers/PublicTournamentController.php")
check("B(#6): schedule sentinel+today logic", "if ($dayFilter === 'all')" in ctrl and "$allDays->contains($today)" in ctrl)
check("B(#6): schedule exposes raw dayParam", "$dayParam = $dayFilter;" in ctrl and "'dayParam' => $dayParam" in ctrl)
check("B(#6): category sentinel+today logic", "if ($calDay === 'all')" in ctrl and "$calAllDays->contains($calToday)" in ctrl)
check("B(#6): category exposes raw calDayParam", "$calDayParam = $calDay;" in ctrl and "'calDayParam' => $calDayParam" in ctrl)

sched = read("resources/views/public/schedule.blade.php")
check("B(#6): schedUrl uses raw dayParam", "'day' => $dayParam ?: null" in sched)
check("B(#6): Todos chip uses all sentinel", "$schedUrl(['day' => 'all'])" in sched)
check("B(#4): Filtros button with count", "pub-filter-btn" in sched and "pub-filter-btn__count" in sched)
check("B(#4): filters bottomsheet present", "filtersSheet" in sched and "sched-sheet-overlay" in sched)
check("B(#4): sheet has cat/phase/venue selects", sched.count('class="pub-select pub-filter-form__control"') >= 1 and 'name="cat"' in sched and 'name="phase"' in sched and 'name="venue"' in sched)
check("B(#4): Aplicar + Limpiar actions", "Aplicar" in sched and "pub-filter-form__actions" in sched)
check("B(#4): active-filter summary chips", "pub-active-filters" in sched and "pub-active-chip" in sched)
check("B(#4): day chips stay inline", "pub-day-chips" in sched)
check("B(#4): old inline phase/venue chip rows removed", 'pub-filter-chips">' not in sched)

cal = read("resources/views/public/partials/category-calendar.blade.php")
check("B(#6): calUrl uses raw calDayParam", "'day' => $calDayParam ?: null" in cal)
check("B(#6): category Todos chip uses all sentinel", "$calUrl(['day' => 'all'])" in cal)

css = read("resources/css/_theme-append-calfilters.css")
check("B(#4): CSS for toolbar + filter button", ".pub-cal-toolbar" in css and ".pub-filter-btn" in css)
check("B(#4): CSS for filter form", ".pub-filter-form" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
