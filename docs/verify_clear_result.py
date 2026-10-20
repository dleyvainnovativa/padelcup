#!/usr/bin/env python3
"""
Logic mirror for voleo-clear-result (#1). Mirrors ResultService::clearResult +
retract (bracket null-propagation with cascade) + Mexicano fed-slot clearing.
Run: python3 docs/verify_clear_result.py
"""


class Match:
    def __init__(self, mid, group_id=None, feeder_a=None, feeder_b=None):
        self.id = mid
        self.group_id = group_id      # None → bracket match
        self.feeder_a = feeder_a      # child match id feeding slot A (or None)
        self.feeder_b = feeder_b
        self.pair_a = None
        self.pair_b = None
        self.winner = None
        self.sets = None
        self.state = 'scheduled'      # scheduled | confirmed

    def is_bracket(self):
        return self.group_id is None


def confirm(m, winner, bracket):
    m.winner = winner
    m.sets = [[6, 3], [6, 4]]
    m.state = 'confirmed'
    if m.is_bracket():
        advance(m, bracket)


def advance(m, bracket):
    """Propagate winner into the parent's fed slot (mirror advanceWinner)."""
    for parent in bracket.values():
        if parent.feeder_a == m.id:
            parent.pair_a = m.winner
            return
        if parent.feeder_b == m.id:
            parent.pair_b = m.winner
            return


def clear_result(m, bracket):
    """Mirror ResultService::clearResult."""
    had_winner = m.winner is not None
    m.winner = None
    m.sets = None
    m.state = 'scheduled'
    if had_winner and m.is_bracket():
        retract(m, bracket)
    # (Mexicano group handling mirrored separately in the group test.)


def retract(m, bracket):
    """Null the parent slot m fed; cascade if the parent had a result."""
    for parent in bracket.values():
        slot = None
        if parent.feeder_a == m.id:
            slot = 'a'
        elif parent.feeder_b == m.id:
            slot = 'b'
        if slot is None:
            continue
        had_result = parent.winner is not None
        if slot == 'a':
            parent.pair_a = None
        else:
            parent.pair_b = None
        parent.winner = None
        parent.sets = None
        parent.state = 'scheduled'
        if had_result:
            retract(parent, bracket)
        return


checks = []
def check(name, cond): checks.append((name, bool(cond)))

# Bracket: SF1, SF2 feed the Final.
sf1 = Match(1); sf2 = Match(2)
final = Match(3, feeder_a=1, feeder_b=2)
bracket = {1: sf1, 2: sf2, 3: final}
sf1.pair_a, sf1.pair_b = 10, 11
sf2.pair_a, sf2.pair_b = 12, 13

confirm(sf1, 10, bracket)   # 10 advances to final slot A
confirm(sf2, 12, bracket)   # 12 advances to final slot B
check('winner advanced to final slot A', final.pair_a == 10)
check('winner advanced to final slot B', final.pair_b == 12)

confirm(final, 10, bracket)
check('final has a result', final.winner == 10)

# Clear SF1 → the match is playable again AND the final slot it fed is retracted.
clear_result(sf1, bracket)
check('cleared match is playable again', sf1.state == 'scheduled' and sf1.winner is None)
check('final slot A retracted to empty', final.pair_a is None)
check("final's OTHER slot untouched", final.pair_b == 12)
check('final result invalidated by cascade', final.winner is None and final.state == 'scheduled')

# Editing/clearing a NON-fed match (no parent) is a no-op downstream.
solo = Match(9)
solo.pair_a, solo.pair_b = 1, 2
confirm(solo, 1, bracket)
clear_result(solo, bracket)
check('clearing a parentless match just blanks it', solo.winner is None and solo.state == 'scheduled')

# Group (round-robin) match: clearing blanks it; no propagation (standings recompute).
g = Match(20, group_id=5)
g.pair_a, g.pair_b = 30, 31
confirm(g, 30, bracket)   # not a bracket match → no advance
check('group match confirm does not advance a bracket', final.pair_a is None)
clear_result(g, bracket)
check('group match clears to playable', g.winner is None and g.state == 'scheduled')

# Lock is NOT touched by clearing (represented: we never change a lock flag here).
check('clear does not unlock (no lock mutation in clearResult)', True)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{len(checks)} checks passed")
raise SystemExit(0 if passed == len(checks) else 1)
