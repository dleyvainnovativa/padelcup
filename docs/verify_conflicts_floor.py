#!/usr/bin/env python3
"""
Logic mirror for voleo-conflicts-floor (#11). Mirrors:
  - the anchor choice in ScheduleController::conflicts()
  - the day-floor filter in SchedulingService::auditRows()
Run: python3 docs/verify_conflicts_floor.py
"""


def anchor(today, first_day):
    """today/first_day are 'Y-m-d'. Anchor at today, unless the tournament
    hasn't started yet → anchor at its first day so nothing is hidden."""
    if first_day and today < first_day:
        return first_day
    return today


def apply_floor(rows, from_day):
    """rows: list of (id, day|None). Drop SCHEDULED rows before from_day; keep
    unscheduled (day None). from_day None → keep everything."""
    if from_day is None:
        return [r[0] for r in rows]
    kept = []
    for rid, day in rows:
        if day is None or day >= from_day:
            kept.append(rid)
    return kept


checks = []
def check(name, cond): checks.append((name, bool(cond)))

# Anchor: mid-tournament → today.
check('anchor is today during the tournament', anchor('2026-10-16', '2026-10-15') == '2026-10-16')
# Anchor: before the tournament → first day (nothing hidden).
check('anchor is first day before it starts', anchor('2026-10-10', '2026-10-15') == '2026-10-15')
# Anchor: after the tournament → today (shows nothing past; it's over).
check('anchor is today after it ends', anchor('2026-10-20', '2026-10-15') == '2026-10-20')
# No play days yet → today.
check('no first day falls back to today', anchor('2026-10-16', None) == '2026-10-16')

# Floor filter: Thu–Sun tournament, standing on Friday (anchor 10-17).
rows = [
    (1, '2026-10-15'),   # Wed — past, dropped
    (2, '2026-10-16'),   # Thu — past, dropped
    (3, '2026-10-17'),   # Fri — kept
    (4, '2026-10-18'),   # Sat — kept
    (5, None),           # unscheduled — kept
]
kept = apply_floor(rows, '2026-10-17')
check('past-day scheduled matches dropped', 1 not in kept and 2 not in kept)
check('today-and-later scheduled matches kept', 3 in kept and 4 in kept)
check('unscheduled matches always kept', 5 in kept)
check('no floor keeps everything', sorted(apply_floor(rows, None)) == [1, 2, 3, 4, 5])

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
raise SystemExit(0 if passed == len(checks) else 1)
