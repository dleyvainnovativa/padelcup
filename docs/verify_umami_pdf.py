#!/usr/bin/env python3
"""
Verifier for voleo-umami-pdf.zip — stylized analytics PDF brief.
Cumulative on voleo-umami-report.zip. Adds AnalyticsController::exportPdf, a
branded PDF (logo + tournament name + every category's traffic), route + button.

Part A: logic mirror — bounce%/avg derivations used in the PDF blade.
Part B: structural.
"""
import sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
passed = failed = 0
def check(n, c):
    global passed, failed
    (globals().__setitem__('passed', passed+1) if c else globals().__setitem__('failed', failed+1))
    print(f"  {'ok  ' if c else 'FAIL'} {n}")

print("Part A — logic mirror")
def bounce(s):
    v = int(s.get('visits', 0)); return f"{round(s.get('bounces',0)/v*100)}%" if v > 0 else "—"
def avg(s):
    v = int(s.get('visits', 0))
    if v <= 0: return "—"
    a = round(s.get('totaltime',0)/v); return f"{a//60}m {a%60}s" if a >= 60 else f"{a}s"
check("bounce% computed", bounce({'visits':90,'bounces':36}) == "40%")
check("bounce guards zero visits", bounce({'visits':0}) == "—")
check("avg time < 60s", avg({'visits':10,'totaltime':300}) == "30s")
check("avg time >= 60s formats m/s", avg({'visits':10,'totaltime':900}) == "1m 30s")
check("avg guards zero visits", avg({'visits':0}) == "—")

print("Part B — structural")
ctrl = (ROOT/"app/Http/Controllers/Admin/AnalyticsController.php").read_text()
check("exportPdf method", "public function exportPdf(" in ctrl)
check("pulls per-category paths /t/{slug}/{cslug}", "'/t/' . $tournament->slug . '/' . $c->slug" in ctrl)
check("includes tournament overall", "'/t/' . $tournament->slug" in ctrl)
check("renders admin.analytics.pdf view", "loadView('admin.analytics.pdf'" in ctrl)

blade = (ROOT/"resources/views/admin/analytics/pdf.blade.php").read_text()
check("PDF embeds logo", "img/voleo-logo.png" in blade)
check("PDF shows tournament name + overall path", "$tournament->name" in blade and "$overallPath" in blade)
check("PDF has per-category table", "Por categoría" in blade and "forelse($rows" in blade)
check("PDF handles unconfigured + no-data", "Umami no está configurado" in blade and "sin datos" in blade)

web = (ROOT/"routes/web.php").read_text()
check("route admin.analytics.pdf", "'exportPdf'])->name('analytics.pdf')" in web)

idx = (ROOT/"resources/views/admin/analytics/index.blade.php").read_text()
check("export button on the report page", "admin.analytics.pdf" in idx and "Exportar PDF" in idx)

check("logo asset present", (ROOT/"public/img/voleo-logo.png").exists())

blade2 = (ROOT/"resources/views/admin/analytics/pdf.blade.php").read_text()
check("PDF renames to Página principal del torneo", "Página principal del torneo" in blade2)
check("PDF renders breakdown sections", "($breakdowns ?? [])" in blade2 and "class=\"brk\"" in blade2)
check("PDF has a glossary (Definiciones)", "Definiciones" in blade2 and "class=\"gloss\"" in blade2)
ctrl3 = (ROOT/"app/Http/Controllers/Admin/AnalyticsController.php").read_text()
check("exportPdf passes breakdowns", "'breakdowns' => $this->breakdowns($umami, $overallPath" in ctrl3)

print()
total = passed + failed
print(f"{passed}/{total} checks passed")
sys.exit(1 if failed else 0)
