#!/usr/bin/env python3
"""
Verifier for voleo-display.zip — TV/kiosk arrivals board.

Part A: logic mirror of displayData()'s current/upcoming/finished bucketing with
the look-ahead / look-back windows, preferring live stamps over time inference.
Part B: structural — routes (ordered before the category catch-all), controller
methods, JSON shape, standalone view, JS poll/rotate, app wiring, builder + links.

Run from project root: python3 docs/verify_display.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))

# ---- Part A: classification mirror (minutes since midnight) ----------------
def classify(now, start, dur, status, ended_at_set, ahead=180, back=45):
    end = start + dur
    if status == 'finished' or (not ended_at_set and status != 'playing' and now >= end):
        finished_at = end  # (ended_at would override, same idea)
        if (now - finished_at) > back:
            return None
        return 'finished'
    if status == 'playing' or (start <= now < end):
        return 'current'
    if now < start:
        if (start - now) > ahead:
            return None
        return 'upcoming'
    return None

NOW = 600  # 10:00
check("A: playing → current", classify(NOW, 540, 60, 'playing', False) == 'current')
check("A: time-in-window no stamps → current", classify(NOW, 570, 60, 'pending', False) == 'current')  # 9:30-10:30
check("A: past end, no stamps → finished", classify(NOW, 520, 60, 'pending', False) == 'finished')     # ended 9:40, 20<45
check("A: finished status → finished", classify(NOW, 500, 60, 'finished', True) == 'finished')
check("A: finished too old → skip", classify(NOW, 400, 60, 'pending', False) is None)                  # ended 8:00, 140>45
check("A: upcoming within window → upcoming", classify(NOW, 660, 60, 'pending', False) == 'upcoming')  # 11:00
check("A: upcoming too far → skip", classify(NOW, 1000, 60, 'pending', False) is None)                 # 16:40, >180
check("A: called soon (upcoming) honored", classify(NOW, 615, 60, 'called', False) == 'upcoming')      # 10:15 future

# ---- Part B: structural ---------------------------------------------------
def read(p): return (ROOT / p).read_text(encoding="utf-8")

routes = read("routes/web.php")
dpos = routes.find("public.display'")
cpos = routes.find("{category:slug}")
check("B: display route exists", "public.display'" in routes and "public.display.data" in routes)
check("B: display route precedes category catch-all", dpos != -1 and cpos != -1 and dpos < cpos)
check("B: builder route exists", "display.config" in routes)

ctrl = read("app/Http/Controllers/PublicTournamentController.php")
check("B: display() method", "public function display(" in ctrl)
check("B: displayData() method", "public function displayData(" in ctrl)
check("B: feed buckets current/upcoming/finished", "'bucket'      => $bucket" in ctrl and "'board'" in ctrl and "'finished'" in ctrl)
check("B: venue filter in feed", "$venueIds" in ctrl)
check("B: day-aware duration used", "$tournament->durationForDay($start)" in ctrl)
check("B: winner included for ticker", "'winner'" in ctrl)

view = read("resources/views/public/display.blade.php")
check("B: standalone page (own doctype)", "<!DOCTYPE html>" in view and "@vite(" in view)
check("B: board data attrs", "data-display-board" in view and "data-max" in view and "data-rotate" in view and "data-refresh" in view)
check("B: branding (logo + instagram)", "x-logo" in view and "voleo.mx" in view)
check("B: robots noindex", 'name="robots"' in view)

js = read("resources/js/modules/displayBoard.js")
check("B: JS polls feed with query string", "dataUrl + window.location.search" in js and "setInterval(refresh" in js)
check("B: JS paginates + rotates", "function chunk(" in js and "startRotation" in js)
check("B: JS live clock", "tickClock" in js)
check("B: JS escapes output", "function esc" in js or "const esc" in js)

app = read("resources/js/app.js")
check("B: app wires displayBoard", "data-display-board" in app and "initDisplayBoard" in app)

cfg = read("resources/views/dashboard/display/config.blade.php")
check("B: builder live URL", "get url()" in cfg and ":href=\"url\"" in cfg)

show = read("resources/views/dashboard/tournaments/show.blade.php")
check("B: Pantalla linked (desktop + mobile)", show.count("route('display.config'") == 2)

css = read("resources/css/_theme-append-display.css")
check("B: display CSS + builder CSS", ".disp__board" in css and ".dc__grid" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
