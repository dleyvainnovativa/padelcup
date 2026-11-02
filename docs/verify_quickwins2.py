#!/usr/bin/env python3
"""
Structural verifier for voleo-quickwins2.zip (items #7, #1, #3).
Sandbox can't render Blade or run the app, so this checks the wiring is present
in the delivered files. Run from the project root: python3 docs/verify_quickwins2.py
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []

def check(name, cond):
    checks.append((name, bool(cond)))

def read(p):
    return (ROOT / p).read_text(encoding="utf-8")

# ---- #7 public leaderboard ------------------------------------------------
cat = read("resources/views/public/category.blade.php")
# Two tables each get PG + PP data cells (won/lost) -> expect 2 of each class.
check("#7 pub-pg cells present in both tables", cat.count('class="pub-pg"') == 2)
check("#7 pub-pp cells present in both tables", cat.count('class="pub-pp"') == 2)
check("#7 won field rendered", cat.count("$row['won']") == 2)
check("#7 lost field rendered", cat.count("$row['lost']") == 2)
check("#7 PG header in both tables", cat.count(">PG</th>") == 2)
check("#7 PP header in both tables", cat.count(">PP</th>") == 2)
check("#7 Pts header has tooltip", 'title="Puntos: victoria 2' in cat)
check("#7 shared legend present", 'class="pub-legend"' in cat)
check("#7 legend hidden on cruces view", "view !== 'cruces'" in cat)

# StandingsService actually produces won/lost (data source sanity).
ss = read("app/Services/Tournament/StandingsService.php")
check("#7 StandingsService blankRow has 'won'", "'won' => 0" in ss)
check("#7 StandingsService blankRow has 'lost'", "'lost' => 0" in ss)

# ---- #1 close control sheet on result save --------------------------------
sched = read("resources/js/modules/schedule.js")
# After onSaveResult, the success branch calls hide().
m = re.search(r"onSaveResult\(data, collected, played\);.*?\}\s*else\s*\{\s*//[^\n]*\n\s*hide\(\);",
              sched, re.S)
check("#1 success branch closes the sheet (hide())", m is not None)

# ---- #3 manager llave ghost labels ----------------------------------------
draw = read("app/Http/Controllers/Dashboard/DrawController.php")
check("#3 controller resolves ghost map", "->mapFor($category)" in draw)
check("#3 controller passes ghostQualifiers", "'ghostQualifiers'" in draw)

bracket = read("resources/views/dashboard/brackets/index.blade.php")
check("#3 view ghostFor side a", "ghostFor('a', $ghostQualifiers ?? [])" in bracket)
check("#3 view ghostFor side b", "ghostFor('b', $ghostQualifiers ?? [])" in bracket)
check("#3 view renders bmatch-ghost chip", bracket.count('class="bmatch-ghost"') == 2)

# ---- CSS snippet ----------------------------------------------------------
css = read("resources/css/_theme-append-quickwins2.css")
check("CSS has pub-pg", ".pub-pg" in css)
check("CSS has pub-pp", ".pub-pp" in css)
check("CSS has pub-legend", ".pub-legend" in css)
check("CSS has bmatch-ghost", ".bmatch-ghost" in css)

# ---- report ---------------------------------------------------------------
passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
