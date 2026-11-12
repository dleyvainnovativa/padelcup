#!/usr/bin/env python3
"""
Verifier for voleo-sidebar-rename.zip — the sidebar "Categorías" button (which
opens the create-category form) renamed to "Nueva categoría".

Run from project root: python3 docs/verify_sidebar_rename.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
side = (ROOT / "resources/views/layouts/partials/sidebar.blade.php").read_text(encoding="utf-8")

check("renamed to 'Nueva categoría'", "Nueva categoría" in side)
check("old label gone", "> Categorías" not in side)
check("still points at categories.create", "$to('categories.create', $ctxTournament)" in side)
check("active-state narrowed to the create route", "$isActive('categories.create')" in side)
# cumulative: the earlier nav additions are still present.
check("Ganadores + Pantalla still present (cumulative on voleo-sidebar-nav)",
      "winners.index" in side and "display.config" in side)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
