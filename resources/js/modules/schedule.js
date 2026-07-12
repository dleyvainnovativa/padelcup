// Custom court-grid scheduler (Playtomic-style): courts as columns, time slots
// as rows, one day at a time.
//
//  - Desktop: drag matches onto cells, OR click an empty cell to open the sheet.
//  - Mobile: tap an empty cell → bottom sheet of unscheduled matches → tap one.
//
// Saves immediately; conflicts return 422 and we offer to force (override).

import { post } from '../core/http';
import toast from '../core/toast';

const isTouch = window.matchMedia('(hover: none), (pointer: coarse)').matches;
const MONTHS = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
const DOW = ['dom','lun','mar','mié','jue','vie','sáb'];

export function initSchedule() {
  const board = document.querySelector('[data-sched-board]');
  if (!board) return;

  const cfg = JSON.parse(board.dataset.schedConfig);
  const sheet = buildSheet();

  // --- Core place/unplace ------------------------------------------
  async function place(matchId, court, date, slot, force = false) {
    const payload = { match_id: matchId, court_id: court, starts_at: `${date}T${slot}:00`, duration: cfg.duration };
    if (force) payload.force = true;
    try {
      await post(cfg.placeUrl, payload);
      toast.success('Partido programado.');
      setTimeout(() => window.location.reload(), 450);
      return true;
    } catch (e) {
      const conflicts = e?.body?.conflicts;
      if (conflicts?.length && !force) {
        return { conflicts };
      }
      toast.error('No se pudo programar.');
      return false;
    }
  }

  async function unplace(matchId) {
    try {
      await post(cfg.unplaceUrl, { match_id: matchId });
      toast.success('Partido quitado.');
      setTimeout(() => window.location.reload(), 400);
    } catch (_) { toast.error('No se pudo quitar.'); }
  }

  board.querySelectorAll('[data-unplace]').forEach((btn) => {
    btn.addEventListener('click', (e) => { e.stopPropagation(); unplace(btn.dataset.unplace); });
  });

  // --- Multi-select unschedule -------------------------------------
  // A "selection mode" toggle turns match clicks into select/deselect instead of
  // opening the control sheet. A floating bar shows the count + the action.
  const selection = initMultiSelect(board, cfg);

  // --- Scheduled match click → match-control sheet ------------------
  const ctrlSheet = buildControlSheet({
    onUnplace: unplace,
    onSaveResult: saveResult,
  });

  board.querySelectorAll('.sched-match').forEach((el) => {
    el.addEventListener('click', (e) => {
      // The × remove button has its own handler.
      if (e.target.closest('[data-unplace]')) return;
      e.stopPropagation();
      // In selection mode, clicking toggles selection instead of opening the sheet.
      if (selection.isActive()) { selection.toggle(el); return; }
      const id = el.dataset.matchId;
      const data = cfg.scheduled[id];
      if (data) ctrlSheet.show(data);
    });
  });

  // POST a result (confirm new, or edit existing) from the control sheet.
  async function saveResult(matchData, sets, isEdit) {
    const url = isEdit ? matchData.editUrl : matchData.confirmUrl;
    try {
      await post(url, { sets });
      toast.success('Resultado guardado.');
      setTimeout(() => window.location.reload(), 450);
      return true;
    } catch (e) {
      const errs = e?.body?.errors;
      const msg = errs ? Object.values(errs).flat().join('\n') : 'No se pudo guardar el resultado.';
      return { error: msg };
    }
  }

  // --- Empty-cell click → bottom sheet (all devices) ----------------
  board.querySelectorAll('[data-cell]').forEach((cell) => {
    cell.addEventListener('click', (e) => {
      // Ignore clicks that land on an existing match block.
      if (e.target.closest('.sched-match')) return;
      if (selection.isActive()) return; // selection mode: cells are inert
      if (!cellIsFree(cell)) return;
      openSheetFor(cell);
    });
  });

  // --- Desktop drag (bonus; sheet still available) ------------------
  if (!isTouch) initDrag(board, place, selection);

  // --- Bottom sheet -------------------------------------------------
  function openSheetFor(cell) {
    const court = cell.dataset.court;
    const slot = cell.dataset.slot;
    const date = visibleDate(cell);
    if (!date) return;

    const d = new Date(date + 'T00:00:00');
    const title = `${cap(DOW[d.getDay()])} ${d.getDate()} ${MONTHS[d.getMonth()]} · ${slot}`;
    const subtitle = cfg.courts[court] || 'Cancha';

    // Which players are already booked in this date+slot? (to flag conflicts)
    const busyPlayers = bookedPlayers(date, slot, court);

    sheet.show(title, subtitle, cfg.unscheduled, busyPlayers, async (matchId, forceBtn) => {
      const res = await place(matchId, court, date, slot, false);
      if (res === true) { sheet.hide(); return; }
      if (res && res.conflicts) {
        // Show conflicts inline in the sheet and let them force.
        forceBtn(res.conflicts, async () => {
          const ok = await place(matchId, court, date, slot, true);
          if (ok === true) sheet.hide();
        });
      }
    });
  }

  // Players already scheduled at this date+slot anywhere (from rendered grid).
  function bookedPlayers(date, slot) {
    // We don't have all scheduled players client-side; rely on server conflict
    // check. Return empty → no pre-flagging; conflicts surface on assign.
    return new Set();
  }

  // --- helpers ------------------------------------------------------
  // Each day is its own <tbody> (x-show toggled); a cell carries a static
  // data-date and is "visible" when its tbody is shown (offsetParent != null).
  function cellIsFree(cell) {
    return isVisible(cell) && !cell.querySelector('.sched-match');
  }
  function isVisible(cell) {
    return cell.offsetParent !== null;
  }
  function visibleDate(cell) {
    return cell.dataset.date || null;
  }
}

// --- Multi-select unschedule -----------------------------------------
// Adds a "Seleccionar" toggle. While active, clicking scheduled matches selects
// them; a floating bar shows the count and unschedules them in one request.
function initMultiSelect(board, cfg) {
  const selected = new Set();
  let active = false;

  // Toggle button — placed next to the board if a host exists, else floated.
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'sched-select-toggle';
  toggle.innerHTML = '<i class="fa-solid fa-object-group"></i> Seleccionar';

  const host = document.querySelector('[data-sched-actions]') || board.parentElement;
  if (host) host.insertBefore(toggle, host.firstChild);

  // Floating action bar.
  const bar = document.createElement('div');
  bar.className = 'sched-selbar';
  bar.innerHTML = `
    <span class="sched-selbar__count"><strong data-sel-count>0</strong> seleccionados</span>
    <button type="button" class="sched-selbar__all" data-sel-all>Seleccionar día</button>
    <button type="button" class="sched-selbar__clear" data-sel-clear>Limpiar</button>
    <button type="button" class="sched-selbar__go" data-sel-go>
      <i class="fa-solid fa-eraser"></i> Quitar del calendario
    </button>`;
  document.body.appendChild(bar);

  const countEl = bar.querySelector('[data-sel-count]');
  const goBtn = bar.querySelector('[data-sel-go]');

  function render() {
    countEl.textContent = selected.size;
    goBtn.disabled = selected.size === 0;
    bar.classList.toggle('is-open', active);
  }

  function toggleMatch(el) {
    const id = el.dataset.matchId;
    if (!id) return;
    if (selected.has(id)) { selected.delete(id); el.classList.remove('is-selected'); }
    else { selected.add(id); el.classList.add('is-selected'); }
    render();
  }

  function clear() {
    selected.forEach((id) => {
      const el = board.querySelector(`.sched-match[data-match-id="${id}"]`);
      if (el) el.classList.remove('is-selected');
    });
    selected.clear();
    render();
  }

  function setActive(on) {
    active = on;
    board.classList.toggle('is-selecting', on);
    toggle.classList.toggle('is-on', on);
    toggle.innerHTML = on
      ? '<i class="fa-solid fa-xmark"></i> Salir de selección'
      : '<i class="fa-solid fa-object-group"></i> Seleccionar';
    if (!on) clear();
    render();
  }

  toggle.addEventListener('click', () => setActive(!active));

  // Select every VISIBLE scheduled match (i.e. the current day).
  bar.querySelector('[data-sel-all]').addEventListener('click', () => {
    board.querySelectorAll('.sched-match').forEach((el) => {
      if (el.offsetParent === null) return; // hidden day
      const id = el.dataset.matchId;
      if (id && !selected.has(id)) { selected.add(id); el.classList.add('is-selected'); }
    });
    render();
  });

  bar.querySelector('[data-sel-clear]').addEventListener('click', clear);

  goBtn.addEventListener('click', async () => {
    if (!selected.size) return;
    const n = selected.size;
    if (!window.confirm(`¿Quitar ${n} ${n === 1 ? 'partido' : 'partidos'} del calendario?`)) return;

    goBtn.disabled = true;
    goBtn.textContent = 'Quitando…';
    try {
      await post(cfg.unplaceManyUrl, { match_ids: [...selected] });
      toast.success(`${n} ${n === 1 ? 'partido quitado' : 'partidos quitados'}.`);
      setTimeout(() => window.location.reload(), 450);
    } catch (_) {
      toast.error('No se pudieron quitar.');
      goBtn.disabled = false;
      goBtn.innerHTML = '<i class="fa-solid fa-eraser"></i> Quitar del calendario';
    }
  });

  // Escape leaves selection mode.
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && active) setActive(false);
  });

  render();
  return { isActive: () => active, toggle: toggleMatch, clear };
}

// --- Desktop drag ---------------------------------------------------
function initDrag(board, place, selection) {
  let draggedId = null;
  board.querySelectorAll('.sched-match, .sched-chip').forEach((el) => {
    el.addEventListener('dragstart', (e) => {
      if (selection && selection.isActive()) { e.preventDefault(); return; }
      draggedId = el.dataset.matchId; el.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move';
    });
    el.addEventListener('dragend', () => el.classList.remove('dragging'));
  });
  board.querySelectorAll('[data-cell]').forEach((cell) => {
    cell.addEventListener('dragover', (e) => {
      if (cell.offsetParent !== null && !cell.querySelector('.sched-match')) { e.preventDefault(); cell.classList.add('drop-target'); }
    });
    cell.addEventListener('dragleave', () => cell.classList.remove('drop-target'));
    cell.addEventListener('drop', async (e) => {
      e.preventDefault(); cell.classList.remove('drop-target');
      if (!draggedId) return;
      const date = cell.dataset.date;
      const res = await place(draggedId, cell.dataset.court, date, cell.dataset.slot, false);
      if (res && res.conflicts) {
        if (window.confirm(res.conflicts.join('\n') + '\n\n¿Programar de todos modos?')) {
          await place(draggedId, cell.dataset.court, date, cell.dataset.slot, true);
        }
      }
    });
  });
}

// --- Bottom sheet component -----------------------------------------
function buildSheet() {
  const overlay = document.createElement('div');
  overlay.className = 'sched-sheet-overlay';
  overlay.innerHTML = `
    <div class="sched-sheet" role="dialog" aria-modal="true">
      <div class="sched-sheet__handle"></div>
      <div class="sched-sheet__head">
        <div>
          <div class="sched-sheet__title"></div>
          <div class="sched-sheet__sub"></div>
        </div>
        <button class="sched-sheet__close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="sched-sheet__label">Partidos disponibles</div>
      <div class="sched-sheet__list"></div>
    </div>`;
  document.body.appendChild(overlay);

  const sheetEl = overlay.querySelector('.sched-sheet');
  const titleEl = overlay.querySelector('.sched-sheet__title');
  const subEl = overlay.querySelector('.sched-sheet__sub');
  const listEl = overlay.querySelector('.sched-sheet__list');

  function hide() { overlay.classList.remove('is-open'); }
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  overlay.querySelector('.sched-sheet__close').addEventListener('click', hide);

  function show(title, subtitle, matches, busyPlayers, onPick) {
    titleEl.textContent = title;
    subEl.textContent = subtitle;
    listEl.innerHTML = '';

    if (!matches.length) {
      listEl.innerHTML = '<div class="sched-sheet__empty">No hay partidos sin programar.</div>';
      overlay.classList.add('is-open');
      return;
    }

    // Group by category.
    const groups = {};
    matches.forEach((m) => { (groups[m.category] ||= []).push(m); });

    Object.entries(groups).forEach(([category, ms]) => {
      const g = document.createElement('div');
      g.className = 'sched-sheet__group';
      g.innerHTML = `<div class="sched-sheet__group-title">${category}</div>`;
      ms.forEach((m) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'sched-sheet__item';
        item.innerHTML = `
          <span class="sched-sheet__item-main">
            <span class="sched-sheet__item-ctx">${m.context || ''}</span>
            ${m.a} <span class="vs">vs</span> ${m.b}
          </span>
          <span class="sched-sheet__chevron"><i class="fa-solid fa-chevron-right"></i></span>`;
        const errBox = document.createElement('div');
        errBox.className = 'sched-sheet__err';

        item.addEventListener('click', () => {
          item.disabled = true;
          onPick(m.id, (conflicts, doForce) => {
            item.disabled = false;
            errBox.innerHTML = conflicts.map((c) => `<div>⚠ ${c}</div>`).join('')
              + '<button type="button" class="sched-sheet__force">Programar de todos modos</button>';
            errBox.querySelector('.sched-sheet__force').addEventListener('click', doForce);
          });
        });

        g.appendChild(item);
        g.appendChild(errBox);
      });
      listEl.appendChild(g);
    });

    overlay.classList.add('is-open');
  }

  return { show, hide };
}

function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

// --- Match-control sheet (scores + unschedule) ----------------------
function buildControlSheet({ onUnplace, onSaveResult }) {
  const overlay = document.createElement('div');
  overlay.className = 'sched-sheet-overlay';
  overlay.innerHTML = `
    <div class="sched-sheet" role="dialog" aria-modal="true">
      <div class="sched-sheet__handle"></div>
      <div class="sched-sheet__head">
        <div>
          <div class="sched-sheet__title" data-ctrl-ctx></div>
          <div class="sched-sheet__sub" data-ctrl-pairs></div>
        </div>
        <button class="sched-sheet__close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="sched-ctrl-status" data-ctrl-status></div>
      <div class="sched-ctrl-scoring" data-ctrl-scoring></div>
      <button class="sched-ctrl-action" data-ctrl-unplace>
        <span class="sched-ctrl-action__icon"><i class="fa-solid fa-eraser"></i></span>
        <span>
          <strong>Quitar de la programación</strong>
          <small>El partido vuelve a la lista de pendientes</small>
        </span>
        <i class="fa-solid fa-chevron-right" style="margin-left:auto;color:var(--text-faint);"></i>
      </button>
    </div>`;
  document.body.appendChild(overlay);

  const ctxEl = overlay.querySelector('[data-ctrl-ctx]');
  const pairsEl = overlay.querySelector('[data-ctrl-pairs]');
  const statusEl = overlay.querySelector('[data-ctrl-status]');
  const scoringEl = overlay.querySelector('[data-ctrl-scoring]');
  const unplaceBtn = overlay.querySelector('[data-ctrl-unplace]');

  let current = null;

  function hide() { overlay.classList.remove('is-open'); }
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  overlay.querySelector('.sched-sheet__close').addEventListener('click', hide);

  unplaceBtn.addEventListener('click', () => { if (current) { hide(); onUnplace(current.id); } });

  function statusBadge(status, played) {
    if (status === 'played') return '<span class="sched-stat sched-stat--played"><i class="fa-solid fa-circle-check"></i> Resultados guardados</span>';
    if (status === 'playing') return '<span class="sched-stat sched-stat--playing"><i class="fa-solid fa-circle-play"></i> En juego</span>';
    return '<span class="sched-stat sched-stat--scheduled"><i class="fa-regular fa-clock"></i> Programado</span>';
  }

  function renderScoring(data) {
    if (!data.ready) {
      scoringEl.innerHTML = '<div class="sched-ctrl-note">Las parejas se definen al confirmar la ronda anterior. No se pueden capturar resultados todavía.</div>';
      return;
    }
    const played = data.status === 'played';
    const sets = data.sets && data.sets.length ? data.sets : [['',''],['',''],['','']];
    const rows = [0,1,2].map((i) => {
      const a = sets[i]?.[0] ?? '';
      const b = sets[i]?.[1] ?? '';
      return `
        <div class="sched-set-row">
          <span class="sched-set-row__label">Set ${i+1}</span>
          <input type="number" min="0" max="7" value="${a}" data-set="${i}" data-side="0" class="sched-set-input">
          <span class="sched-set-row__dash">-</span>
          <input type="number" min="0" max="7" value="${b}" data-set="${i}" data-side="1" class="sched-set-input">
        </div>`;
    }).join('');

    scoringEl.innerHTML = `
      <div class="sched-ctrl-action__title"><i class="fa-solid fa-pen"></i> ${played ? 'Editar resultados' : 'Capturar resultados'}</div>
      <div class="sched-ctrl-pairlabels">
        <span>${data.a}</span><span>${data.b}</span>
      </div>
      ${rows}
      <div class="sched-ctrl-err" data-ctrl-err></div>
      <button class="sched-ctrl-save" data-ctrl-save>${played ? 'Guardar cambios' : 'Confirmar resultado'}</button>`;

    scoringEl.querySelector('[data-ctrl-save]').addEventListener('click', async () => {
      const saveBtn = scoringEl.querySelector('[data-ctrl-save]');
      const errBox = scoringEl.querySelector('[data-ctrl-err]');
      errBox.textContent = '';
      // Collect non-empty sets.
      const collected = [0,1,2].map((i) => {
        const a = scoringEl.querySelector(`[data-set="${i}"][data-side="0"]`).value;
        const b = scoringEl.querySelector(`[data-set="${i}"][data-side="1"]`).value;
        return [a, b];
      }).filter((s) => s[0] !== '' || s[1] !== '');

      if (collected.length < 2) { errBox.textContent = 'Captura al menos 2 sets.'; return; }

      saveBtn.disabled = true;
      saveBtn.textContent = 'Guardando…';
      const res = await onSaveResult(data, collected, played);
      if (res && res.error) {
        errBox.textContent = res.error;
        saveBtn.disabled = false;
        saveBtn.textContent = played ? 'Guardar cambios' : 'Confirmar resultado';
      }
    });
  }

  function show(data) {
    current = data;
    ctxEl.textContent = data.context || '';
    pairsEl.textContent = `${data.a} · ${data.b}`;
    statusEl.innerHTML = statusBadge(data.status);
    renderScoring(data);
    overlay.classList.add('is-open');
  }

  return { show, hide };
}