#!/usr/bin/env python3
"""
Structural verifier for voleo-venue-filter.zip (item #2).
Public calendars gain a venue filter (chips only when 2+ venues; default all),
on both the tournament-wide schedule and the per-category calendar.
Run from project root: python3 docs/verify_venue_filter.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

ctrl = read("app/Http/Controllers/PublicTournamentController.php")
# schedule()
check("schedule(): reads venue query", "$venueFilter = $request->query('venue')" in ctrl)
check("schedule(): builds allVenues from court.venue", "$allVenues = $matches" in ctrl and "->map(fn($m) => $m->court?->venue)" in ctrl)
check("schedule(): applies venue filter", "$m->court?->venue?->id === (int) $venueFilter" in ctrl)
check("schedule(): passes allVenues", "'allVenues' => $allVenues" in ctrl)
check("schedule(): passes venueFilter", "'venueFilter' => $venueFilter" in ctrl)
# category()
check("category(): reads venue query", "$calVenue = $request->query('venue')" in ctrl)
check("category(): builds calVenuesPresent", "$calVenuesPresent = $calMatches" in ctrl)
check("category(): applies venue filter", "$m->court?->venue?->id === (int) $calVenue" in ctrl)
check("category(): passes calVenue", "'calVenue' => $calVenue" in ctrl)
check("category(): passes calVenuesPresent", "'calVenuesPresent' => $calVenuesPresent" in ctrl)

sched = read("resources/views/public/schedule.blade.php")
check("schedule.blade: schedUrl closure includes venue", "'venue' => $venueFilter ?: null" in sched)
check("schedule.blade: venue chips guarded by 2+", "$allVenues->count() > 1" in sched)
check("schedule.blade: venue 'Todas' chip", "$schedUrl(['venue' => null])" in sched)
check("schedule.blade: per-venue chip", "$schedUrl(['venue' => $v['id']])" in sched)
check("schedule.blade: venue label Sede", "Sede:" in sched)

cal = read("resources/views/public/partials/category-calendar.blade.php")
check("cal.blade: calUrl closure includes venue", "'venue' => $calVenue ?: null" in cal)
check("cal.blade: venue chips guarded by 2+", "$calVenuesPresent->count() > 1" in cal)
check("cal.blade: venue 'Todas' chip", "$calUrl(['venue' => null])" in cal)
check("cal.blade: per-venue chip", "$calUrl(['venue' => $v['id']])" in cal)
check("cal.blade: venue hidden input", 'name="venue"' in cal)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
