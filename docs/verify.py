"""
Structural verification of the singles logic (no PHP interpreter in sandbox).
Mirrors the PHP in Pair.php and PaymentReconciler.php exactly and asserts the
money-critical outcomes for every flow.
"""

# ---- Pair model mirror ------------------------------------------------
class Pair:
    def __init__(self, is_singles, player1_id, player2_id, display_name=None):
        self.is_singles = is_singles
        self.player1_id = player1_id
        self.player2_id = player2_id
        self.display_name = display_name
        self.player1_name = "Ana"
        self.player2_name = "Luis" if player2_id else None

    def is_complete(self):
        if self.is_singles:
            return self.player1_id is not None
        return self.player2_id is not None

    def name(self):
        if self.display_name:
            return self.display_name
        p1 = self.player1_name or "—"
        if self.is_singles:
            return p1
        p2 = self.player2_name or "(pendiente)"
        return f"{p1} / {p2}"

# ---- Reconciler mirror ------------------------------------------------
def expected_fee_count(pair, invitation_status):
    if pair and pair.is_singles:
        return 1
    has_pending_invite = invitation_status == "pending"
    return 2 if ((pair and pair.is_complete()) or has_pending_invite) else 1

def rolls_up_paid(pair, invitation_status, paid_player_ids):
    expected = expected_fee_count(pair, invitation_status)
    paid = len(set(paid_player_ids))
    return paid >= expected, expected, paid

# ---- Scenarios --------------------------------------------------------
results = []
def check(desc, actual, expect):
    ok = actual == expect
    results.append(ok)
    print(f"[{'PASS' if ok else 'FAIL'}] {desc}: got {actual!r}, want {expect!r}")

# name() rendering
check("singles name = one player, no separator",
      Pair(True, 1, None).name(), "Ana")
check("doubles complete name = A / B",
      Pair(False, 1, 2).name(), "Ana / Luis")
check("doubles pending partner name = A / (pendiente)",
      Pair(False, 1, None).name(), "Ana / (pendiente)")
check("display_name override wins for singles",
      Pair(True, 1, None, "Equipo X").name(), "Equipo X")

# isComplete()
check("singles complete with only player1", Pair(True, 1, None).is_complete(), True)
check("doubles NOT complete without player2", Pair(False, 1, None).is_complete(), False)
check("doubles complete with player2", Pair(False, 1, 2).is_complete(), True)

# expected fee count — the money-critical part
check("SINGLES expects 1 fee",
      expected_fee_count(Pair(True, 1, None), None), 1)
check("SINGLES expects 1 even if a stray invite existed (guard wins)",
      expected_fee_count(Pair(True, 1, None), "pending"), 1)
check("DOUBLES complete expects 2 fees",
      expected_fee_count(Pair(False, 1, 2), None), 2)
check("DOUBLES pending-invite expects 2 fees (partner not joined yet)",
      expected_fee_count(Pair(False, 1, None), "pending"), 2)
check("DOUBLES no partner, no invite (edge) expects 1",
      expected_fee_count(Pair(False, 1, None), None), 1)

# roll-up confirmation outcomes
paid, exp, got = rolls_up_paid(Pair(True, 1, None), None, [1])
check("SINGLES confirms on 1 payment", paid, True)

paid, _, _ = rolls_up_paid(Pair(False, 1, 2), None, [1])
check("DOUBLES does NOT confirm on 1 of 2 payments", paid, False)

paid, _, _ = rolls_up_paid(Pair(False, 1, 2), None, [1, 2])
check("DOUBLES confirms on 2 payments", paid, True)

paid, _, _ = rolls_up_paid(Pair(False, 1, None), "pending", [1])
check("DOUBLES invite: registrant's single payment does NOT confirm", paid, False)

# ---- engine: playerIds filters null -----------------------------------
def player_ids(pair):
    return [x for x in [pair.player1_id, pair.player2_id] if x is not None]
check("singles playerIds has 1 entry (scheduler conflict-safe)",
      player_ids(Pair(True, 5, None)), [5])
check("doubles playerIds has 2 entries",
      player_ids(Pair(False, 5, 6)), [5, 6])

print()
print(f"{sum(results)}/{len(results)} checks passed")
assert all(results), "SOME CHECKS FAILED"
print("ALL STRUCTURAL CHECKS PASSED")
