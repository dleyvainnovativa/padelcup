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

  // Shared move routine used by both interaction modes.
  async function movePair(pairEl, toGroupEl) {
    const pairId = pairEl.dataset.pairId;
    const fromGroupId = pairEl.closest('[data-group]').dataset.groupId;
    const toGroupId = toGroupEl.dataset.groupId;

    if (fromGroupId === toGroupId) return;

    // Optimistic move in the DOM.
    const list = toGroupEl.querySelector('[data-group-list]');
    list.appendChild(pairEl);

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
      if (dragging) movePair(dragging, group);
    });
  });
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