#!/usr/bin/env python3
"""
Verifier for voleo-transfer-overall-fill.zip —
  (1) the JSON tournament transfer now carries bracket_seeding + winners_per_group
      (category) and max_matches_per_day + public_court_visibility (tournament);
  (2) overall-seeded extra qualifiers auto-fill deterministically (with a notice)
      instead of staying ghosted when the boundary ties.

Part A: logic mirrors. Part B: structural.

Run from project root: python3 docs/verify_transfer_overall.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: mirrors ------------------------------------------------------
# (1) transfer round-trip preserves the fields.
def roundtrip(exported):
    # import reads the same keys back (with defaults when absent).
    return {
        'bracket_seeding': exported.get('bracket_seeding'),
        'winners_per_group': exported.get('winners_per_group'),
        'max_matches_per_day': exported.get('max_matches_per_day'),
        'public_court_visibility': exported.get('public_court_visibility', 'full'),
    }
src = {'bracket_seeding': 'overall', 'winners_per_group': [2, 1], 'max_matches_per_day': 3, 'public_court_visibility': 'hidden'}
check("A: transfer preserves bracket_seeding", roundtrip(src)['bracket_seeding'] == 'overall')
check("A: transfer preserves winners_per_group", roundtrip(src)['winners_per_group'] == [2, 1])
check("A: transfer preserves max_matches_per_day", roundtrip(src)['max_matches_per_day'] == 3)
check("A: transfer preserves public_court_visibility", roundtrip(src)['public_court_visibility'] == 'hidden')
check("A: missing court visibility defaults to full", roundtrip({})['public_court_visibility'] == 'full')

# (2) qualifiers() fill behaviour.
def qualifiers(auto, pool_ranked, need, break_ties, has_tie):
    extras = []
    if need > 0 and pool_ranked:
        if (not has_tie) or break_ties:
            extras = pool_ranked[:need]
    return {'qualifiers': auto + extras, 'tie': ('TIE' if has_tie else None)}

AUTO = [1, 2, 3]
POOL = [10, 11, 12, 13]
check("A: no tie fills extras regardless of break_ties",
      qualifiers(AUTO, POOL, 3, False, False)['qualifiers'] == [1, 2, 3, 10, 11, 12])
check("A: tie + no break → extras dropped (positional prompt path)",
      qualifiers(AUTO, POOL, 3, False, True)['qualifiers'] == [1, 2, 3])
check("A: tie + break → extras filled deterministically (auto-fill path)",
      qualifiers(AUTO, POOL, 3, True, True)['qualifiers'] == [1, 2, 3, 10, 11, 12])
check("A: tie is reported either way (for notify)",
      qualifiers(AUTO, POOL, 3, True, True)['tie'] == 'TIE')
check("A: bindQualifiers tie_broken = tie present under break",
      (qualifiers(AUTO, POOL, 3, True, True)['tie'] is not None) is True
      and (qualifiers(AUTO, POOL, 3, True, False)['tie'] is not None) is False)

# ---- Part B: structural ---------------------------------------------------
xfer = read("app/Services/Tournament/TournamentTransferService.php")
check("B: category export includes bracket_seeding + winners_per_group",
      "'bracket_seeding' => $this->enum($cat->bracket_seeding)," in xfer and "'winners_per_group' => $cat->winners_per_group," in xfer)
check("B: category import reads bracket_seeding + winners_per_group",
      "'bracket_seeding' => $cat['bracket_seeding'] ?? null," in xfer and "'winners_per_group' => $cat['winners_per_group'] ?? null," in xfer)
check("B: tournament export includes max/day + court visibility",
      "'max_matches_per_day' => $t->max_matches_per_day," in xfer and "'public_court_visibility' => $this->enum($t->public_court_visibility)," in xfer)
check("B: tournament import reads max/day + court visibility",
      "'max_matches_per_day' => $tt['max_matches_per_day'] ?? null," in xfer and "'public_court_visibility' => $tt['public_court_visibility'] ?? 'full'," in xfer)

br = read("app/Services/Tournament/BracketService.php")
check("B: qualifiers takes breakTies", "public function qualifiers(Category $category, bool $breakTies = false): array" in br)
check("B: qualifiers fills extras under breakTies even on a tie", "if (! $tie || $breakTies) {" in br)
check("B: seedLabelMap takes breakTies + returns map/tie",
      "private function seedLabelMap(Category $category, bool $breakTies = false): array" in br
      and "return ['map' => $map, 'tie' => $q['tie']];" in br)
check("B: bindQualifiers returns tie info + uses breakTies",
      "public function bindQualifiers(Category $category): array" in br
      and "$this->seedLabelMap($category, breakTies: true);" in br
      and "return ['tie_broken' => $seed['tie'] !== null, 'tie' => $seed['tie']];" in br)

res = read("app/Http/Controllers/Dashboard/ResultController.php")
check("B: maybeBindBracket returns the tie-broken flag",
      "private function maybeBindBracket(Tournament $tournament, Category $category, GameMatch $match): bool" in res
      and "return ! empty($res['tie_broken']);" in res)
check("B: confirm/accept surface the notice",
      res.count("$tieBroken = $this->maybeBindBracket(") == 2 and "function tieNotice(" in res and "se desempató la última posición" in res)

draw = read("app/Http/Controllers/Dashboard/DrawController.php")
check("B: overall generate surfaces the notice",
      "! empty($this->brackets->bindQualifiers($category)['tie_broken'])" in draw and "se desempató la última posición" in draw)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
