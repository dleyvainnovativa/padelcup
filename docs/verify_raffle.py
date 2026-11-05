#!/usr/bin/env python3
"""
Verifier for voleo-raffle.zip (#1 random player picker).

Part A: logic mirror of the draw pool — "Sin repetir" dedupes by normalized
NAME (not player id, since the same person is a separate Player row per
category) and excludes already-drawn; off = raw visible rows (multi-category
weighted, repeats allowed). Scoped to the currently-visible (filtered) rows.
Part B: structural checks (manager-only via $raffle flag; UI + CSS wired).

Run from project root: python3 docs/verify_raffle.py
"""
import sys, random
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: pool logic mirror --------------------------------------------
def pool(rows, visible_ids, no_repeat, drawn):
    """rows: list of dicts {id, k(name-key), c(cat)}. visible_ids filters by UI
    search/category. Mirrors the x-data pool()."""
    p = [r for r in rows if r['id'] in visible_ids]
    if no_repeat:
        seen, out = set(), []
        for r in p:
            if r['k'] in drawn or r['k'] in seen:
                continue
            seen.add(r['k'])
            out.append(r)
        p = out
    return p

# Ana in 2 categories (2 rows, same name-key), Beto 1, Caro 1.
rows = [
    {'id': 1, 'k': 'ana ruiz', 'c': 'A'},
    {'id': 2, 'k': 'ana ruiz', 'c': 'B'},
    {'id': 3, 'k': 'beto paz', 'c': 'A'},
    {'id': 4, 'k': 'caro lee', 'c': 'B'},
]
all_ids = {1, 2, 3, 4}

# no_repeat OFF: raw rows → Ana appears twice (weighted), 4 entries.
p_off = pool(rows, all_ids, False, [])
check("A: off → 4 weighted entries", len(p_off) == 4)
check("A: off → Ana weighted (2 of 4)", sum(1 for r in p_off if r['k'] == 'ana ruiz') == 2)

# no_repeat ON: dedup by name → 3 unique people, Ana once.
p_on = pool(rows, all_ids, True, [])
check("A: on → 3 unique people", len(p_on) == 3)
check("A: on → Ana appears once", sum(1 for r in p_on if r['k'] == 'ana ruiz') == 1)

# no_repeat ON excludes already-drawn.
p_excl = pool(rows, all_ids, True, ['ana ruiz'])
check("A: on → drawn person excluded", all(r['k'] != 'ana ruiz' for r in p_excl) and len(p_excl) == 2)

# Scoped to visible rows only (category B visible).
visB = {2, 4}
p_vis = pool(rows, visB, True, [])
check("A: scoped to visible rows", sorted(r['k'] for r in p_vis) == ['ana ruiz', 'caro lee'])

# Exhaustion: all drawn → empty pool.
check("A: exhausted when all drawn", len(pool(rows, all_ids, True, ['ana ruiz', 'beto paz', 'caro lee'])) == 0)

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")
part = read("resources/views/shared/player-directory.blade.php")
check("B: gated by $raffle flag", "$raffle = $raffle ?? false;" in part and "@if($raffle)" in part)
check("B: dedup by normalized name key", "Player::normalize($r['player']['name'])" in part and "'k' =>" in part)
check("B: pool respects filters via matches()", "this.rows.filter(r => this.matches(r))" in part)
check("B: noRepeat dedups + excludes drawn", "this.drawn.includes(r.k)" in part)
check("B: draw picks random", "Math.floor(Math.random()" in part)
check("B: Sortear button", "Sortear jugador" in part and "@click=\"draw()\"" in part)
check("B: Sin repetir checkbox", 'x-model="noRepeat"' in part)
check("B: Reiniciar reset", "resetDraw()" in part)
check("B: winner highlight by data-pl-key", "data-pl-key" in part and "pl-dir__row--winner" in part)

idx = read("resources/views/dashboard/players/index.blade.php")
check("B: manager page passes raffle=true", "'raffle' => true" in idx)

css = read("resources/css/_theme-append-raffle.css")
check("B: CSS has pl-raffle + winner", ".pl-raffle" in css and ".pl-dir__row--winner" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
