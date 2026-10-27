#!/usr/bin/env python3
"""
verify_live_board.py — structural checks for voleo-live-board.

This feature is front-end DOM/fetch orchestration (no backend logic to mirror),
so the verifier asserts the invariants that make the live refresh correct and
regression-proof, by inspecting the three JS modules it touches.

Run: python3 docs/verify_live_board.py   → expects "N/N".
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
JS = ROOT / "resources" / "js" / "modules"


def read(rel):
    return (JS / rel).read_text(encoding="utf-8")


def main():
    passed = total = 0

    def check(name, cond):
        nonlocal passed, total
        total += 1
        if cond:
            passed += 1
        else:
            print(f"  FAIL: {name}")

    sched = read("schedule.js")
    cat = read("categoryHighlight.js")
    badges = read("playerBadges.js")

    # 1. The 7 board actions now refresh instead of hard-reloading.
    check("7+ refreshBoard() calls in schedule.js", sched.count("refreshBoard();") >= 7)

    # 2. The ONLY hard reloads left are the two deliberate fallbacks
    #    (refreshBoard catch + multi-select when no onDone was passed).
    check("only 2 window.location.reload() in schedule.js",
          sched.count("window.location.reload()") == 2)

    # 3. bindBoard is defined and called at least twice (initial + each refresh).
    check("bindBoard defined", "function bindBoard()" in sched)
    check("bindBoard called >=2x", len(re.findall(r"\bbindBoard\(\);", sched)) >= 2)

    # 4. refreshBoard exists, is re-entrancy guarded, and restores scroll.
    check("refreshBoard defined", "async function refreshBoard()" in sched)
    check("refreshBoard re-entrancy guard", "if (refreshing) return;" in sched)
    check("refreshBoard restores scroll", "window.scrollTo(0, scrollY)" in sched)

    # 5. It fetches, parses, swaps the node, and re-inits Alpine on the new one.
    check("fetch current url", "fetch(window.location.href" in sched)
    check("DOMParser used", "new DOMParser()" in sched)
    check("swaps board node", "board.replaceWith(fresh)" in sched)
    check("Alpine re-init on new node", "Alpine?.initTree?.(board)" in sched)
    check("Alpine teardown on old node", "Alpine?.destroyTree?.(board)" in sched)

    # 6. cfg + the out-of-board player index are refreshed from the response.
    check("cfg re-read after swap", "cfg = JSON.parse(board.dataset.schedConfig)" in sched)
    check("player index refreshed", "player-match-index" in sched)

    # 7. A refresh event is dispatched for board-scoped helpers to re-apply.
    check("dispatches sched:board-refreshed", "sched:board-refreshed" in sched)

    # 8. Multi-select no longer hard-reloads; it calls the passed-in onDone.
    check("multi-select uses onDone", "if (onDone) onDone(); else window.location.reload();" in sched)
    check("multi-select resolves board live", "const getBoard = ()" in sched)
    check("selection exposes reset", "reset: () => setActive(false)" in sched)

    # 9. categoryHighlight re-applies dimming on the event, against a live board.
    check("categoryHighlight listens for refresh",
          "addEventListener('sched:board-refreshed'" in cat)
    check("categoryHighlight apply() re-queries board",
          "const board = document.querySelector('[data-sched-board]');" in cat)

    # 10. playerBadges re-reads its index + re-points board on the event.
    check("playerBadges listens for refresh",
          "addEventListener('sched:board-refreshed'" in badges)
    check("playerBadges jumpToMatch resolves board live",
          "jumpToMatch(document.querySelector('[data-sched-board]')" in badges)

    print(f"{passed}/{total}")
    return 0 if passed == total else 1


if __name__ == "__main__":
    raise SystemExit(main())
