#!/usr/bin/env python3
"""Verifier: import preview no longer caps 'extra winners' at 3 (floor 0 kept;
advance 1-2 unchanged). Run: python3 docs/verify_import_extra.py"""
import sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(n, c): checks.append((n, bool(c)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

svc = read("app/Services/Registration/TournamentImportService.php")
check("server: extra floor kept, no upper cap", "'extra' => max(0, (int) ($found['extra'] ?? 0))" in svc)
check("server: no min(3) on extra", "min(3," not in svc)
check("server: advance still capped 1-2", "'advance' => max(1, min(2," in svc)

view = read("resources/views/dashboard/tournaments/import-preview.blade.php")
check("input: extra has no max=3", 'data-f="extra"' in view and 'max="3"' not in view.split('data-f="extra"')[1][:200])
check("input: extra keeps min=0", 'name="settings[{{ $row[\'category\'] }}][extra]" data-f="extra" value="0" min="0" class' in view)
check("js: live calc no Math.min(3) on extra", "Math.min(3, parseInt(row.querySelector('[data-f=\"extra\"]')" not in view)
check("js: extra floored at 0", 'const extra = Math.max(0, parseInt(row.querySelector(\'[data-f="extra"]\').value, 10) || 0);' in view)

passed = sum(1 for _, ok in checks if ok)
for n, ok in checks: print(f"  [{'OK' if ok else 'FAIL'}] {n}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
