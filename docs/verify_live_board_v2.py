#!/usr/bin/env python3
"""
Verifier for voleo-live-board-v2.zip — make the calendar fluid: kill the slow
full-page re-fetch after every save and the scroll-to-top jump.

What changed:
  (1) The board moved into its own partial (dashboard/schedule/partials/board).
      index() and the live refresh render the SAME markup from the SAME data
      (ScheduleController::gridData → boardViewData), so they can't diverge.
  (2) place / setTime / unplace / unplaceMany now hand back the freshly rendered
      board partial INLINE in their JSON ('board' key) → the client repaints with
      ONE round-trip instead of a POST + a full-page GET.
  (3) A new light endpoint GET calendario/tablero (schedule.board) returns ONLY
      the board partial — no layout, cheatsheets, conflict audit or capacity
      preview. refreshBoard() (used by swap / switchCourt / results / multi-select)
      fetches THAT instead of the whole page.
  (4) ResultController confirm/edit return a tiny JSON for AJAX callers (calendar +
      bracket) instead of a 302 back() that the fetch follows into a full index()
      render. Resultados page (real form posts) still redirects.
  (5) refreshBoard's last-resort hard reload now preserves scroll via sessionStorage.

Part A: logic mirror — the one-vs-two round-trip decision + the expectsJson branch.
Part B: structural — controller methods/returns, route, ResultController branches,
        blade extraction, JS wiring.
"""

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
passed = 0
failed = 0


def check(name, cond):
    global passed, failed
    if cond:
        passed += 1
        print(f"  ok   {name}")
    else:
        failed += 1
        print(f"  FAIL {name}")


def read(rel):
    return (ROOT / rel).read_text(encoding="utf-8")


# ---------------------------------------------------------------------------
# Part A — logic mirrors
# ---------------------------------------------------------------------------
print("Part A — logic mirror")


# Client decision: use the inline board if the action handed one back, else fetch.
def apply_or_refresh(res):
    if res and isinstance(res.get("board"), str):
        return "inline"      # single round-trip: swap the returned HTML
    return "fetch"           # fall back to the light board GET


check("place/setTime/unplace return board -> inline swap (1 round-trip)",
      apply_or_refresh({"ok": True, "board": "<div data-sched-board></div>"}) == "inline")
check("swap/switchCourt (no board key) -> light fetch",
      apply_or_refresh({"ok": True, "action": "swap"}) == "fetch")
check("result save ({ok:true} only) -> light fetch",
      apply_or_refresh({"ok": True, "status": "Resultado confirmado."}) == "fetch")
check("null response -> light fetch (never crashes)",
      apply_or_refresh(None) == "fetch")


# Server: how a result save responds depends on whether the caller expects JSON.
def result_response(expects_json, ok=True):
    if not ok:
        return ("json", 422) if expects_json else ("redirect_back", 302)
    return ("json", 200) if expects_json else ("redirect_back", 302)


check("calendar/bracket result save (Accept: json) -> JSON, no redirect-follow",
      result_response(True) == ("json", 200))
check("Resultados page result save (form post) -> redirect back (unchanged)",
      result_response(False) == ("redirect_back", 302))
check("calendar result validation error -> 422 JSON (error reaches the sheet)",
      result_response(True, ok=False) == ("json", 422))


# Cost model: board partial skips the heavy index-only work.
INDEX_ONLY = {"busyDayPlayers(audit)", "preferredSchedulePlayers", "unpaidPlayers",
              "capacity.preview", "capacity.proposeWindows", "categories", "full-layout"}
BOARD_NEEDS = {"courts", "matches", "scheduled", "unscheduled", "ghostQualifiers",
               "days", "slots", "multiCategoryPlayers", "playerMatchIndex"}
check("board partial drops the index-only heavy work",
      INDEX_ONLY.isdisjoint(BOARD_NEEDS))


# ---------------------------------------------------------------------------
# Part B — structural
# ---------------------------------------------------------------------------
print("Part B — structural")

sc = read("app/Http/Controllers/Dashboard/ScheduleController.php")
check("ScheduleController: gridData() helper", "private function gridData(Tournament $tournament): array" in sc)
check("ScheduleController: boardViewData() helper", "private function boardViewData(Tournament $tournament): array" in sc)
check("ScheduleController: boardHtml() helper", "private function boardHtml(Tournament $tournament): string" in sc)
check("ScheduleController: public board() endpoint", "public function board(Tournament $tournament)" in sc)
check("ScheduleController: index() uses the shared gridData()", "$this->gridData($tournament)" in sc)
check("ScheduleController: boardViewData feeds the partial render",
      "view('dashboard.schedule.partials.board', $this->boardViewData($tournament))" in sc)

# Each of the four simple endpoints returns the board inline.
for name in ["place", "setTime", "unplace", "unplaceMany"]:
    body = re.search(r"public function %s\(.*?\n    \}" % name, sc, re.S)
    check(f"{name}() success response carries 'board'",
          bool(body) and "'board' => $this->boardHtml($tournament)" in body.group(0))

# switchCourt / swapMatches run in DB transactions → intentionally NOT inlined.
check("switchCourt stays on the light refresh (no inline board render in a tx)",
      "public function switchCourt" in sc
      and "'board' => $this->boardHtml" not in re.search(r"public function switchCourt.*?public function swapMatches", sc, re.S).group(0))

web = read("routes/web.php")
check("route schedule.board exists", "->name('schedule.board')" in web and "calendario/tablero" in web)

rc = read("app/Http/Controllers/Dashboard/ResultController.php")
check("ResultController confirm() has an expectsJson branch",
      "public function confirm" in rc
      and "$request->expectsJson()" in re.search(r"public function confirm.*?public function acceptProposal", rc, re.S).group(0))
check("ResultController edit() has an expectsJson branch",
      "public function edit" in rc
      and "$request->expectsJson()" in re.search(r"public function edit\(.*?\n    \}", rc, re.S).group(0))
check("ResultController confirm JSON success shape", "'ok' => true, 'status' =>" in rc)
check("ResultController JSON error shape (422)", "'ok' => false, 'errors' =>" in rc)

# Blade extraction
partial_path = ROOT / "resources/views/dashboard/schedule/partials/board.blade.php"
check("board partial file exists", partial_path.exists())
part = read("resources/views/dashboard/schedule/partials/board.blade.php")
idx = read("resources/views/dashboard/schedule/index.blade.php")
check("index includes the board partial", "@include('dashboard.schedule.partials.board')" in idx)
check("player-match-index moved into the partial", 'id="player-match-index"' in part)
check("player-match-index no longer inline in index", 'id="player-match-index"' not in idx)
check("boardUrl exposed in board config", "'boardUrl' => route('schedule.board', $tournament)" in part)
check("board node present in partial", "data-sched-board" in part)
check("board node NOT left in index", "data-sched-board" not in idx)

# Partial must only depend on vars boardViewData provides (plus locals).
provided = {"tournament", "courts", "scheduled", "unscheduled", "ghostQualifiers",
            "days", "slots", "multiCategoryPlayers", "playerMatchIndex"}
index_only_vars = {"busyDayPlayers", "preferredSchedulePlayers", "unpaidPlayers",
                   "capacity", "proposedWindows", "proposalOverflow", "presentPhases",
                   "phaseWindows", "categories"}
check("partial references none of the index-only (heavy) vars",
      not any(("$" + v) in part for v in index_only_vars))

# JS wiring
js = read("resources/js/modules/schedule.js")
check("JS: applyBoardHtml() swap-from-string helper", "function applyBoardHtml(html)" in js)
check("JS: applyOrRefresh() inline-or-fetch decision", "function applyOrRefresh(res)" in js)
check("JS: refreshBoard fetches the light board endpoint", "cfg.boardUrl" in js)
check("JS: refreshBoard no longer fetches the whole page URL",
      "fetch(window.location.href" not in js)
check("JS: hardReloadKeepingScroll preserves scroll", "function hardReloadKeepingScroll()" in js
      and "sessionStorage.setItem(SCROLL_KEY" in js)
check("JS: scroll restored on load", "sessionStorage.getItem(SCROLL_KEY)" in js)
check("JS: place uses applyOrRefresh(res)",
      "const res = await post(cfg.placeUrl" in js)
check("JS: unplace uses applyOrRefresh(res)",
      "const res = await post(cfg.unplaceUrl" in js)
check("JS: setTime uses applyOrRefresh(res)",
      "const res = await post(cfg.setTimeUrl" in js)
check("JS: multi-select passes the response through",
      "if (onDone) onDone(res)" in js)

# ---------------------------------------------------------------------------
print()
total = passed + failed
print(f"{passed}/{total} checks passed")
sys.exit(1 if failed else 0)
