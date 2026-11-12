#!/usr/bin/env python3
"""
Verifier for voleo-topbar-search.zip — wires the (previously dead) topbar search
to a manager-scoped endpoint with a live dropdown.

Part A: logic mirrors — min-length gate, player dedupe key, result grouping.
Part B: structural — controller + scoping, route, topbar markup, JS module +
        app.js wiring, CSS.

Run from project root: python3 docs/verify_topbar_search.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: mirrors ------------------------------------------------------
def should_query(q):
    return len(q.strip()) >= 2
check("A: <2 chars returns empty", should_query("a") is False and should_query(" ") is False)
check("A: >=2 chars queries", should_query("ab") is True)

def dedupe_players(rows):
    # rows: (normalized_name, category_id) — one entry per name+category.
    seen, out = set(), []
    for norm, cid in rows:
        k = f"{norm}|{cid}"
        if k in seen:
            continue
        seen.add(k)
        out.append(k)
    return out
check("A: same player in same category dedupes",
      dedupe_players([("ana", 1), ("ana", 1), ("ana", 2)]) == ["ana|1", "ana|2"])

def group_order(data):
    return [g for g in ("tournaments", "categories", "players") if data.get(g)]
check("A: groups render in fixed order",
      group_order({"players": [1], "tournaments": [1]}) == ["tournaments", "players"])

# ---- Part B: structural ---------------------------------------------------
ctrl = read("app/Http/Controllers/Dashboard/DashboardSearchController.php")
check("B: controller exists", "class DashboardSearchController" in ctrl)
check("B: scoped to the manager's own tournaments",
      "$manager->tournaments()" in ctrl and "$tids = $manager->tournaments()->pluck('tournaments.id');" in ctrl)
check("B: categories scoped to those tournament ids",
      "Category::whereIn('tournament_id', $tids)" in ctrl)
check("B: players scoped via pairs in those tournaments",
      "whereHas('category', fn ($c) => $c->whereIn('tournament_id', $tids))" in ctrl)
check("B: min-length guard", "mb_strlen($q) < 2" in ctrl)
check("B: returns three groups", "'tournaments'" in ctrl and "'categories'" in ctrl and "'players'" in ctrl)

routes = read("routes/web.php")
check("B: dashboard.search route (auth group)", "->name('dashboard.search')" in routes and "DashboardSearchController" in routes)

app_view = read("resources/views/layouts/app.blade.php")
check("B: topbar wired with data attrs",
      "data-topbar-search" in app_view and "data-search-url=\"{{ route('dashboard.search') }}\"" in app_view)
check("B: input + results panel present",
      "data-search-input" in app_view and "data-search-results" in app_view)

js = read("resources/js/modules/topbarSearch.js")
check("B: module exports initTopbarSearch", "export function initTopbarSearch()" in js)
check("B: debounced GET to the endpoint", "get(`${url}?q=${encodeURIComponent(q)}`)" in js)
check("B: out-of-order responses guarded", "if (mine !== token) return;" in js)
check("B: keyboard + click-outside", "ArrowDown" in js and "!root.contains(e.target)" in js)
check("B: escapes result text", "function esc(" in js)

appjs = read("resources/js/app.js")
check("B: app.js wires topbar search", "data-topbar-search" in appjs and "initTopbarSearch()" in appjs)

css = read("resources/css/theme.css")
check("B: dropdown CSS present", ".topbar-search__panel" in css and ".tbs__row" in css)
check("B: search box made position:relative", ".topbar-search { position: relative; }" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
