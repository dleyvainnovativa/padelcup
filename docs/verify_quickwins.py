#!/usr/bin/env python3
"""
Logic mirror for voleo-quickwins. Covers the bits with real logic:
  - #4 leader toggle (seed null <-> 1)
  - #3 clear scope resolution (all vs a single tz-local day)
  - #5 cross-group drop-at-index ordering (desiredOrder sent to the reorder endpoint)
Run: python3 docs/verify_quickwins.py
"""

# --- #4 PairController::toggleLeader ---
def toggle_leader(seed):
    return 1 if (seed or 0) <= 0 else None

# --- #3 ScheduleController::clearAll scope resolution ---
# matches: list of (id, day 'Y-m-d'). Returns the ids that get cleared.
def clear_ids(matches, scope, day=None):
    if scope == 'day':
        return [mid for (mid, d) in matches if d == day]
    return [mid for (mid, _) in matches]  # 'all'

# --- #5 groupBuilder desiredOrder after a drop at a given index ---
# existing: destination group's pair ids (DOM order). Insert moved before index
# `before` (len(existing) or None => append at end).
def insert_order(existing, moved, before):
    out = list(existing)
    if before is None or before >= len(out):
        out.append(moved)
    else:
        out.insert(before, moved)
    return out

checks = []
def check(name, cond): checks.append((name, bool(cond)))

# leader toggle
check('unseeded -> leader (1)', toggle_leader(None) == 1 and toggle_leader(0) == 1)
check('leader -> unseeded (None)', toggle_leader(1) is None and toggle_leader(3) is None)

# clear scope
m = [(1, '2026-10-16'), (2, '2026-10-16'), (3, '2026-10-17'), (4, '2026-10-18')]
check('clear all clears every scheduled match', sorted(clear_ids(m, 'all')) == [1, 2, 3, 4])
check('clear day clears only that day', sorted(clear_ids(m, 'day', '2026-10-16')) == [1, 2])
check('clear day with no matches clears none', clear_ids(m, 'day', '2026-10-99') == [])

# drop-at-index ordering
check('drop at top', insert_order(['b', 'c'], 'a', 0) == ['a', 'b', 'c'])
check('drop in middle', insert_order(['a', 'c'], 'b', 1) == ['a', 'b', 'c'])
check('drop at end (append)', insert_order(['a', 'b'], 'c', None) == ['a', 'b', 'c'])
check('drop past end clamps to append', insert_order(['a', 'b'], 'c', 9) == ['a', 'b', 'c'])

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
raise SystemExit(0 if passed == len(checks) else 1)
