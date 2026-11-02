#!/usr/bin/env python3
"""
Structural verifier for voleo-mobile-actions.zip.
Adds the desktop-only tournament actions to the mobile tile grid (full parity),
incl. Export, Ganadores, Mesa de control. Run: python3 docs/verify_mobile_actions.py
"""
import re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
show = (ROOT / "resources/views/dashboard/tournaments/show.blade.php").read_text(encoding="utf-8")

# Isolate the mobile block so we assert tiles exist THERE (not just desktop).
m = re.search(r"dash-actions-mobile(.*?)@include", show, re.S)
mobile = m.group(1) if m else ""

for label, needle in [
    ("Mesa de control", "route('control.index'"),
    ("Ganadores", "route('winners.index'"),
    ("Exportar", "route('tournaments.transfer.export'"),
    ("Jugadores", "route('tournament.players'"),
    ("Patrocinadores", "route('sponsors.index'"),
    ("Respaldar", "route('tournaments.backup'"),
]:
    check(f"mobile grid has {label}", needle in mobile and "dash-tile" in mobile)

check("Patrocinadores no longer commented out", "<!-- <a href=\"{{ route('sponsors.index'" not in show)
check("backup is a POST form tile", 'class="dash-grid__form"' in mobile and "@csrf" in mobile)
check("unlock tile gated by isLocked", "$tournament->isLocked()" in mobile)
check("CSS: form becomes grid item", ".dash-grid__form { display: contents; }" in
      (ROOT / "resources/css/_theme-append-mobileactions.css").read_text(encoding="utf-8"))
check("desktop toolbar untouched (still present)", "dash-actions-desktop" in show)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
