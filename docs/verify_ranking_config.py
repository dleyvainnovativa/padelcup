#!/usr/bin/env python3
"""
Verifier for voleo-ranking-config.zip (items #4 + #5).

Part A: logic mirror of the finalizer's new behavior — category exclusion (#4)
and the alternate point table (#5) — proving the rules on synthetic data.
Part B: structural checks that the migration, pivot columns, finalizer hooks,
controller action, route and view are wired.

Run from project root: python3 docs/verify_ranking_config.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: finalizer logic mirror ---------------------------------------
# apply_stacking mirror: override replaces the schedule; stacking from system.
def apply_stacking(achievements, system_points, cumulative, points_override=None):
    unique = {}
    for a in achievements:
        unique[a] = (int(points_override.get(a, 0)) if points_override is not None
                     else int(system_points.get(a, 0)))
    if cumulative:
        return unique
    if not unique:
        return {}
    top = max(unique, key=lambda k: unique[k])
    return {top: unique[top]}

# build_ledger mirror (points + category exclusion only).
def build_ledger(categories, system_points, cumulative, points_override=None, excluded=None):
    excluded = set(int(x) for x in (excluded or []))
    rows = []
    for cat in categories:
        if int(cat["id"]) in excluded:
            continue
        if not cat["decided"]:
            continue
        for pair_id, achs in cat["byPair"].items():
            awarded = apply_stacking(achs, system_points, cumulative, points_override)
            for ach, pts in awarded.items():
                if pts <= 0:
                    continue
                for pl in cat["players"][pair_id]:
                    rows.append({"category_id": cat["id"], "player_id": pl, "achievement": ach, "points": pts})
    return rows

SYS = {"champion": 100, "finalist": 60, "group_win": 20, "group_stage": 10}

cats = [
    {"id": 1, "decided": True,  "byPair": {10: ["champion", "group_win"]}, "players": {10: ["A", "B"]}},
    {"id": 2, "decided": True,  "byPair": {20: ["finalist"]},              "players": {20: ["C"]}},
    {"id": 3, "decided": False, "byPair": {30: ["champion"]},             "players": {30: ["D"]}},
]

# Baseline: no override, no exclusion, cumulative.
base = build_ledger(cats, SYS, True)
champ_rows = [r for r in base if r["achievement"] == "champion"]
check("A: champion worth 100 (default schedule)", all(r["points"] == 100 for r in champ_rows))
check("A: undecided category awards nothing", all(r["category_id"] != 3 for r in base))
check("A: cumulative awards both champion+group_win to pair", len([r for r in base if r["player_id"] == "A"]) == 2)

# #4 exclude category 1 → no rows for it.
excl = build_ledger(cats, SYS, True, excluded=[1])
check("A(#4): excluded category produces no rows", all(r["category_id"] != 1 for r in excl))
check("A(#4): non-excluded category still counts", any(r["category_id"] == 2 for r in excl))

# #5 alternate point table → champion worth 500 instead of 100.
ovr = build_ledger(cats, SYS, True, points_override={"champion": 500, "finalist": 300, "group_win": 0, "group_stage": 0})
champ_ovr = [r for r in ovr if r["achievement"] == "champion"]
check("A(#5): override gives champion 500", all(r["points"] == 500 for r in champ_ovr))
check("A(#5): override 0 for group_win drops those rows", all(r["achievement"] != "group_win" for r in ovr))

# best_only stacking keeps only the top achievement.
best = build_ledger(cats, SYS, False)
check("A: best_only keeps one row for the two-achievement pair", len([r for r in best if r["player_id"] == "A"]) == 1)

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")

mig = read("database/migrations/2026_11_02_000001_add_overrides_to_ranking_system_tournament.php")
check("B: migration adds points_override", "points_override" in mig and "->json(" in mig)
check("B: migration adds excluded_category_ids", "excluded_category_ids" in mig)
check("B: migration down drops both", "dropColumn(['points_override', 'excluded_category_ids'])" in mig)

for rel in ["app/Models/Tournament.php", "app/Models/RankingSystem.php"]:
    src = read(rel)
    check(f"B: {Path(rel).stem} withPivot has overrides",
          "'points_override'" in src and "'excluded_category_ids'" in src)

fin = read("app/Services/Ranking/RankingFinalizer.php")
check("B: finalizer reads pivot override", "$link->pivot->points_override" in fin)
check("B: finalizer reads pivot exclusions", "$link->pivot->excluded_category_ids" in fin)
check("B: buildLedger takes override+excluded", "?array $pointsOverride = null," in fin and "array $excludedCategoryIds = []" in fin)
check("B: buildLedger skips excluded", "in_array((int) $category->id, $excludedCategoryIds, true)" in fin)
check("B: applyStacking uses override", "$pointsOverride[$a->value]" in fin)

ctrl = read("app/Http/Controllers/RankingSystemController.php")
check("B: controller action", "function updateTournamentConfig(" in ctrl)
check("B: controller writes pivot", "updateExistingPivot(" in ctrl)
check("B: controller computes excluded from included", "array_diff($allIds, $included)" in ctrl)
check("B: show loads linkedTournaments", "'linkedTournaments'" in ctrl)

routes = read("routes/web.php")
check("B: config route", "ranking-systems.tournament-config" in routes)

view = read("resources/views/ranking-systems/show.blade.php")
check("B: view category checkboxes", 'name="categories[]"' in view)
check("B: view custom points toggle", 'name="custom_points"' in view)
check("B: view points inputs", 'name="points[' in view)

css = read("resources/css/_theme-append-ranking-config.css")
check("B: CSS has rk-cfg", ".rk-cfg" in css)

# ---- report ---------------------------------------------------------------
passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
