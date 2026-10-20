#!/usr/bin/env python3
"""
Logic mirror for voleo-exports (#6 + #10). Covers the bits with real logic:
  - #10 calendar-grid: start-time → slot snapping, category filter, day filter
  - #6 ranking xlsx: worksheet-title sanitization (Excel rules) + uniqueness
Run: python3 docs/verify_exports.py
(PhpSpreadsheet/DomPDF rendering itself can't run here — packagist is blocked —
so the export services are verified by php -l; this covers their pure logic.)
"""
import re


# --- #10 ScheduleController::exportGridPdf slot snapping ---
def snap_slot(start_min, slot_mins, step):
    """slot_mins: ordered dict label->minute. Returns the slot label or None."""
    for label, m in slot_mins.items():
        if m <= start_min < m + step:
            return label
    if slot_mins:
        first_label = next(iter(slot_mins))
        last_label = list(slot_mins)[-1]
        first_min = slot_mins[first_label]
        return first_label if start_min < first_min else last_label
    return None


def included(category_id, cat_ids):
    return (not cat_ids) or (category_id in cat_ids)


def day_included(day, day_filter):
    return (day_filter is None) or (day == day_filter)


# --- #6 RankingExport::uniqueTitle ---
def unique_title(name, used):
    clean = re.sub(r'[\\/\?\*\[\]:]', ' ', name)
    clean = re.sub(r'\s+', ' ', clean).strip() or 'Torneo'
    base = clean[:31]
    title = base
    i = 2
    while title.lower() in used:
        suffix = f' ({i})'
        title = base[:31 - len(suffix)] + suffix
        i += 1
    used.append(title.lower())
    return title


checks = []
def check(name, cond): checks.append((name, bool(cond)))

slots = {'08:00': 480, '09:15': 555, '10:30': 630}  # step 75
step = 75

# 09:20 (560) falls in the 09:15 slot (555..630).
check('match snaps to its slot window', snap_slot(560, slots, step) == '09:15')
# Exactly on a boundary → that slot.
check('exact slot start snaps to it', snap_slot(555, slots, step) == '09:15')
# Before the first slot → clamp to first.
check('before grid clamps to first slot', snap_slot(400, slots, step) == '08:00')
# After the last slot window → clamp to last.
check('after grid clamps to last slot', snap_slot(900, slots, step) == '10:30')

# Category filter.
check('no cat filter includes all', included(7, []) is True)
check('cat filter excludes others', included(7, [1, 2]) is False and included(2, [1, 2]) is True)

# Day filter.
check('no day filter includes all days', day_included('2026-10-17', None))
check('day filter keeps only that day',
      day_included('2026-10-17', '2026-10-17') and not day_included('2026-10-16', '2026-10-17'))

# Worksheet titles: forbidden chars stripped, 31-char cap, uniqueness.
used = ['resumen', 'puntos']
t1 = unique_title('Copa / Invierno [2026]: Fase 1', used)
check('forbidden chars stripped from title', '/' not in t1 and '[' not in t1 and ':' not in t1)
check('title capped at 31 chars', len(unique_title('X' * 50, used)) == 31)
a = unique_title('Torneo Regional', used)
b = unique_title('Torneo Regional', used)
check('duplicate titles disambiguated', a != b and b.endswith('(2)'))
check('collision with reserved names handled', unique_title('Resumen', used).lower() != 'resumen')

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
raise SystemExit(0 if passed == len(checks) else 1)
