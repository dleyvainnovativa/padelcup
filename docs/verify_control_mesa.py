#!/usr/bin/env python3
"""
Logic mirror for voleo-mesa-control. The sandbox can't run Laravel, so this
reproduces the control-status, delay, and per-player payment math exactly as the
PHP does it, and asserts the behaviour. Run: python3 docs/verify_control_mesa.py

Mirrors:
  - GameMatch::controlStatus()         (status derivation)
  - ControlController::applyStatus()   (timestamp stamping/clearing)
  - GameMatch::delayMinutes()          (late-start minutes)
  - PaymentReconciler::expectedFeeCount / rollUpRegistration (fully-paid)
  - RegistrationService::setPlayerPaid (materialize bridge)
  - Pair::paidPlayerStates()           (per-player display truth)
"""

# --- GameMatch::controlStatus() ------------------------------------------
def control_status(called, started, ended, confirmed=False, winner=False):
    if ended is not None or confirmed or winner:
        return 'finished'
    if started is not None:
        return 'playing'
    if called is not None:
        return 'called'
    return 'pending'


# --- ControlController::applyStatus() ------------------------------------
def apply_status(stamps, status, now):
    called, started, ended = stamps['called'], stamps['started'], stamps['ended']
    if status == 'pending':
        return {'called': None, 'started': None, 'ended': None}
    if status == 'called':
        return {'called': called if called is not None else now, 'started': None, 'ended': None}
    if status == 'playing':
        return {'called': called if called is not None else now,
                'started': started if started is not None else now, 'ended': None}
    if status == 'finished':
        return {'called': called if called is not None else now,
                'started': started if started is not None else now,
                'ended': ended if ended is not None else now}
    raise ValueError(status)


# --- GameMatch::delayMinutes() -------------------------------------------
def delay_minutes(starts_at, started_at, called_at):
    if starts_at is None:
        return None
    actual = started_at if started_at is not None else called_at
    if actual is None:
        return None
    return actual - starts_at  # signed minutes


# --- PaymentReconciler ----------------------------------------------------
def expected_fee_count(is_singles, is_complete, has_pending_invite):
    if is_singles:
        return 1
    return 2 if (is_complete or has_pending_invite) else 1


def fully_paid(paid_distinct_players, expected):
    return paid_distinct_players >= expected


# --- Pair::paidPlayerStates() --------------------------------------------
def paid_player_states(player_ids, payment_rows, reg_status):
    """payment_rows: {player_id: 'paid'|'unpaid'}. reg_status: 'paid'|'pending'|'unpaid'."""
    has_rows = len(payment_rows) > 0
    paid_ids = {pid for pid, st in payment_rows.items() if st == 'paid'}
    legacy_paid = (reg_status == 'paid')
    out = {}
    for pid in player_ids:
        out[pid] = (pid in paid_ids) if has_rows else legacy_paid
    return out


# --- RegistrationService::setPlayerPaid (materialize bridge) -------------
def set_player_paid(player_ids, payment_rows, reg_status, player_id, paid):
    rows = dict(payment_rows)
    has_rows = len(rows) > 0
    # Preserve a legacy wholesale-paid pair: create paid rows for the others.
    if not has_rows and reg_status == 'paid':
        for pid in player_ids:
            if pid != player_id:
                rows[pid] = 'paid'
    rows[player_id] = 'paid' if paid else 'unpaid'
    # Re-roll: fully paid when every expected fee is paid.
    is_singles = (len(player_ids) == 1)
    expected = expected_fee_count(is_singles, is_complete=(len(player_ids) == 2), has_pending_invite=False)
    paid_distinct = len({pid for pid, st in rows.items() if st == 'paid'})
    new_status = 'paid' if fully_paid(paid_distinct, expected) else 'pending'
    return rows, new_status


checks = []
def check(name, cond):
    checks.append((name, bool(cond)))


# 1-4 control status derivation
check('pending when no stamps', control_status(None, None, None) == 'pending')
check('called when called_at only', control_status(10, None, None) == 'called')
check('playing when started_at', control_status(10, 15, None) == 'playing')
check('finished when ended_at', control_status(10, 15, 40) == 'finished')
check('finished when confirmed (no ended)', control_status(None, None, None, confirmed=True) == 'finished')

# 6-8 applyStatus stepping
s = apply_status({'called': None, 'started': None, 'ended': None}, 'playing', now=100)
check('playing stamps called+started, not ended', s['called'] == 100 and s['started'] == 100 and s['ended'] is None)
s2 = apply_status({'called': 50, 'started': 60, 'ended': None}, 'finished', now=100)
check('finished preserves earlier real stamps', s2['called'] == 50 and s2['started'] == 60 and s2['ended'] == 100)
s3 = apply_status({'called': 50, 'started': 60, 'ended': 90}, 'called', now=100)
check('stepping back to called clears started/ended', s3['called'] == 50 and s3['started'] is None and s3['ended'] is None)

# 9 delay
check('delay = started - scheduled (late)', delay_minutes(480, 495, 490) == 15)
check('delay None when not started/called', delay_minutes(480, None, None) is None)

# 10-11 fully paid / expected
check('doubles needs 2 fees', expected_fee_count(False, True, False) == 2)
check('singles needs 1 fee', expected_fee_count(True, False, False) == 1)
check('pending invite doubles needs 2', expected_fee_count(False, False, True) == 2)

# 12 setPlayerPaid materialize bridge on a legacy wholesale-paid doubles pair
rows, status = set_player_paid([1, 2], {}, 'paid', player_id=2, paid=False)
# marking player 2 unpaid should keep player 1 paid (materialized) and drop reg to pending
check('materialize keeps other half paid', rows.get(1) == 'paid' and rows.get(2) == 'unpaid')
check('reg drops to pending after unpaying one half', status == 'pending')

# per-player display truth: no rows + legacy paid -> both paid
st = paid_player_states([1, 2], {}, 'paid')
check('legacy paid shows both players paid', st == {1: True, 2: True})
# rows present -> driven by rows
st2 = paid_player_states([1, 2], {1: 'paid', 2: 'unpaid'}, 'pending')
check('rows drive per-player states', st2 == {1: True, 2: False})
# singles fully paid on single mark
rows_s, status_s = set_player_paid([7], {}, 'unpaid', player_id=7, paid=True)
check('singles fully paid on single mark', status_s == 'paid')


passed = sum(1 for _, ok in checks if ok)
total = len(checks)
for name, ok in checks:
    print(('  OK  ' if ok else ' FAIL ') + name)
print(f"\n{passed}/{total} checks passed")
raise SystemExit(0 if passed == total else 1)
