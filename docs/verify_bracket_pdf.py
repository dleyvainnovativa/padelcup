#!/usr/bin/env python3
"""
Verifier for voleo-bracket-pdf.zip — one-page visual bracket PDF (linear & mirror).

Replaces the old vertical-list "PDF eliminación" with a real bracket tree sized
to the page, per category, with a logo + category URL header.

Part A: geometry mirror of BracketPdfComposer (same constants) over the REAL
        export — every match placed, no same-column overlaps, correct page
        sizing, and mirror shorter than linear (its whole point).
Part B: structural — composer, blade, controller, route, button, logo asset.
"""
import json, re, sys, glob
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
passed = failed = 0
def check(n, c):
    global passed, failed
    (globals().__setitem__('passed', passed+1) if c else globals().__setitem__('failed', failed+1))
    print(f"  {'ok  ' if c else 'FAIL'} {n}")

# ---- constants mirror BracketPdfComposer ----
BOX_W, ROW_H, ROW_GAP, COL_GAP, MARGIN, HEADER_H = 150, 34, 44, 28, 24, 74
COLW = BOX_W + COL_GAP

def build(matches):
    by = {m["ref"]: m for m in matches if not m.get("is_third_place")}
    maxr = max(m["round"] for m in by.values())
    root = next(m for m in by.values() if m["round"] == maxr)
    children = lambda m: [by[r] for r in (m["feeder_a"], m["feeder_b"]) if r and r in by]
    return by, root, children, maxr

def assign_y(start, children):
    y = {}; leaf = [0]
    def walk(m):
        ch = children(m)
        if not ch:
            y[m["ref"]] = leaf[0] + 0.5; leaf[0] += 1; return y[m["ref"]]
        ys = [walk(c) for c in ch]; y[m["ref"]] = sum(ys)/len(ys); return y[m["ref"]]
    walk(start); return y, leaf[0]

def linear(by, root, children, R):
    y, n = assign_y(root, children)
    W = MARGIN*2 + R*COLW + 46
    H = MARGIN + HEADER_H + n*ROW_GAP + MARGIN
    boxes = {ref: (MARGIN+(by[ref]["round"]-1)*COLW, MARGIN+HEADER_H+(y[ref]-0.5)*ROW_GAP, "L") for ref in by}
    return W, H, boxes

def mirror(by, root, children, R):
    kids = children(root)
    side = {root["ref"]: "C"}
    def mark(m, s):
        side[m["ref"]] = s
        for c in children(m): mark(c, s)
    if kids: mark(kids[0], "L")
    if len(kids) > 1: mark(kids[1], "R")
    yL, nL = assign_y(kids[0], children) if kids else ({}, 0)
    yR, nR = assign_y(kids[1], children) if len(kids) > 1 else ({}, 0)
    wing = (R-1)*COLW
    W = MARGIN*2 + 2*wing + BOX_W + 40
    H = MARGIN + HEADER_H + max(nL, nR)*ROW_GAP + MARGIN
    boxes = {}
    for ref, m in by.items():
        s = side.get(ref, "L"); r = m["round"]
        if s == "L":
            boxes[ref] = (MARGIN+(r-1)*COLW, MARGIN+HEADER_H+(yL.get(ref,0)-0.5)*ROW_GAP, "L")
        elif s == "R":
            boxes[ref] = (W-MARGIN-BOX_W-(r-1)*COLW, MARGIN+HEADER_H+(yR.get(ref,0)-0.5)*ROW_GAP, "R")
        else:
            boxes[ref] = ((W-BOX_W)/2, MARGIN+HEADER_H+(max(nL,nR)/2)*ROW_GAP - ROW_H/2, "C")
    return W, H, boxes

def no_overlap(by, boxes):
    # group by (round, side-column x); boxes in a column must not vertically overlap
    cols = {}
    for ref, (x, y, s) in boxes.items():
        cols.setdefault(round(x), []).append(y)
    for ys in cols.values():
        ys.sort()
        for a, b in zip(ys, ys[1:]):
            if b - a < ROW_H - 0.5:  # must be at least a box-height apart
                return False
    return True

# ---- Part A ----
export = next(iter(glob.glob("/mnt/user-data/uploads/*don-justo*.json") +
                   ([sys.argv[1]] if len(sys.argv) > 1 and Path(sys.argv[1]).exists() else [])), None)
if export:
    print(f"Part A — geometry mirror over {Path(export).name}")
    ms = json.load(open(export))["categories"][0]["matches"]
    by, root, children, R = build(ms)
    LW, LH, lb = linear(by, root, children, R)
    MW, MH, mb = mirror(by, root, children, R)
    check("linear places every match", len(lb) == len(by))
    check("mirror places every match", len(mb) == len(by))
    check("linear: no overlapping boxes in a column", no_overlap(by, lb))
    check("mirror: no overlapping boxes in a column", no_overlap(by, mb))
    check("mirror is shorter than linear (fits big draws)", MH < LH)
    check("mirror is wider than linear (uses both sides)", MW > LW)
    check("page sizes are sane (positive, bounded)", 0 < LW < 5000 and 0 < LH < 8000 and 0 < MW < 8000)
    print(f"      linear {LW:.0f}x{LH:.0f}pt · mirror {MW:.0f}x{MH:.0f}pt")
else:
    print("Part A — skipped (export not found)")

# ---- Part B ----
print("Part B — structural")
comp = (ROOT/"app/Services/Tournament/BracketPdfComposer.php").read_text()
check("composer has both layouts", "private function linear(" in comp and "private function mirror(" in comp)
check("composer returns page size + boxes + lines", "'width'" in comp and "'boxes'" in comp and "'lines'" in comp)
check("composer constants match", f"BOX_W = {BOX_W}" in comp and f"ROW_GAP = {ROW_GAP}" in comp)

dc = (ROOT/"app/Http/Controllers/Dashboard/DrawController.php").read_text()
check("controller bracketPdf method", "public function bracketPdf(" in dc)
check("controller auto-picks mirror for big draws", "$firstRound > 8 ? 'mirror' : 'linear'" in dc)
check("controller sizes paper to the bracket", "setPaper([0, 0, $data['width'], $data['height']])" in dc)
check("controller passes category public URL", "route('public.category', [$tournament, $category])" in dc)

blade = (ROOT/"resources/views/dashboard/schedule/pdf-bracket.blade.php").read_text()
check("pdf blade embeds the logo", "img/voleo-logo.png" in blade)
check("pdf blade shows category + url", "$category->name" in blade and "$categoryUrl" in blade)
check("pdf blade draws boxes + connector lines", "class=\"bx\"" in blade and "class=\"ln\"" in blade)

web = (ROOT/"routes/web.php").read_text()
check("route draw.bracket.pdf", "'bracketPdf'])->name('draw.bracket.pdf')" in web)

llave = (ROOT/"resources/views/dashboard/brackets/index.blade.php").read_text()
check("llave page has the PDF button + layout toggle", "draw.bracket.pdf" in llave and "Lado a lado" in llave and "Lineal" in llave)

sched = (ROOT/"resources/views/dashboard/schedule/index.blade.php").read_text()
check("old all-categories elimination PDF button retired", "exportEliminationPdf" not in sched)

check("logo PNG asset present", (ROOT/"public/img/voleo-logo.png").exists())

print()
total = passed + failed
print(f"{passed}/{total} checks passed")
sys.exit(1 if failed else 0)
