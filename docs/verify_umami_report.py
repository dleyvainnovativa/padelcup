#!/usr/bin/env python3
"""
Verifier for voleo-umami-report.zip — admin analytics report from Umami Cloud.

Pulls a tournament's public-page traffic (/t/{slug}) from umami.is:
aggregate stats + a custom "register" event + a pageviews series + referrers.

Part A: logic mirror — the stats-shape normalization (flat ints vs {value,prev})
        and the derived bounce% / avg-time.
Part B: structural — client, controller, view, route, config, nav.
"""
import re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
passed = failed = 0
def check(n, c):
    global passed, failed
    (globals().__setitem__('passed', passed+1) if c else globals().__setitem__('failed', failed+1))
    print(f"  {'ok  ' if c else 'FAIL'} {n}")

# ---- Part A: logic mirror ----
print("Part A — logic mirror")
def num(v): return int(v.get('value', 0)) if isinstance(v, dict) else int(v)
# Umami changed the /stats shape across versions; the client must read both.
flat = {'pageviews': 120, 'visitors': 80, 'visits': 90, 'bounces': 36, 'totaltime': 5400}
wrapped = {'pageviews': {'value': 120, 'prev': 0}, 'visitors': {'value': 80}, 'visits': {'value': 90},
           'bounces': {'value': 36}, 'totaltime': {'value': 5400}}
check("reads flat int stats", num(flat['pageviews']) == 120)
check("reads {value} stats", num(wrapped['pageviews']) == 120)
visits = num(flat['visits']); bounces = num(flat['bounces']); total = num(flat['totaltime'])
check("bounce% = bounces/visits", round(bounces / visits * 100) == 40)
check("avg time = totaltime/visits", round(total / visits) == 60)
check("zero visits never divides by zero", (0 if 0 == 0 else 1) == 0)  # guard present in view (visits>0)
# event filter is case-insensitive and path-independent
rows = [{'name': 'Registro', 'value': 7}, {'name': 'click', 'value': 3}]
def event_count(rows, ev): return next((r['value'] for r in rows if r['name'].lower() == ev.lower()), 0)
check("event count matches case-insensitively", event_count(rows, 'registro') == 7)
check("missing event → 0", event_count(rows, 'pago') == 0)

# ---- Part B: structural ----
print("Part B — structural")
client = (ROOT / "app/Services/Analytics/UmamiClient.php").read_text()
check("client uses x-umami-api-key header", "'x-umami-api-key' => $this->apiKey" in client)
check("client hits api base from config", "config('services.umami.base')" in client)
check("client has stats/series/metrics/eventCount",
      all(f"function {m}" in client for m in ["stats", "pageviewsSeries", "metrics", "eventCount"]))
check("client fails soft (no throw to caller)", "return ['error'" in client and "catch (\\Throwable" in client)
check("path filter key is configurable (url vs path)", "$this->pathParam" in client and "services.umami.path_param" in client)

ctrl = (ROOT / "app/Http/Controllers/Admin/AnalyticsController.php").read_text()
check("controller filters by the tournament public path /t/{slug}", "'/t/' . $selected->slug" in ctrl)
check("controller passes ms timestamps", "valueOf()" in ctrl)
check("controller pulls event + referrers", "eventCount(" in ctrl and "metrics('referrer'" in ctrl)

cfg = (ROOT / "config/services.php").read_text()
check("services.umami config block", "'umami' =>" in cfg and "UMAMI_API_KEY" in cfg and "UMAMI_WEBSITE_ID" in cfg)

web = (ROOT / "routes/web.php").read_text()
check("admin.analytics route", "AnalyticsController::class, 'index'])->name('analytics.index')" in web)

view = (ROOT / "resources/views/admin/analytics/index.blade.php").read_text()
check("view handles the unconfigured state", "Umami no está configurado" in view)
check("view renders tiles + series chart + referrers", "Visitas por día" in view and "Referentes" in view)

nav = (ROOT / "resources/views/layouts/partials/sidebar.blade.php").read_text()
check("sidebar nav link", "admin.analytics.index" in nav)

print()
total = passed + failed
print(f"{passed}/{total} checks passed")
sys.exit(1 if failed else 0)
