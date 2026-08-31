/**
 * "Validar horarios" bottom sheet. Fetches per-player validation of scheduled
 * matches against their preferred-schedule rules, renders an accordion list:
 * players with problems first (auto-expanded), then pending, then all-ok.
 * A search filters by player name.
 */
export function initScheduleValidation() {
  const trigger = document.querySelector('[data-validate-schedule]');
  if (!trigger) return;

  const url = trigger.dataset.validateSchedule;
  let data = null;

  const overlay = document.createElement('div');
  overlay.className = 'sched-sheet-overlay';
  overlay.innerHTML = `
    <div class="sched-sheet" role="dialog" aria-modal="true" style="max-height:85vh;overflow-y:auto;">
      <div class="sched-sheet__handle"></div>
      <div class="sched-sheet__head">
        <div>
          <div class="sched-sheet__title">Validar horarios</div>
          <div class="sched-sheet__sub">Solicitudes de horario</div>
        </div>
        <button class="sched-sheet__close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="sched-sheet__search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="sched-sheet__search-input sv-search" placeholder="Buscar jugador…" autocomplete="off">
      </div>
      <div class="sv-list"></div>
    </div>`;
  document.body.appendChild(overlay);

  const listEl = overlay.querySelector('.sv-list');
  const searchEl = overlay.querySelector('.sv-search');
  const hide = () => overlay.classList.remove('is-open');
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  overlay.querySelector('.sched-sheet__close').addEventListener('click', hide);
  searchEl.addEventListener('input', () => render(searchEl.value.trim().toLowerCase()));

  trigger.addEventListener('click', async () => {
    overlay.classList.add('is-open');
    if (data === null) {
      listEl.innerHTML = '<div class="sched-sheet__empty">Cargando…</div>';
      try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        data = json.players || [];
      } catch (e) {
        listEl.innerHTML = '<div class="sched-sheet__empty">No se pudo cargar la validación.</div>';
        return;
      }
    }
    searchEl.value = '';
    render('');
  });

  function statusPill(p) {
    if (p.errors > 0) return `<span class="sv-pill sv-pill--error">${p.errors} con problema</span>`;
    if (p.pending > 0) return `<span class="sv-pill sv-pill--pending">${p.pending} sin programar</span>`;
    return `<span class="sv-pill sv-pill--ok">Todo correcto</span>`;
  }

  function matchRow(m) {
    const cls = m.status === 'ok' ? 'ok' : (m.status === 'pending' ? 'pending' : 'error');
    const icon = m.status === 'ok' ? 'fa-circle-check'
               : (m.status === 'pending' ? 'fa-clock' : 'fa-circle-xmark');
    const when = m.when ? `${m.when}${m.court ? ' · ' + m.court : ''}` : 'Sin horario';
    return `
      <div class="sv-match sv-match--${cls}">
        <i class="fa-solid ${icon} sv-match__icon"></i>
        <div class="sv-match__body">
          <div class="sv-match__ctx">${m.context || ''}</div>
          <div class="sv-match__label">${m.label}</div>
          <div class="sv-match__when">${when} <span class="sv-match__reason">· ${m.reason}</span></div>
        </div>
      </div>`;
  }

  function render(needle) {
    if (!data || !data.length) {
      listEl.innerHTML = '<div class="sched-sheet__empty">No hay jugadores con solicitudes de horario.</div>';
      return;
    }
    const shown = needle ? data.filter((p) => p.name.toLowerCase().includes(needle)) : data;
    if (!shown.length) {
      listEl.innerHTML = '<div class="sched-sheet__empty">Sin coincidencias.</div>';
      return;
    }

    listEl.innerHTML = shown.map((p, i) => {
      const open = p.errors > 0; // auto-expand players with problems
      return `
        <div class="sv-player ${open ? 'is-open' : ''}" data-sv-player="${i}">
          <button type="button" class="sv-player__head" data-sv-toggle>
            <span class="sv-player__name">${p.name}</span>
            ${statusPill(p)}
            <i class="fa-solid fa-chevron-down sv-player__chev"></i>
          </button>
          <div class="sv-player__body">
            ${p.rules.length ? `<div class="sv-player__rules">${p.rules.join(' · ')}</div>` : ''}
            ${p.matches.map(matchRow).join('')}
          </div>
        </div>`;
    }).join('');

    listEl.querySelectorAll('[data-sv-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => btn.closest('.sv-player').classList.toggle('is-open'));
    });
  }
}
