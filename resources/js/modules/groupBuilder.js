// Group builder: move pairs between groups.
//  - Desktop: native HTML5 drag-and-drop.
//  - Mobile/touch: tap a pair to "pick it up", then tap a target group.
// Saves immediately via the move endpoint; matches rebuild server-side.

import { post } from '../core/http';
import toast from '../core/toast';

const isTouch = window.matchMedia('(hover: none), (pointer: coarse)').matches;

export function initGroupBuilder() {
  const board = document.querySelector('[data-group-board]');
  if (!board) return;

  const moveUrl = board.dataset.moveUrl;
  const reorderUrl = board.dataset.reorderUrl;

  // Shared move routine used by both interaction modes. `beforeRef` (optional) is
  // the list child to insert BEFORE — desktop drag passes it so the pair lands at
  // the drop position instead of always at the end; null/append = bottom.
  async function movePair(pairEl, toGroupEl, beforeRef = null) {
    const pairId = pairEl.dataset.pairId;
    const fromGroupId = pairEl.closest('[data-group]').dataset.groupId;
    const toGroupId = toGroupEl.dataset.groupId;

    if (fromGroupId === toGroupId) return;

    // Optimistic move in the DOM, at the chosen position when given.
    const list = toGroupEl.querySelector('[data-group-list]');
    if (beforeRef && beforeRef !== pairEl && beforeRef.parentElement === list) {
      list.insertBefore(pairEl, beforeRef);
    } else {
      list.appendChild(pairEl);
    }

    // Desired order for the destination group (not the "Sin asignar" pool, id 0),
    // read from the DOM after the optimistic insert.
    const desiredOrder = toGroupId !== '0'
      ? [...list.querySelectorAll('[data-pair]')].map((el) => el.dataset.pairId)
      : null;

    try {
      const res = await post(moveUrl, {
        pair_id: pairId,
        from_group_id: fromGroupId,
        to_group_id: toGroupId,
      });
      if (res.warning) {
        toast.warning(res.warning);
      } else {
        toast.success('Pareja movida.');
      }

      // Persist the chosen position within the destination group. The move above
      // already attached the pair, so the reorder set matches the group's pairs.
      if (desiredOrder && desiredOrder.length > 1) {
        try {
          await post(reorderUrl, { group_id: toGroupId, pair_ids: desiredOrder });
        } catch (_) {
          // Position couldn't be persisted (e.g. a Mexicano group needing confirm);
          // the move still succeeded — the reload shows the server's order.
        }
      }

      // Resync standings (server-rendered) after the match rebuild.
      setTimeout(() => window.location.reload(), 700);
    } catch (e) {
      toast.error('No se pudo mover la pareja.');
      setTimeout(() => window.location.reload(), 1200);
    }
  }

  if (isTouch) {
    initTapMode(board, movePair);
  } else {
    initDragMode(board, movePair);
  }
}

// --- Desktop: HTML5 drag-and-drop ----------------------------------
function initDragMode(board, movePair) {
  board.querySelectorAll('[data-pair]').forEach((el) => {
    el.setAttribute('draggable', 'true');
    el.addEventListener('dragstart', (e) => {
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', el.dataset.pairId);
    });
    el.addEventListener('dragend', () => el.classList.remove('dragging'));
  });

  board.querySelectorAll('[data-group]').forEach((group) => {
    group.addEventListener('dragover', (e) => {
      e.preventDefault();
      group.classList.add('drop-target');
    });
    group.addEventListener('dragleave', () => group.classList.remove('drop-target'));
    group.addEventListener('drop', (e) => {
      e.preventDefault();
      group.classList.remove('drop-target');
      const dragging = board.querySelector('.dragging');
      if (!dragging) return;
      const list = group.querySelector('[data-group-list]');
      const beforeRef = list ? insertRefAt(list, e.clientY) : null;
      movePair(dragging, group, beforeRef);
    });
  });
}

// The list child to insert the dragged pair BEFORE, given a drop Y. Returns null
// to append at the end. Works whether list children are bare chips (pool) or
// [data-pair-row] wrappers (real groups) — it maps each chip to its own child.
function insertRefAt(list, y) {
  const chips = [...list.querySelectorAll('[data-pair]')];
  for (const chip of chips) {
    const box = chip.getBoundingClientRect();
    if (y < box.top + box.height / 2) {
      return chip.closest('[data-pair-row]') || chip;
    }
  }
  return null;
}

// --- Mobile: tap-to-pick, tap-to-drop ------------------------------
function initTapMode(board, movePair) {
  let picked = null;

  function clearPicked() {
    if (picked) picked.classList.remove('picked');
    picked = null;
    board.querySelectorAll('[data-group]').forEach((g) => g.classList.remove('drop-hint'));
  }

  board.querySelectorAll('[data-pair]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.stopPropagation();
      if (picked === el) {
        clearPicked();
        return;
      }
      clearPicked();
      picked = el;
      el.classList.add('picked');
      // Hint the other groups as drop targets.
      const currentGroup = el.closest('[data-group]');
      board.querySelectorAll('[data-group]').forEach((g) => {
        if (g !== currentGroup) g.classList.add('drop-hint');
      });
    });
  });

  board.querySelectorAll('[data-group]').forEach((group) => {
    group.addEventListener('click', () => {
      if (!picked) return;
      const target = group;
      const pairEl = picked;
      clearPicked();
      movePair(pairEl, target);
    });
  });

  // Tap outside to cancel.
  document.addEventListener('click', () => clearPicked());
}