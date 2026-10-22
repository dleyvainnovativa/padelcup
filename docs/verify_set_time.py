#!/usr/bin/env python3
"""
verify_set_time.py — logic mirror for voleo-set-match-time.

Mirrors ScheduleController::setTime() + the SchedulingService checks it reuses,
in plain Python, so the day-preservation + conflict/force behavior can be
checked without a running app (Packagist is blocked in the sandbox).

Run: python3 docs/verify_set_time.py   → expects "N/N".
"""
from datetime import datetime, timedelta

TZ = "America/Mexico_City"  # fixed, like the controller


def parse(day, hhmm):
    """day='2026-10-25', hhmm='09:30' -> naive datetime (one tz, so naive is fine)."""
    return datetime.strptime(f"{day} {hhmm}", "%Y-%m-%d %H:%M")


def overlap(a_start, a_end, b_start, b_end):
    """Half-open [start, end) overlap — matches courtOverlap()."""
    return a_start < b_end and b_start < a_end


def set_time(match, courts, others, avail, new_time, duration, force):
    """
    match: {'id','day','court_id'} (already scheduled)
    courts: {court_id: name}
    others: [{'id','court_id','start','dur','players':set}]  other scheduled matches
    avail: {court_id: [(open,close), ...]} windows as 'HH:MM' on the match day
    Returns: ('ok', starts_at, warnings) | ('blocked', conflicts) | ('error', msg)
    """
    # Preconditions the controller enforces.
    if match.get("start") is None or match.get("court_id") is None:
        return ("error", "El partido debe estar programado (con día y cancha) para ajustar la hora.")
    if match["court_id"] not in courts:
        return ("error", "La cancha del partido ya no existe.")

    # Day + court preserved; only the time changes.
    day = match["start"].strftime("%Y-%m-%d")
    starts_at = parse(day, new_time)
    ends_at = starts_at + timedelta(minutes=duration)

    conflicts = []

    # 1. Availability window of the court on that day.
    windows = avail.get(match["court_id"], [])
    inside = any(parse(day, o) <= starts_at and ends_at <= parse(day, c) for (o, c) in windows)
    if windows and not inside:
        conflicts.append("Fuera del horario disponible de la cancha.")

    # 2. Court overlap (exclude self by id).
    for o in others:
        if o["id"] == match["id"]:
            continue
        if o["court_id"] != match["court_id"]:
            continue
        if overlap(starts_at, ends_at, o["start"], o["start"] + timedelta(minutes=o["dur"])):
            conflicts.append("La cancha ya tiene un partido en ese horario.")
            break

    # 3. Player overlap across all other scheduled matches (any court).
    mine = match.get("players", set())
    for o in others:
        if o["id"] == match["id"]:
            continue
        if overlap(starts_at, ends_at, o["start"], o["start"] + timedelta(minutes=o["dur"])):
            clash = mine & o.get("players", set())
            if clash:
                conflicts.append(f"Conflicto de jugador: {sorted(clash)[0]} ya juega en ese horario.")
                break

    if conflicts and not force:
        return ("blocked", conflicts)
    return ("ok", starts_at, conflicts)


def main():
    passed = total = 0

    def check(name, cond):
        nonlocal passed, total
        total += 1
        if cond:
            passed += 1
        else:
            print(f"  FAIL: {name}")

    courts = {1: "Cancha 1", 2: "Cancha 2"}
    avail = {1: [("08:00", "23:00")], 2: [("08:00", "23:00")]}

    base = parse("2026-10-25", "09:00")
    m = {"id": 10, "day": "2026-10-25", "court_id": 1, "start": base, "players": {"ana", "bea"}}

    # 1. Clean move keeps the day and applies the new time.
    r = set_time(m, courts, [], avail, "11:30", 60, False)
    check("clean move ok", r[0] == "ok")
    check("day preserved", r[1].strftime("%Y-%m-%d") == "2026-10-25")
    check("time applied", r[1].strftime("%H:%M") == "11:30")

    # 2. Off-grid time (not a slot boundary) is still accepted — renders nearest.
    r = set_time(m, courts, [], avail, "11:37", 60, False)
    check("off-grid accepted", r[0] == "ok" and r[1].strftime("%H:%M") == "11:37")

    # 3. Court overlap on same court blocks (unless forced).
    other_same_court = [{"id": 20, "court_id": 1, "start": parse("2026-10-25", "11:00"), "dur": 60, "players": {"x", "y"}}]
    r = set_time(m, courts, other_same_court, avail, "11:30", 60, False)
    check("court overlap blocks", r[0] == "blocked" and any("cancha" in c.lower() for c in r[1]))

    # 4. Same conflict, forced → goes through, returns the conflicts as warnings.
    r = set_time(m, courts, other_same_court, avail, "11:30", 60, True)
    check("force overrides", r[0] == "ok" and len(r[2]) >= 1)

    # 5. No overlap on a different court → no court conflict.
    other_other_court = [{"id": 21, "court_id": 2, "start": parse("2026-10-25", "11:00"), "dur": 60, "players": {"x", "y"}}]
    r = set_time(m, courts, other_other_court, avail, "11:30", 60, False)
    check("different court clear", r[0] == "ok")

    # 6. Player double-booking blocks even on a different court.
    other_player_clash = [{"id": 22, "court_id": 2, "start": parse("2026-10-25", "11:00"), "dur": 60, "players": {"ana", "z"}}]
    r = set_time(m, courts, other_player_clash, avail, "11:30", 60, False)
    check("player clash blocks", r[0] == "blocked" and any("jugador" in c.lower() for c in r[1]))

    # 7. Adjacent (touching, not overlapping) slot is fine: 10:00-11:00 then 11:00.
    adj = [{"id": 23, "court_id": 1, "start": parse("2026-10-25", "10:00"), "dur": 60, "players": {"x", "y"}}]
    r = set_time(m, courts, adj, avail, "11:00", 60, False)
    check("adjacent slot ok", r[0] == "ok")

    # 8. Outside the court's availability window blocks.
    r = set_time(m, courts, [], avail, "23:30", 60, False)
    check("outside availability blocks", r[0] == "blocked" and any("horario" in c.lower() for c in r[1]))

    # 9. Unscheduled match (no start/court) is rejected with a clear error.
    unsched = {"id": 11, "day": None, "court_id": None, "start": None, "players": set()}
    r = set_time(unsched, courts, [], avail, "11:30", 60, False)
    check("unscheduled rejected", r[0] == "error")

    # 10. Court removed from the tournament → error.
    gone = dict(m)
    gone["court_id"] = 99
    r = set_time(gone, courts, [], avail, "11:30", 60, False)
    check("missing court rejected", r[0] == "error")

    print(f"{passed}/{total}")
    return 0 if passed == total else 1


if __name__ == "__main__":
    raise SystemExit(main())
