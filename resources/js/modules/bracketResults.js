// Bracket results: tap a (pair-complete) bracket match to enter or edit scores
// in a bottom sheet, reusing the schedule sheet styling. No unschedule action.

import { post } from '../core/http';
import toast from '../core/toast';

export function initBracketResults() {
  const board = document.querySelector('[data-bracket-board]');
  if (!board) return;

  const sheet = buildScoreSheet(saveResult);

  board.querySelectorAll('[data-bracket-match]').forEach((el) => {
    el.addEventListener('click', () => {
      sheet.show({
        context: el.dataset.ctx,
        a: el.dataset.a,
        b: el.dataset.b,
        status: el.dataset.status,
        sets: safeParse(el.dataset.sets),
        confirmUrl: el.dataset.confirmUrl,
        editUrl: el.dataset.editUrl,
      });
    });
  });

  async function saveResult(data, sets, isEdit) {
    const url = isEdit ? data.editUrl : data.confirmUrl;
    try {
      await post(url, { sets });
      toast.success('Resultado guardado.');
      setTimeout(() => window.location.reload(), 450);
      return true;
    } catch (e) {
      const errs = e?.body?.errors;
      return { error: errs ? Object.values(errs).flat().join('\n') : 'No se pudo guardar.' };
    }
  }
}

function safeParse(s) { try { return JSON.parse(s || '[]'); } catch { return []; } }

function buildScoreSheet(onSave) {
  const overlay = document.createElement('div');
  overlay.className = 'sched-sheet-overlay';
  overlay.innerHTML = `
    <div class="sched-sheet" role="dialog" aria-modal="true">
      <div class="sched-sheet__handle"></div>
      <div class="sched-sheet__head">
        <div>
          <div class="sched-sheet__title" data-ctx></div>
          <div class="sched-sheet__sub" data-pairs></div>
        </div>
        <button class="sched-sheet__close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="sched-ctrl-status" data-status></div>
      <div class="sched-ctrl-scoring" data-scoring></div>
    </div>`;
  document.body.appendChild(overlay);

  const ctxEl = overlay.querySelector('[data-ctx]');
  const pairsEl = overlay.querySelector('[data-pairs]');
  const statusEl = overlay.querySelector('[data-status]');
  const scoringEl = overlay.querySelector('[data-scoring]');

  function hide() { overlay.classList.remove('is-open'); }
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  overlay.querySelector('.sched-sheet__close').addEventListener('click', hide);

  function statusBadge(status) {
    if (status === 'played') return '<span class="sched-stat sched-stat--played"><i class="fa-solid fa-circle-check"></i> Resultados guardados</span>';
    if (status === 'playing') return '<span class="sched-stat sched-stat--playing"><i class="fa-solid fa-circle-play"></i> En juego</span>';
    return '<span class="sched-stat sched-stat--scheduled"><i class="fa-regular fa-clock"></i> Pendiente</span>';
  }

  function show(data) {
    ctxEl.textContent = data.context || '';
    pairsEl.textContent = `${data.a} · ${data.b}`;
    statusEl.innerHTML = statusBadge(data.status);

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
      <div class="sched-ctrl-pairlabels"><span>${data.a}</span><span>${data.b}</span></div>
      ${rows}
      <div class="sched-ctrl-err" data-err></div>
      <button class="sched-ctrl-save" data-save>${played ? 'Guardar cambios' : 'Confirmar resultado'}</button>`;

    const saveBtn = scoringEl.querySelector('[data-save]');
    const errBox = scoringEl.querySelector('[data-err]');
    saveBtn.addEventListener('click', async () => {
      errBox.textContent = '';
      const collected = [0,1,2].map((i) => [
        scoringEl.querySelector(`[data-set="${i}"][data-side="0"]`).value,
        scoringEl.querySelector(`[data-set="${i}"][data-side="1"]`).value,
      ]).filter((s) => s[0] !== '' || s[1] !== '');
      if (collected.length < 2) { errBox.textContent = 'Captura al menos 2 sets.'; return; }
      saveBtn.disabled = true; saveBtn.textContent = 'Guardando…';
      const res = await onSave(data, collected, played);
      if (res && res.error) {
        errBox.textContent = res.error;
        saveBtn.disabled = false;
        saveBtn.textContent = played ? 'Guardar cambios' : 'Confirmar resultado';
      }
    });

    overlay.classList.add('is-open');
  }

  return { show, hide };
}