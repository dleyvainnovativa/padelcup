#!/usr/bin/env python3
"""
Verifier for voleo-public-court-visibility.zip — a per-tournament setting that
controls how much court detail the PUBLIC surfaces + pantalla show (Completa /
Solo sede / Oculta). The manager dashboard is never affected.

Part A: logic mirror of the label + the pantalla court/venue resolution.
Part B: structural — enum, migration, model, request, edit form, public views,
        controller feeds, pantalla view + CSS.

Run from project root: python3 docs/verify_public_court.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: mirrors ------------------------------------------------------
FULL, VENUE_ONLY, HIDDEN = "full", "venue_only", "hidden"
def shows_court(v): return v == FULL
def shows_venue(v): return v != HIDDEN

def label(v, venue, court):
    if not court or v == HIDDEN:
        return ""
    parts = []
    if shows_venue(v) and venue:
        parts.append(venue)
    if shows_court(v) and court:
        parts.append(court)
    return " · ".join(parts)

check("A: Full → sede · cancha", label(FULL, "SP", "C1") == "SP · C1")
check("A: Full, no venue → cancha only", label(FULL, None, "C1") == "C1")
check("A: VenueOnly → sede only", label(VENUE_ONLY, "SP", "C1") == "SP")
check("A: VenueOnly, no venue → empty", label(VENUE_ONLY, None, "C1") == "")
check("A: Hidden → empty", label(HIDDEN, "SP", "C1") == "")
check("A: no court → empty regardless", label(FULL, "SP", None) == "")

# Pantalla court/venue cell resolution (mirror of displayData).
def disp_cell(v, court, venue):
    if v == HIDDEN:
        return ("", "")
    if not shows_court(v):  # VenueOnly
        return (venue or "", "")
    return (court, venue)  # Full
check("A: pantalla Full keeps court + venue", disp_cell(FULL, "C1", "SP") == ("C1", "SP"))
check("A: pantalla VenueOnly puts venue in the cell", disp_cell(VENUE_ONLY, "C1", "SP") == ("SP", ""))
check("A: pantalla Hidden blanks both", disp_cell(HIDDEN, "C1", "SP") == ("", ""))

# Court-only contexts (bracket/results/cruces) show the court only when Full.
def court_only(v, court):
    return court if shows_court(v) else None
check("A: court-only view shows court only on Full",
      court_only(FULL, "C1") == "C1" and court_only(VENUE_ONLY, "C1") is None and court_only(HIDDEN, "C1") is None)

# ---- Part B: structural ---------------------------------------------------
enum = read("app/Enums/PublicCourtVisibility.php")
check("B: enum has 3 cases", "case Full = 'full';" in enum and "case VenueOnly = 'venue_only';" in enum and "case Hidden = 'hidden';" in enum)
check("B: enum helpers", "function showsCourtName()" in enum and "function showsVenue()" in enum and "function isHidden()" in enum)

migs = list((ROOT / "database/migrations").glob("*public_court_visibility*"))
mig = migs[0].read_text() if migs else ""
check("B: migration adds column, default full", "public_court_visibility" in mig and "->default('full')" in mig)

model = read("app/Models/Tournament.php")
check("B: model fillable + cast",
      "'public_court_visibility'," in model and "'public_court_visibility' => \\App\\Enums\\PublicCourtVisibility::class," in model)
check("B: model helpers", "function publicCourtLabel(" in model and "function showsPublicCourtName()" in model
      and "function hidesPublicCourt()" in model)
check("B: label builds with ' · ' join", "implode(' · ', array_filter(" in model)

req = read("app/Http/Requests/Tournament/StoreTournamentRequest.php")
check("B: request validates the enum",
      "'public_court_visibility' => ['nullable', new Enum(\\App\\Enums\\PublicCourtVisibility::class)]," in req)

edit = read("resources/views/dashboard/tournaments/edit.blade.php")
check("B: edit form has the select",
      'name="public_court_visibility"' in edit and "PublicCourtVisibility::cases()" in edit)
check("B: edit form notes dashboard is unaffected", "panel de organización siempre ve la cancha completa" in edit)

# Public views
sched = read("resources/views/public/schedule.blade.php")
check("B: public calendar uses publicCourtLabel", "$tournament->publicCourtLabel($m->court)" in sched)
calmatch = read("resources/views/public/partials/category-calendar-match.blade.php")
check("B: category calendar partial uses publicCourtLabel", "$tournament->publicCourtLabel($m->court)" in calmatch)
cat = read("resources/views/public/category.blade.php")
check("B: bracket/results gate on showsPublicCourtName",
      "$m->court && $tournament->showsPublicCourtName()" in cat)
player = read("resources/views/public/player.blade.php")
check("B: player page uses publicCourtLabel (both spots)",
      "$tournament->publicCourtLabel($next->court)" in player and "$tournament->publicCourtLabel($m->court)" in player)

ctrl = read("app/Http/Controllers/PublicTournamentController.php")
check("B: category cruces feeds gate court",
      ctrl.count("$tournament->showsPublicCourtName() ? $m->court?->name : null") >= 2)
check("B: playerRow gates via category->tournament",
      "optional($category->tournament)->showsPublicCourtName()" in ctrl)
check("B: displayData resolves court/venue per setting",
      "$pcv = $tournament->publicCourtVisibility();" in ctrl and "'court'       => $dispCourt," in ctrl and "'venue'       => $dispVenue," in ctrl)
check("B: changes log no longer leaks raw court names",
      "{$court->venue->name}: {$court->name}" not in ctrl and "$tournament->publicCourtLabel($court)" in ctrl)

disp = read("resources/views/public/display.blade.php")
check("B: pantalla adds no-court class when hidden", "$tournament->hidesPublicCourt()) disp--no-court" in disp)
check("B: pantalla header label Cancha/Sede", "$tournament->showsPublicCourtName() ? 'Cancha' : 'Sede'" in disp)

css = read("resources/css/theme.css")
check("B: no-court column CSS", ".disp--no-court .disp__c-court { display: none; }" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
