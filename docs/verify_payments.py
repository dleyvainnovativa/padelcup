#!/usr/bin/env python3
"""
Verifier for voleo-payments.zip (items #8 + #9).

Part A: logic mirror of ScheduleController::unpaidPlayers() grouping — proves the
dedup-by-normalized-name and per-category-unpaid behavior on synthetic pairs.
Part B: structural checks that the wiring (controller, blade, JS) is present.

Run from project root: python3 docs/verify_payments.py
"""
import sys
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: logic mirror -------------------------------------------------
def normalize(name):
    # Mirror of Player::normalize (lowercase + collapse whitespace); good enough
    # for the grouping property under test.
    return re.sub(r"\s+", " ", name.strip().lower())

def unpaid_players(pairs):
    """pairs: list of {category, players:[{name, paid}]}. Returns rows deduped by
    normalized name, each with the categories where the player is still unpaid,
    sorted by lowercased name."""
    by_name = {}
    for pair in pairs:
        cat = pair["category"]
        for pl in pair["players"]:
            if pl["paid"]:
                continue
            key = normalize(pl["name"])
            row = by_name.setdefault(key, {"name": pl["name"], "categories": []})
            if cat and cat not in row["categories"]:
                row["categories"].append(cat)
    return sorted(by_name.values(), key=lambda r: r["name"].lower())

# Scenario: Ana in two categories (unpaid in both); Beto paid in his only cat;
# Caro unpaid in A, paid in B (should list only A); "ana " dup name folds in.
pairs = [
    {"category": "Open A", "players": [{"name": "Ana Ruiz", "paid": False}, {"name": "Beto Paz", "paid": True}]},
    {"category": "Open B", "players": [{"name": "Ana  Ruiz ", "paid": False}, {"name": "Caro Lee", "paid": True}]},
    {"category": "Open A", "players": [{"name": "Caro Lee", "paid": False}, {"name": "Dani Mora", "paid": False}]},
]
rows = unpaid_players(pairs)
names = [r["name"] for r in rows]

check("A: Beto (fully paid) excluded", "Beto Paz" not in names)
check("A: Ana appears once (name dedup)", names.count("Ana Ruiz") == 1)
ana = next(r for r in rows if normalize(r["name"]) == normalize("Ana Ruiz"))
check("A: Ana lists both unpaid categories", sorted(ana["categories"]) == ["Open A", "Open B"])
caro = next(r for r in rows if r["name"] == "Caro Lee")
check("A: Caro lists only the unpaid category", caro["categories"] == ["Open A"])
check("A: Dani (unpaid) present", "Dani Mora" in names)
check("A: rows sorted by name", names == sorted(names, key=str.lower))

# #9 property: other-categories excludes the current one.
def other_cats(all_cats, current):
    return [c for c in all_cats if c != current]
check("A(#9): other categories excludes current", other_cats(["Open A", "Open B"], "Open A") == ["Open B"])
check("A(#9): single-category player -> no others", other_cats(["Open A"], "Open A") == [])

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")
ctrl = read("app/Http/Controllers/Dashboard/ScheduleController.php")
check("B: unpaidPlayers() method defined", "private function unpaidPlayers(" in ctrl)
check("B: uses paidPlayerStates", "$pair->paidPlayerStates()" in ctrl)
check("B: eager loads registration.payments", "'registration.payments'" in ctrl)
check("B: passes unpaidPlayers to view", "'unpaidPlayers' => $unpaidPlayers" in ctrl)

blade = read("resources/views/dashboard/schedule/index.blade.php")
check("B: unpaidCount in total", "+ $unpaidCount" in blade)
check("B: unpaid tab entry", "$plTabs['unpaid']" in blade)
check("B: unpaid section", 'data-pl-tab="unpaid"' in blade)
check("B: playerCategoryMap built", "$playerCategoryMap" in blade)
check("B: otherCategories serialized", "'otherCategories' => $otherCats" in blade)

js = read("resources/js/modules/schedule.js")
check("B: pagos renders otherCategories", "p.otherCategories" in js)
check("B: subtitle label", "También en:" in js)
check("B: subtitle class", "sched-pago__cats" in js)

css = read("resources/css/_theme-append-payments.css")
check("B: CSS has sched-pago__cats", ".sched-pago__cats" in css)

# ---- report ---------------------------------------------------------------
passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
