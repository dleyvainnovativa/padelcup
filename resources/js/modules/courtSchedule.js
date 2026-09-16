// Court schedule UX: inline-edit a window (press-to-edit, always on) and a
// per-court select mode for multi-delete.
//
// Interaction rules:
//   - Normal: click a window tag → it becomes an inline start/end editor
//     (save / cancel). PATCHes availability.update, then reloads.
//   - Select mode (toggled per court): tags become checkboxes; click toggles
//     selection instead of editing. "Eliminar (N)" POSTs availability.bulkDestroy.
//   The two never collide because click does different things per mode.

import { post } from '../core/http';
import toast from '../core/toast';

export function initCourtSchedule() {
  // Wire every court block on the page, regardless of how venues are nested.
  // (Scoping to a single wrapper broke when a second venue's courts fell
  // outside it.) Guard against double-wiring if this ever runs twice.
  document.querySelectorAll('[data-court-block]').forEach((block) => {
    if (block.dataset.reorderWired === '1') return;
    block.dataset.reorderWired = '1';
    wireCourt(block);
  });
}

function wireCourt(block) {
  const bulkUrl = block.dataset.bulkDeleteUrl;
  const updateTpl = block.dataset.updateTpl; // route with __ID__ placeholder

  const selectBtn = block.querySelector('[data-select-toggle]');
  const bulkBar = block.querySelector('[data-bulk-bar]');
  const bulkDeleteBtn = block.querySelector('[data-bulk-delete]');
  const countEl = block.querySelector('[data-bulk-count]');
  const tags = () => [...block.querySelectorAll('[data-win-tag]')];

  let selectMode = false;

  function refreshCount() {
    const n = tags().filter((t) => t.classList.contains('is-selected')).length;
    if (countEl) countEl.textContent = n;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = n === 0;
  }

  function setSelectMode(on) {
    selectMode = on;
    block.classList.toggle('is-selecting', on);
    if (bulkBar) bulkBar.hidden = !on;
    if (selectBtn) {
      selectBtn.classList.toggle('is-active', on);
      selectBtn.setAttribute('aria-pressed', String(on));
    }
    if (!on) {
      tags().forEach((t) => t.classList.remove('is-selected'));
      refreshCount();
    }
  }

  selectBtn?.addEventListener('click', () => setSelectMode(!selectMode));

  // Tag clicks: edit (normal) or toggle-select (select mode).
  block.addEventListener('click', (e) => {
    const tag = e.target.closest('[data-win-tag]');
    if (!tag || !block.contains(tag)) return;

    // The inline per-tag "×" (single delete form) still works on its own.
    if (e.target.closest('[data-win-x]')) return;
    // Don't hijack clicks already inside an open editor.
    if (e.target.closest('[data-win-editor]')) return;

    if (selectMode) {
      e.preventDefault();
      tag.classList.toggle('is-selected');
      refreshCount();
      return;
    }

    openEditor(tag, updateTpl);
  });

  // Bulk delete.
  bulkDeleteBtn?.addEventListener('click', async () => {
    const ids = tags()
      .filter((t) => t.classList.contains('is-selected'))
      .map((t) => t.dataset.winId);
    if (!ids.length) return;

    bulkDeleteBtn.disabled = true;
    try {
      await post(bulkUrl, { ids });
      toast.success(ids.length === 1 ? 'Horario eliminado.' : `${ids.length} horarios eliminados.`);
      setTimeout(() => window.location.reload(), 500);
    } catch (_) {
      toast.error('No se pudieron eliminar los horarios.');
      bulkDeleteBtn.disabled = false;
    }
  });
}

// Turn a tag into an inline start/end time editor.
function openEditor(tag, updateTpl) {
  if (tag.querySelector('[data-win-editor]')) return; // already editing
  const id = tag.dataset.winId;
  const start = tag.dataset.winStart; // "HH:MM"
  const end = tag.dataset.winEnd;

  // Preserve the label so we can restore on cancel.
  const original = tag.innerHTML;

  const editor = document.createElement('span');
  editor.setAttribute('data-win-editor', '');
  editor.className = 'court-win-editor';
  editor.innerHTML = `
    <input type="time" value="${start}" data-edit-start class="court-win-editor__time" aria-label="Inicio">
    <span class="court-win-editor__arrow">→</span>
    <input type="time" value="${end}" data-edit-end class="court-win-editor__time" aria-label="Fin">
    <button type="button" data-edit-save class="court-win-editor__btn court-win-editor__btn--ok" title="Guardar">✓</button>
    <button type="button" data-edit-cancel class="court-win-editor__btn" title="Cancelar">×</button>
  `;
  tag.classList.add('is-editing');
  tag.innerHTML = '';
  tag.appendChild(editor);

  const startEl = editor.querySelector('[data-edit-start]');
  const endEl = editor.querySelector('[data-edit-end]');
  startEl.focus();

  const restore = () => {
    tag.classList.remove('is-editing');
    tag.innerHTML = original;
  };

  editor.querySelector('[data-edit-cancel]').addEventListener('click', restore);

  editor.querySelector('[data-edit-save]').addEventListener('click', async () => {
    const s = startEl.value;
    const en = endEl.value;
    if (!s || !en) { toast.error('Ingresa inicio y fin.'); return; }
    if (en <= s) { toast.error('El fin debe ser posterior al inicio.'); return; }

    try {
      await post(updateTpl.replace("__ID__", id), { start_time: s, end_time: en });
      toast.success('Horario actualizado.');
      setTimeout(() => window.location.reload(), 400);
    } catch (err) {
      const msg = err?.body?.errors?.start_time?.[0] || 'No se pudo actualizar el horario.';
      toast.error(msg);
    }
  });

  // Enter saves, Esc cancels.
  editor.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); editor.querySelector('[data-edit-save]').click(); }
    if (e.key === 'Escape') { e.preventDefault(); restore(); }
  });
}
