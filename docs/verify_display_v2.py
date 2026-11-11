#!/usr/bin/env python3
"""
Verifier for the display board v2 refinements:
  - pin a specific date (day=Y-m-d) → that day only, whole day, finished inline
  - full names stacked (team1 / vs / team2)
  - results shown for decided matches (score + winner bolded)
  - ticker overlap fixed (clipped view) + score in ticker
  - signed window math (far-future/far-past don't leak in live mode)

Part A mirrors the date-pinned vs live bucketing + set formatting.
Run from project root: python3 docs/verify_display_v2.py
"""
import sys, re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: date filter + set formatting mirror --------------------------
def keep_for_day(match_day, day_filter):
    return (day_filter is None) or (match_day == day_filter)

check("A: day filter keeps only the pinned day",
      keep_for_day('2026-11-11', '2026-11-11') and not keep_for_day('2026-11-10', '2026-11-11'))
check("A: no day filter keeps all", keep_for_day('2026-11-10', None) and keep_for_day('2026-11-11', None))

def format_sets(sets):
    if not sets or not isinstance(sets, list): return None
    parts = []
    for s in sets:
        a = s[0] if len(s) > 0 else None
        b = s[1] if len(s) > 1 else None
        if a is None and b is None: continue
        parts.append(f"{int(a)}-{int(b)}")
    return ' '.join(parts) if parts else None

check("A: formats a two-set score", format_sets([[6, 3], [6, 4]]) == "6-3 6-4")
check("A: formats a three-set score", format_sets([[6, 3], [4, 6], [7, 5]]) == "6-3 4-6 7-5")
check("A: no result → None", format_sets([]) is None and format_sets(None) is None)

# Signed window: far-future upcoming excluded, near-future kept (live mode).
def in_ahead(now, start, ahead):
    return (start - now) <= ahead and start > now  # signed
check("A: near-future upcoming within window", in_ahead(600, 660, 180))
check("A: far-future upcoming excluded (signed, not abs)", not in_ahead(600, 1800, 180))

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")
ctrl = read("app/Http/Controllers/PublicTournamentController.php")
check("B: day param parsed + validated", "$dayFilter = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/'" in ctrl)
check("B: day-pinned keeps only that date", "$start->format('Y-m-d') !== $dayFilter" in ctrl)
check("B: day-pinned returns whole day sorted", "$col->sortBy('sort')->values()" in ctrl and "'dayLabel'" in ctrl)
check("B: score + winnerSide in feed", "'score'       => $score" in ctrl and "'winnerSide'  => $winnerSide" in ctrl)
check("B: set formatter", "private function formatMatchSets(" in ctrl)
check("B: signed window diffs", "diffInMinutes($now, false)" in ctrl and "diffInMinutes($now, false) > $aheadMin" in ctrl)

js = read("resources/js/modules/displayBoard.js")
check("B: stacked team rows", "disp__team" in js and "disp__vs" in js)
check("B: winner bolded + trophy", "is-win" in js and "winnerSide === 'a'" in js)
check("B: score cell for finished", "disp__score" in js and "Final" in js)
check("B: ticker duplicated for seamless loop", "disp__tick-seg" in js and js.count("${one}") == 2)
check("B: day label shown", "json.dayLabel" in js)

view = read("resources/views/public/display.blade.php")
check("B: ticker view wrapper (clip fix)", "disp__ticker-view" in view)

cfg = read("resources/views/dashboard/display/config.blade.php")
check("B: builder has day selector", "x-model=\"day\"" in cfg and "p.set('day'" in cfg)

css = read("resources/css/_theme-append-display.css")
check("B: stacked/winner/score CSS", ".disp__pairs" in css and ".disp__team.is-win" in css and ".disp__score" in css)
check("B: ticker-view clip CSS", ".disp__ticker-view" in css and "overflow: hidden" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
