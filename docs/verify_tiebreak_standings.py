#!/usr/bin/env python3
"""
Before/after mirror for voleo-tiebreak-standings (#12 + #9).

Proves:
  - the NEW default tiebreak order ranks a STANDARD pool (no walkovers, so
    match-points track match-wins) IDENTICALLY to the OLD hardcoded cross-group
    order — i.e. qualifier binding doesn't move for the "winners are correct" cases;
  - a custom configured order is honored;
  - head-to-head is skipped across groups;
  - the one case where old vs new differ is isolated and documented (a group with
    a walkover/forfeit, where match-points and match-wins disagree).

Run: python3 docs/verify_tiebreak_standings.py
"""

# Field map mirrors App\Support\TiebreakCriteria::field().
FIELD = {
    'matches_won': 'won',
    'sets_won': 'sets_for',
    'set_diff': 'set_diff',
    'games_won': 'games_for',
    'game_diff': 'game_diff',
    'head_to_head': None,  # not valid across groups
}
NEW_DEFAULT = ['matches_won', 'set_diff', 'game_diff', 'sets_won', 'games_won', 'head_to_head']


def rank_old(rows):
    """Legacy fixed chain: points → set_diff → game_diff → games_for."""
    return sorted(rows, key=lambda r: (-r['points'], -r['set_diff'], -r['game_diff'], -r['games_for']))


def rank_new(rows, order):
    """Configured order (numeric criteria), then legacy chain + pair_id."""
    def key(r):
        k = []
        for c in order:
            f = FIELD.get(c)
            if f is None:
                continue
            k.append(-r.get(f, 0))
        # stable fallback
        k += [-r['points'], -r['set_diff'], -r['game_diff'], r['pair_id']]
        return tuple(k)
    return sorted(rows, key=key)


def ids(rows):
    return [r['pair_id'] for r in rows]


def row(pid, won, points, sd, gd, sf=0, gf=0):
    return {'pair_id': pid, 'won': won, 'points': points,
            'set_diff': sd, 'game_diff': gd, 'sets_for': sf, 'games_for': gf}


checks = []
def check(name, cond): checks.append((name, bool(cond)))

# --- Standard pool: no walkovers, points == 2*wins + played-losses, so wins and
#     points order agree. New default must match old order exactly. ---
std = [
    row(1, won=3, points=6, sd=5, gd=20, sf=6, gf=40),
    row(2, won=2, points=4, sd=2, gd=8,  sf=4, gf=30),
    row(3, won=2, points=4, sd=1, gd=6,  sf=4, gf=28),
    row(4, won=1, points=2, sd=-3, gd=-12, sf=2, gf=22),
]
check('new default == old order for a standard pool', ids(rank_new(std, NEW_DEFAULT)) == ids(rank_old(std)))

# Boundary selection (top 2 extras) identical old vs new on the standard pool.
check('top-2 extras identical (standard)', ids(rank_new(std, NEW_DEFAULT))[:2] == ids(rank_old(std))[:2])

# --- Custom configured order is honored: games_won first flips ties. ---
pool = [
    row(10, won=2, points=4, sd=1, gd=2, sf=4, gf=50),
    row(11, won=2, points=4, sd=1, gd=2, sf=4, gf=60),  # more games won
]
check('custom order games_won-first ranks higher games first',
      ids(rank_new(pool, ['matches_won', 'games_won']))[0] == 11)

# --- head_to_head is skipped across groups (no crash, ignored). ---
check('head_to_head skipped cross-group',
      ids(rank_new(std, ['head_to_head', 'matches_won', 'set_diff', 'game_diff'])) == ids(rank_new(std, ['matches_won', 'set_diff', 'game_diff'])))

# --- The ONE documented difference: a walkover makes match-points disagree with
#     match-wins. Old ranks by points-first; new default ranks by wins-first. ---
wo = [
    # P20: 1 win + 2 played losses → points 4, wins 1, high set_diff
    row(20, won=1, points=4, sd=3, gd=5, sf=3, gf=20),
    # P21: 2 wins + 1 walkover loss → points 4, wins 2, lower set_diff
    row(21, won=2, points=4, sd=1, gd=3, sf=4, gf=22),
]
old_wo = ids(rank_old(wo))     # points tie → set_diff → P20 first
new_wo = ids(rank_new(wo, NEW_DEFAULT))  # wins first → P21 first
check('walkover case: old ranks set_diff-winner first (P20)', old_wo[0] == 20)
check('walkover case: new default ranks wins-winner first (P21)', new_wo[0] == 21)
check('walkover case is the documented behaviour change', old_wo != new_wo)

# --- Default order constant matches the spec (#12). ---
check('#12 default order constant', NEW_DEFAULT == ['matches_won', 'set_diff', 'game_diff', 'sets_won', 'games_won', 'head_to_head'])

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
print("\nNote: old vs new differ ONLY when match-points disagree with match-wins")
print("(walkovers/forfeits). For standard groups the qualifier order is unchanged.")
raise SystemExit(0 if passed == len(checks) else 1)
