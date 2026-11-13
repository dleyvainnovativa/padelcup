#!/usr/bin/env python3
"""
Verifier for voleo-public-hidden-category.zip — a per-category "Ocultar en vistas
públicas" toggle. Hidden categories drop out of the public calendar, pantalla and
the public category list, but still show in the admin dashboard and remain
reachable by direct link (unlisted, not forbidden).

Part A: logic mirrors. Part B: structural.

Run from project root: python3 docs/verify_public_hidden.py
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
checks = []
def check(name, cond): checks.append((name, bool(cond)))
def read(p): return (ROOT / p).read_text(encoding="utf-8")

# ---- Part A: mirrors ------------------------------------------------------
def public_visible(categories):
    return [c for c in categories if not c.get("public_hidden")]
cats = [{"id": 1, "public_hidden": False}, {"id": 2, "public_hidden": True}, {"id": 3}]
check("A: scope keeps only non-hidden", [c["id"] for c in public_visible(cats)] == [1, 3])
check("A: default (absent flag) is visible", {"id": 9} in public_visible([{"id": 9}]))

def boolean_coerce(present, value):
    # mirror of $this->boolean('public_hidden'): absent/"0"/false → False, "1"/true → True
    if not present:
        return False
    return value in (True, 1, "1", "true", "on")
check("A: unchecked box coerces to false (persists un-hide)", boolean_coerce(False, None) is False)
check("A: checked box coerces to true", boolean_coerce(True, "1") is True)

# ---- Part B: structural ---------------------------------------------------
migs = list((ROOT / "database/migrations").glob("*public_hidden*"))
mig = migs[0].read_text() if migs else ""
check("B: migration adds boolean default false",
      "public_hidden" in mig and "->boolean('public_hidden')->default(false)" in mig)

model = read("app/Models/Category.php")
check("B: model fillable + boolean cast",
      "'public_hidden'," in model and "'public_hidden' => 'boolean'," in model)
check("B: publicVisible scope filters hidden",
      "public function scopePublicVisible($query)" in model and "->where('public_hidden', false)" in model)

req = read("app/Http/Requests/Category/StoreCategoryRequest.php")
check("B: request validates public_hidden", "'public_hidden' => ['boolean']," in req)
check("B: prepareForValidation coerces the checkbox",
      "protected function prepareForValidation(): void" in req and "'public_hidden' => $this->boolean('public_hidden')," in req)

for f in ("edit", "create"):
    v = read(f"resources/views/dashboard/categories/{f}.blade.php")
    check(f"B: {f} form has the toggle", 'name="public_hidden"' in v and "Ocultar en vistas públicas" in v)
check("B: edit form reflects stored value", '@checked($category->public_hidden)' in read("resources/views/dashboard/categories/edit.blade.php"))

pub = read("app/Http/Controllers/PublicTournamentController.php")
check("B: public queries scoped (calendar x2, pantalla, list = 4)",
      pub.count("->publicVisible()") == 4)

# Admin side must NOT be scoped — the dashboard shows all categories.
dash = read("app/Http/Controllers/Dashboard/CategoryController.php")
check("B: admin CategoryController not scoped", "publicVisible()" not in dash)

passed = sum(1 for _, ok in checks if ok)
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
print(f"\n{passed}/{len(checks)} checks passed")
sys.exit(0 if passed == len(checks) else 1)
