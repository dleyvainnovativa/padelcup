#!/usr/bin/env python3
"""
Logic mirror for voleo-overall-bracket (#2). Mirrors:
  - O1..ON rank seed labels + standard seeding placement (O1 vs ON in round 1)
  - the live overall-rank projection (updates when a result changes)
  - O# label rendering ("3º")
  - binding O# → the overall-ranked qualifier
Run: python3 docs/verify_overall_bracket.py
"""
import re
import math

FIELD = {'matches_won': 'won', 'sets_won': 'sets_for', 'set_diff': 'set_diff',
         'games_won': 'games_for', 'game_diff': 'game_diff', 'head_to_head': None}
DEFAULT = ['matches_won', 'set_diff', 'game_diff', 'sets_won', 'games_won', 'head_to_head']


def overall_labels(n):
    return [f'O{k}' for k in range(1, n + 1)]


def next_pow2(n):
    p = 1
    while p < n:
        p *= 2
    return p


def standard_seed_order(size):
    order = [1, 2]
    while len(order) < size:
        nxt = []
        n = len(order) * 2
        for s in order:
            nxt.append(s)
            nxt.append(n + 1 - s)
        order = nxt
    return order


def round1_pairings(labels):
    size = max(2, next_pow2(len(labels)))
    padded = labels + ['BYE'] * (size - len(labels))
    order = standard_seed_order(size)
    slots = [padded[s - 1] for s in order]
    return [(slots[i], slots[i + 1]) for i in range(0, size, 2)]


def rank_overall(rows, order=DEFAULT):
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


def seed_label_text(label):
    m = re.match(r'^O(\d+)$', label)
    return f'{m.group(1)}º' if m else label


def row(pid, won, pts, sd, gd, sf=0, gf=0):
    return {'pair_id': pid, 'won': won, 'points': pts, 'set_diff': sd,
            'game_diff': gd, 'sets_for': sf, 'games_for': gf}


checks = []
def check(name, cond): checks.append((name, bool(cond)))

# Labels + placement for 4 qualifiers.
labels = overall_labels(4)
check('O-labels for N qualifiers', labels == ['O1', 'O2', 'O3', 'O4'])
pairings = round1_pairings(labels)
check('round 1 is O1 vs O4 and O2 vs O3', pairings == [('O1', 'O4'), ('O2', 'O3')])

# 6 qualifiers → size 8, two byes for the top seeds.
p6 = round1_pairings(overall_labels(6))
check('6 qualifiers → size 8 (4 matches)', len(p6) == 4)
check('top seed O1 drawn vs a BYE', ('O1', 'BYE') in p6)

# Rendering.
check('O3 renders as 3º', seed_label_text('O3') == '3º')
check('non-overall label untouched', seed_label_text('A1') == 'A1')

# Live projection: rank the current qualifiers overall; O1 = strongest.
rows = [
    row(1, won=2, pts=4, sd=2, gd=8),   # group A winner
    row(2, won=3, pts=6, sd=5, gd=20),  # group B winner (strongest)
    row(3, won=2, pts=4, sd=1, gd=4),   # group C winner
    row(4, won=1, pts=2, sd=-3, gd=-9), # an extra
]
ranked = rank_overall(rows)
proj = {f'O{i+1}': r['pair_id'] for i, r in enumerate(ranked)}
check('projection O1 = strongest (pair 2)', proj['O1'] == 2)
check('projection in overall order', [proj[f'O{i}'] for i in range(1, 5)] == [2, 1, 3, 4])

# A result changes → projection updates (pair 1 surges past pair 2).
rows2 = [dict(r) for r in rows]
rows2[0].update(won=4, pts=8, sd=9, gd=30)  # pair 1 now strongest
proj2 = {f'O{i+1}': r['pair_id'] for i, r in enumerate(rank_overall(rows2))}
check('projection updates when a result changes', proj2['O1'] == 1 and proj['O1'] == 2)

# Binding: O# → overall-ranked qualifier id (same order as the projection).
bind = {f'O{i+1}': r['pair_id'] for i, r in enumerate(rank_overall(rows))}
check('binding matches overall rank order', bind == proj)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
raise SystemExit(0 if passed == len(checks) else 1)
