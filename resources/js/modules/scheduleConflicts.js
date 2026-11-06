/**
 * "Revisar conflictos" bottom sheet. Fetches the audit report (order per
 * category, 3+/day, player conflicts) as a rendered HTML fragment and shows it
 * in an accordion. Re-fetches on EVERY open so it reflects the current board
 * after drags / moves — never a stale snapshot, no full page reload.
 */
export function initScheduleConflicts() {
  const trigger = document.querySelector('[data-conflicts-url]');
  if (!trigger) return;

  const url = trigger.dataset.conflictsUrl;

  const overlay = document.createElement('div');
  overlay.className = 'sched-sheet-overlay';
  overlay.innerHTML = `
    <div class="sched-sheet" role="dialog" aria-modal="true" style="max-height:85vh;overflow-y:auto;">
      <div class="sched-sheet__handle"></div>
      <div class="sched-sheet__head">
        <div>
          <div class="sched-sheet__title">Revisar conflictos</div>
          <div class="sched-sheet__sub">Orden, carga por día y choques de jugadores</div>
        </div>
        <button class="sched-sheet__close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="sc-list"></div>
    </div>`;
  document.body.appendChild(overlay);

  const listEl = overlay.querySelector('.sc-list');
  const hide = () => overlay.classList.remove('is-open');
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  overlay.querySelector('.sched-sheet__close').addEventListener('click', hide);

  trigger.addEventListener('click', async () => {
    overlay.classList.add('is-open');
    listEl.innerHTML = '<div class="sched-sheet__empty">Cargando…</div>';
    try {
      const res = await fetch(url, { headers: { 'Accept': 'text/html' }, cache: 'no-store' });
      if (!res.ok) throw new Error('bad status');
      listEl.innerHTML = await res.text();
    } catch (e) {
      listEl.innerHTML = '<div class="sched-sheet__empty">No se pudo cargar el reporte de conflictos.</div>';
    }
  });
}
