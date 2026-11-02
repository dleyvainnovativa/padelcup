#!/usr/bin/env python3
"""
Structural verifier for voleo-fixes2.zip (items #1, #2, #3, #5).
All view/CSS changes — verified structurally (sandbox can't render Blade).
Run from project root: python3 docs/verify_fixes2.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# #1 ranking config table uses the themed tc-table (not Bootstrap .table)
show = read("resources/views/ranking-systems/show.blade.php")
check("#1 config table uses tc-table", 'class="tc-table rk-cfg__points-table"' in show)
check("#1 config table wrapped in tc-table-wrap", show.count("tc-table-wrap") >= 2)
check("#1 old bootstrap .table removed from config", 'class="table rk-cfg__points-table"' not in show)

# #2 hide retraso when ended
ctrl = read("resources/views/dashboard/control/index.blade.php")
check("#2 $ended computed from ends_on", "$ended = $tournament->ends_on && $tournament->ends_on->lt(" in ctrl)
check("#2 header badge gated by !$ended", "@if(! $ended && $delay !== null" in ctrl)
check("#2 per-row delay gated by !$ended", "@if(! $ended && $delayM !== null" in ctrl)

# #3 icon-only tabs
css = read("resources/css/_theme-append-fixes2.css")
check("#3 label hidden globally", ".pl-tab__label { display: none; }" in css)
check("#3 tabs nowrap", ".pl-sheet__tabs { flex-wrap: nowrap; }" in css)

# #5 venue line in court header
sched = read("resources/views/dashboard/schedule/index.blade.php")
check("#5 multiVenue computed", "$multiVenue = $courts->pluck('venue')->filter()->unique('id')->count() > 1" in sched)
check("#5 venue span rendered when multi", '$multiVenue && $court->venue' in sched and 'class="sched-grid__venue"' in sched)
check("#5 CSS for venue line", ".sched-grid__venue" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
