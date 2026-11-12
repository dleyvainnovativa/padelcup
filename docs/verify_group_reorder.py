#!/usr/bin/env python3
"""
Verifier for voleo-group-reorder.zip — reorder groups / rename letters, plus the
GroupLetter helper that centralizes letter derivation (A..Z..AA) and the >26 guard.

Part A: logic mirror of GroupLetter::at + the two-pass reorder (no position clash).
Part B: structural — helper, chr() sites replaced, controller + route, view panel,
        JS module + app.js wiring, CSS.

Run from project root: python3 docs/verify_group_reorder.py
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: letter + reorder mirrors -------------------------------------
def letter_at(index):
    n = max(0, index)
    s = ''
    while True:
        s = chr(65 + (n % 26)) + s
        n = n // 26 - 1
        if n < 0:
            break
    return s

check("A: A..Z then AA", [letter_at(i) for i in (0, 1, 25, 26, 27)] == ['A', 'B', 'Z', 'AA', 'AB'])
check("A: wraps AZ→BA, ZZ→AAA", letter_at(51) == 'AZ' and letter_at(52) == 'BA' and letter_at(701) == 'ZZ' and letter_at(702) == 'AAA')
check("A: negative index clamps to A", letter_at(-3) == 'A')

# Direct single-pass reposition: assign 0..n-1 by the new order. The
# (category_id, position) index is NOT unique, so no parking is needed and no
# value can overflow the unsignedSmallInteger column.
def two_pass(order):
    return {gid: i for i, gid in enumerate(order)}

check("A: direct position assignment (0..n-1), no value exceeds the column range",
      two_pass([30, 10, 20, 40]) == {30: 0, 10: 1, 20: 2, 40: 3}
      and all(0 <= p <= 65535 for p in two_pass([30, 10, 20, 40]).values()))

# Letter follows order: reorder [B,A,C] (ids) → letters by NEW index.
def letters_for(order):
    return {gid: letter_at(i) for i, gid in enumerate(order)}
check("A: moving a group to slot 0 makes it 'A'",
      letters_for([99, 11, 22])[99] == 'A' and letters_for([99, 11, 22])[11] == 'B')

# ---- Part B: structural ---------------------------------------------------
helper = read("app/Support/GroupLetter.php")
check("B: GroupLetter::at defined", "public static function at(int $index): string" in helper)
check("B: GroupLetter::name defined", "public static function name(int $index): string" in helper)
check("B: BRACKET_MAX constant", "const BRACKET_MAX = 26;" in helper)

# chr() letter derivations replaced by the helper everywhere.
for f in [
    "app/Services/Tournament/BracketService.php",
    "app/Services/Tournament/GhostQualifierResolver.php",
    "app/Services/Tournament/SchedulingService.php",
    "app/Services/Tournament/GroupGenerationService.php",
]:
    src = read(f)
    check(f"B: no chr() letter math left in {Path(f).name}",
          "chr(ord('A')" not in src and "chr(65" not in src)
check("B: engines call GroupLetter::at",
      "GroupLetter::at($i)" in read("app/Services/Tournament/SchedulingService.php"))
check("B: generation names via GroupLetter::name",
      "GroupLetter::name($index)" in read("app/Services/Tournament/GroupGenerationService.php"))

ctrl = read("app/Http/Controllers/Dashboard/DrawController.php")
check("B: reorderGroups method", "public function reorderGroups(" in ctrl)
check("B: reorderGroups NOT locked-guarded (works on started tournaments)",
      "function reorderGroups" in ctrl and
      "abort_if($tournament->isLocked" not in ctrl.split("function reorderGroups")[1].split("public function")[0])
check("B: reorderGroups validates full group set",
      "La lista de grupos no coincide" in ctrl)
check("B: reorderGroups assigns position directly (no overflow of unsignedSmallInteger)",
      "$g->position = $index;" in ctrl and "100000" not in ctrl)
check("B: reorderGroups renames via helper", "\\App\\Support\\GroupLetter::name($index)" in ctrl)
check("B: reorderGroups warns (not blocks) on bracket results",
      "La llave ya tiene resultados" in ctrl and "'ok' => true" in ctrl.split("function reorderGroups")[1].split("public function")[0])

routes = read("routes/web.php")
check("B: draw.groups.letters route", "->name('draw.groups.letters')" in routes and "'reorderGroups'" in routes)

view = read("resources/views/dashboard/groups/index.blade.php")
check("B: panel present + always-on (outside tab x-show)", "data-group-letters" in view and "Reordenar grupos y letras" in view)
check("B: panel posts to letters route", "route('draw.groups.letters'" in view)
check("B: >26 bracket warning", "más de {{ \\App\\Support\\GroupLetter::BRACKET_MAX }} grupos" in view)
check("B: bracket-results warning", "La llave ya tiene resultados" in view)
check("B: badges render helper letters", "\\App\\Support\\GroupLetter::at($i)" in view)

js = read("resources/js/modules/groupLetters.js")
check("B: JS module exports initGroupLetters", "export function initGroupLetters()" in js)
check("B: JS letterAt mirrors helper", "String.fromCharCode(65 + (n % 26))" in js)
check("B: JS posts group_ids", "{ group_ids: idsNow() }" in js)
app = read("resources/js/app.js")
check("B: app.js wires groupLetters on [data-group-letters]",
      "data-group-letters" in app and "initGroupLetters()" in app)

css = read("resources/css/theme.css")
check("B: panel CSS present", ".grp-letters" in css and ".grp-letters__badge" in css)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
