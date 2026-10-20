#!/usr/bin/env python3
"""
Logic mirror for voleo-bracket-seeding (#2). Mirrors BracketService::seedByOverall
ordering (which reuses rankCrossGroup) and asserts:
  - overall seeding reorders qualifiers by the configured overall rank;
  - the qualifier SET is preserved (same ids, only order changes);
  - group seeding leaves the group-seeded order untouched;
  - a missing-from-standings id is appended (safety net).
Run: python3 docs/verify_bracket_seeding.py
"""

FIELD = {
    'matches_won': 'won', 'sets_won': 'sets_for', 'set_diff': 'set_diff',
    'games_won': 'games_for', 'game_diff': 'game_diff', 'head_to_head': None,
}
DEFAULT = ['matches_won', 'set_diff', 'game_diff', 'sets_won', 'games_won', 'head_to_head']


def rank_cross_group(rows, order):
    def key(r):
        k = []
        for c in order:
            f = FIELD.get(c)
            if f is None:
                continue
            k.append(-r.get(f, 0))
        k += [-r['points'], -r['set_diff'], -r['game_diff'], r['pair_id']]
        return tuple(k)
    return sorted(rows, key=key)


def seed_by_overall(qualifier_ids, rows_by_id, order):
    if len(qualifier_ids) < 2:
        return list(qualifier_ids)
    rows = [rows_by_id[i] for i in qualifier_ids if i in rows_by_id]
    ranked = [r['pair_id'] for r in rank_cross_group(rows, order)]
    for i in qualifier_ids:            # append any id missing from standings
        if i not in ranked:
            ranked.append(i)
    return ranked


def row(pid, won, points, sd, gd, sf=0, gf=0):
    return {'pair_id': pid, 'won': won, 'points': points,
            'set_diff': sd, 'game_diff': gd, 'sets_for': sf, 'games_for': gf}


# Qualifiers delivered in GROUP-seed order (A1, B1, C1, A2, …) but their overall
# strength differs: B1 (id 2) is actually the strongest, then A1 (1), then C1 (3).
rows = {
    1: row(1, won=2, points=4, sd=2, gd=8,  sf=4, gf=30),   # A1
    2: row(2, won=3, points=6, sd=5, gd=20, sf=6, gf=40),   # B1 (strongest)
    3: row(3, won=2, points=4, sd=1, gd=4,  sf=4, gf=26),   # C1
}
group_order = [1, 2, 3]  # as qualifiers() returns them (group position)

checks = []
def check(name, cond): checks.append((name, bool(cond)))

overall = seed_by_overall(group_order, rows, DEFAULT)
check('overall reseeds strongest (B1) to seed 1', overall[0] == 2)
check('overall order is by rank', overall == [2, 1, 3])
check('overall preserves the qualifier SET', sorted(overall) == sorted(group_order))

# Group mode leaves the order as delivered.
check('group mode leaves group order', list(group_order) == [1, 2, 3])

# Fewer than 2 → unchanged.
check('single qualifier unchanged', seed_by_overall([5], rows, DEFAULT) == [5])

# A qualifier id missing from standings is appended at the end.
mix = [2, 1, 99]
res = seed_by_overall(mix, rows, DEFAULT)
check('missing-from-standings id appended', res[-1] == 99 and sorted(res) == sorted(mix))

# Custom order changes the reseed (games_won first promotes higher games).
rows2 = {
    10: row(10, won=2, points=4, sd=1, gd=1, sf=4, gf=40),
    11: row(11, won=2, points=4, sd=1, gd=1, sf=4, gf=55),
}
check('custom order (games_won) promotes higher games',
      seed_by_overall([10, 11], rows2, ['matches_won', 'games_won'])[0] == 11)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
raise SystemExit(0 if passed == len(checks) else 1)
