#!/usr/bin/env python3
"""
Structural verifier for voleo-calfixes.zip (3 calendar interaction fixes):
  #1 close control sheet on manual set-time
  #2 "Validar horarios" re-fetches live on every open (no stale cache)
  #3 "Revisar conflictos" → live bottom-sheet accordion, fetched fresh each open
Run from project root: python3 docs/verify_calfixes.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# #1 set-time closes the sheet
sched = read("resources/js/modules/schedule.js")
check("#1 set-time success closes sheet", "if (res === true) { hide(); return; }" in sched)

# #2 validar re-fetches every open (cache guard removed)
val = read("resources/js/modules/scheduleValidation.js")
check("#2 cache guard removed", "if (data === null)" not in val)
check("#2 fetch inside click handler (always)", "Always re-fetch on open" in val and "no-store" in val)

# #3 conflicts: controller fragment endpoint
ctrl = read("app/Http/Controllers/Dashboard/ScheduleController.php")
check("#3 conflictsData action", "public function conflictsData(" in ctrl)
check("#3 renders audit-report fragment", "view('dashboard.schedule.partials.audit-report'" in ctrl and "->render()" in ctrl)

routes = read("routes/web.php")
check("#3 GET conflicts-data route", "schedule.conflicts.data" in routes and "'conflictsData'" in routes)

idx = read("resources/views/dashboard/schedule/index.blade.php")
check("#3 button is a live trigger (not POST form)", "data-conflicts-url" in idx)
check("#3 old server include removed", "@include('dashboard.schedule.partials.audit-report')" not in idx)

part = read("resources/views/dashboard/schedule/partials/audit-report.blade.php")
check("#3 partial accepts passed data w/ session fallback", "$conflicts = $conflicts ?? session('conflicts'" in part)
check("#3 three report sections are accordions", part.count('class="audit-acc"') == 3)
check("#3 auto-open order on errors", '<details class="audit-acc" {{ $orderErrors ? \'open\' : \'\' }}>' in part)

mod = read("resources/js/modules/scheduleConflicts.js")
check("#3 module fetches fragment each open", "cache: 'no-store'" in mod and "await res.text()" in mod)

app = read("resources/js/app.js")
check("#3 app.js wires scheduleConflicts", "data-conflicts-url" in app and "initScheduleConflicts" in app)

css = read("resources/css/_theme-append-conflicts.css")
check("#3 accordion CSS", ".audit-acc" in css and ".audit-acc__chev" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
