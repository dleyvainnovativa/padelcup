#!/usr/bin/env python3
"""
Verifier for voleo-sidebar-nav.zip — Ganadores + Pantalla added to the
tournament-context sidebar. Structural only (Blade). Confirms both links sit in
the tournament branch, use the tournament-scoped routes, mirror the show-page
icons, and that the routes exist.

Run from project root: python3 docs/verify_sidebar_nav.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

side = read("resources/views/layouts/partials/sidebar.blade.php")

check("Ganadores link present", "$to('winners.index', $ctxTournament)" in side and "Ganadores" in side)
check("Ganadores active-state", "$isActive('winners.*')" in side)
check("Ganadores icon mirrors show page (trophy)", 'fa-trophy"></i> Ganadores' in side)
check("Pantalla link present", "$to('display.config', $ctxTournament)" in side and "Pantalla" in side)
check("Pantalla active-state", "$isActive('display.config')" in side)
check("Pantalla icon mirrors show page (display)", 'fa-display"></i> Pantalla' in side)

# Both must live in the tournament-context branch: appear after $inTournament and
# before the global-context branch ($isStaff elseif).
ini = side.find("elseif($inTournament)")
glo = side.find("elseif($isStaff)")
gan = side.find("winners.index', $ctxTournament")
pan = side.find("display.config', $ctxTournament")
check("links are inside the tournament branch",
      -1 < ini < gan < glo and -1 < ini < pan < glo)
# ...specifically in the Operación group (after its label, before Promoción).
op = side.find('Operación')
promo = side.find('Promoción')
check("links sit in the Operación group", -1 < op < gan < promo and -1 < op < pan < promo)

routes = read("routes/web.php")
check("winners.index route exists", "->name('winners.index')" in routes)
check("display.config route exists", "->name('display.config')" in routes)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
