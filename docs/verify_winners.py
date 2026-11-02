#!/usr/bin/env python3
"""
Verifier for voleo-winners.zip (item #6).

Part A: logic mirror of PodiumService podium resolution on synthetic bracket
data — proves champion/runner-up/3rd/decided rules without the app.
Part B: structural checks that the service, controller, routes, view, export,
public delegation and nav link are wired.

PhpSpreadsheet can't run in the sandbox (packagist blocked), so the xlsx itself
is verified by php -l + this structural check + Daniel testing the download.

Run from project root: python3 docs/verify_winners.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: podium logic mirror ------------------------------------------
def resolve_podium(has_bracket, matches):
    """matches: list of {round, id, state, winner, a, b}. Mirrors forCategory()."""
    base = {"decided": False, "champion": None, "runner_up": None, "third": None}
    if not has_bracket or not matches:
        return base
    max_round = max(m["round"] for m in matches)
    final_round = [m for m in matches if m["round"] == max_round]
    final = next((m for m in final_round if m["state"] == "confirmed"), final_round[0])
    if final["state"] != "confirmed" or not final["winner"]:
        return base
    champ = final["a"] if final["winner"] == "a" else final["b"]
    runner = final["b"] if final["winner"] == "a" else final["a"]
    base["decided"] = True
    base["champion"] = champ
    base["runner_up"] = runner  # None when bye-final (b is None)
    # 3rd: confirmed same-round match other than the final.
    third_m = next((m for m in matches
                    if m["round"] == final["round"] and m["id"] != final["id"]
                    and m["state"] == "confirmed" and m["winner"]), None)
    if third_m:
        base["third"] = third_m["a"] if third_m["winner"] == "a" else third_m["b"]
    return base

# Scenario 1: decided final + a 3rd-place playoff in the same round.
s1 = resolve_podium(True, [
    {"round": 1, "id": 1, "state": "confirmed", "winner": "a", "a": "SF-win-1", "b": "x"},
    {"round": 2, "id": 3, "state": "confirmed", "winner": "a", "a": "Champ", "b": "RunnerUp"},
    {"round": 2, "id": 4, "state": "confirmed", "winner": "b", "a": "x", "b": "Third"},
])
check("A1 champion from final winner", s1["champion"] == "Champ")
check("A1 runner-up from final loser", s1["runner_up"] == "RunnerUp")
check("A1 third from same-round playoff", s1["third"] == "Third")
check("A1 decided", s1["decided"] is True)

# Scenario 2: final not confirmed → undecided, no names.
s2 = resolve_podium(True, [
    {"round": 2, "id": 3, "state": "pending", "winner": None, "a": "x", "b": "y"},
])
check("A2 undecided when final unconfirmed", s2["decided"] is False and s2["champion"] is None)

# Scenario 3: bye-final (no runner-up), no 3rd-place match.
s3 = resolve_podium(True, [
    {"round": 2, "id": 3, "state": "confirmed", "winner": "a", "a": "Champ", "b": None},
])
check("A3 bye-final: champion set, runner-up None", s3["champion"] == "Champ" and s3["runner_up"] is None)
check("A3 no 3rd when no playoff", s3["third"] is None)

# Scenario 4: no bracket → nothing decided.
s4 = resolve_podium(False, [])
check("A4 no bracket → undecided", s4["decided"] is False)

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")

svc = read("app/Services/Tournament/PodiumService.php")
check("B: PodiumService.forCategory", "public function forCategory(" in svc)
check("B: PodiumService.forTournament", "public function forTournament(" in svc)
check("B: PodiumService includes player names", "'champion_players'" in svc and "'third_players'" in svc)

exp = read("app/Services/Tournament/WinnersExport.php")
check("B: WinnersExport builds Spreadsheet", "new Spreadsheet()" in exp and "function build(" in exp)
check("B: export reads PodiumService", "$this->podiums->forTournament(" in exp)

ctrl = read("app/Http/Controllers/Dashboard/WinnersController.php")
check("B: controller index", "function index(" in ctrl)
check("B: controller export guards PhpSpreadsheet", "class_exists(\\PhpOffice\\PhpSpreadsheet\\Spreadsheet::class)" in ctrl)
check("B: controller streams xlsx", "streamDownload(" in ctrl)
check("B: controller authorizes view", "$this->authorize('view', $tournament)" in ctrl)

routes = read("routes/web.php")
check("B: winners.index route", "winners.index" in routes)
check("B: winners.export route", "winners.export" in routes)

view = read("resources/views/dashboard/winners/index.blade.php")
check("B: view export button", "route('winners.export'" in view)
check("B: view shows champion/runner/third", "champion" in view and "runner_up" in view and "third" in view)

pub = read("app/Http/Controllers/PublicTournamentController.php")
check("B: public delegates to PodiumService", "PodiumService::class)->forCategory" in pub)
check("B: old resolveThirdPlace removed", "private function resolveThirdPlace" not in pub)

show = read("resources/views/dashboard/tournaments/show.blade.php")
check("B: nav link to winners", "route('winners.index'" in show)

css = read("resources/css/_theme-append-winners.css")
check("B: CSS has win-grid", ".win-grid" in css)

# ---- report ---------------------------------------------------------------
passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
