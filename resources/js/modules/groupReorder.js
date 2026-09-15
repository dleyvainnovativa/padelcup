// Group reorder: order pairs WITHIN a group (seeding + match listing).
//
// Two interaction modes, both available on every device:
//   - Drag-sort: grab the grip and drop above/below another pair in the SAME
//     group. Pointer Events, so it works with mouse, touch and pen.
//   - Up/down buttons: always-reliable fallback, one nudge per tap.
//
// Cross-group moves stay in groupBuilder.js. This module only reorders inside
// a single group's list and never moves a pair between groups.
//
// On commit it POSTs the group's new pair order. If the server answers 409
// needs_confirm (Mexicano group that already has scores → reordering rebuilds
// and discards them), we show the themed confirm and re-POST with confirm:true.

import { post, HttpError } from '../core/http';
import toast from '../core/toast';
import { confirm as confirmModal } from '../core/modal';

export function initGroupReorder() {
  const board = document.querySelector('[data-group-board]');
  if (!board) return;

  const reorderUrl = board.dataset.reorderUrl;
  if (!reorderUrl) return; // reorder not enabled on this board

  // Only real groups are reorderable — not the "Sin asignar" pool (id 0).
  board
    .querySelectorAll('[data-group]:not([data-group-id="0"])')
    .forEach((group) => wireGroup(group, reorderUrl));
}

function wireGroup(groupEl, reorderUrl) {
  const groupId = groupEl.dataset.groupId;
  const list = groupEl.querySelector('[data-group-list]');
  if (!list) return;

  // Current on-screen order of pair ids.
  const currentOrder = () =>
    [...list.querySelectorAll('[data-pair]')].map((el) => el.dataset.pairId);

  // Persist the list's current DOM order to the server.
  async function commit() {
    const pairIds = currentOrder();
    if (pairIds.length < 2) return; // nothing to order

    try {
      const res = await post(reorderUrl, { group_id: groupId, pair_ids: pairIds });
      if (res.rebuilt) {
        toast.success('Orden guardado. Se regeneraron los partidos del grupo.');
        setTimeout(() => window.location.reload(), 700);
      } else {
        toast.success('Orden guardado.');
        setTimeout(() => window.location.reload(), 500);
      }
    } catch (e) {
      // 409 → Mexicano group with scores; ask to confirm the destructive rebuild.
      if (e instanceof HttpError && e.status === 409) {
        const ok = await confirmModal({
          title: 'Reordenar grupo',
          body:
            e.body?.message ||
            'Este grupo ya tiene resultados. Reordenar cambiará los enfrentamientos y se perderán los marcadores de este grupo. ¿Continuar?',
          confirmText: 'Reordenar y regenerar',
          variant: 'danger',
        });
        if (!ok) {
          window.location.reload(); // discard the optimistic DOM change
          return;
        }
        try {
          await post(reorderUrl, { group_id: groupId, pair_ids: pairIds, confirm: true });
          toast.success('Grupo reordenado. Se regeneraron los partidos.');
          setTimeout(() => window.location.reload(), 700);
        } catch (_) {
          toast.error('No se pudo reordenar el grupo.');
          setTimeout(() => window.location.reload(), 1000);
        }
        return;
      }
      toast.error('No se pudo guardar el orden.');
      setTimeout(() => window.location.reload(), 1000);
    }
  }

  wireButtons(list, commit);
  wireDragSort(list, commit);
}

// --- Up/down buttons (always available) ------------------------------------
//
// The buttons live OUTSIDE the [data-pair] chip — in a sibling [data-pair-row]
// wrapper — so a tap on them is never inside the chip and can't trigger
// groupBuilder's tap-to-pick. We move the whole ROW so the chip travels with
// its buttons. preventDefault avoids the mobile ghost-click.
function wireButtons(list, commit) {
  list.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-reorder-up], [data-reorder-down]');
    if (!btn) return;
    e.preventDefault();

    const row = btn.closest('[data-pair-row]');
    if (!row) return;

    if (btn.hasAttribute('data-reorder-up')) {
      const prev = row.previousElementSibling;
      if (prev && prev.matches('[data-pair-row]')) list.insertBefore(row, prev);
      else return; // already first
    } else {
      const next = row.nextElementSibling;
      if (next && next.matches('[data-pair-row]')) list.insertBefore(next, row);
      else return; // already last
    }
    commit();
  });
}

// --- Drag-sort within the group (Pointer Events: mouse/touch/pen) -----------
// Grip lives inside the chip; dragging moves the whole ROW wrapper.
function wireDragSort(list, commit) {
  let dragRow = null;
  let startY = 0;
  let moved = false;

  list.querySelectorAll('[data-pair-row]').forEach((row) => {
    const chip = row.querySelector('[data-pair]');
    const grip = row.querySelector('.pair-chip__grip') || chip;
    if (!grip) return;

    grip.addEventListener('pointerdown', (e) => {
      if (e.button === 2) return; // ignore right-click

      dragRow = row;
      startY = e.clientY;
      moved = false;
      row.classList.add('reordering');
      grip.setPointerCapture?.(e.pointerId);
    });

    grip.addEventListener('pointermove', (e) => {
      if (!dragRow) return;
      if (!moved && Math.abs(e.clientY - startY) < 4) return; // small-move threshold
      moved = true;
      e.preventDefault();

      const after = rowAfter(list, e.clientY);
      if (after == null) list.appendChild(dragRow);
      else if (after !== dragRow) list.insertBefore(dragRow, after);
    });

    const finish = (e) => {
      if (!dragRow) return;
      const el = dragRow;
      dragRow = null;
      el.classList.remove('reordering');
      grip.releasePointerCapture?.(e.pointerId);
      if (moved) commit();
    };
    grip.addEventListener('pointerup', finish);
    grip.addEventListener('pointercancel', finish);
  });
}

// Which row should the dragged one be inserted BEFORE, given a Y position.
function rowAfter(list, y) {
  const rows = [...list.querySelectorAll('[data-pair-row]:not(.reordering)')];
  let closest = null;
  let closestOffset = Number.NEGATIVE_INFINITY;
  for (const row of rows) {
    const box = row.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closestOffset) {
      closestOffset = offset;
      closest = row;
    }
  }
  return closest; // null → append at end
}