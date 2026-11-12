// Group letters: reorder the GROUPS of a category to set each group's letter.
//
// The letter is derived from a group's position, so "rename group B to A" means
// "move it to the first slot". This module lets the manager nudge groups up/down,
// re-letters the badges live, and on save POSTs the new order. The server renames
// + repositions only — matches are never moved or unscheduled — so this is safe
// (and available) even on a started tournament.
//
// On save we reload, because the letters appear all over the page (standings
// headers, group columns, bracket preview) and the server is the source of truth.

import { post, HttpError } from '../core/http';
import toast from '../core/toast';

// 0 -> "A", 25 -> "Z", 26 -> "AA", … (mirror of App\Support\GroupLetter::at).
function letterAt(index) {
  let n = Math.max(0, index);
  let s = '';
  do {
    s = String.fromCharCode(65 + (n % 26)) + s;
    n = Math.floor(n / 26) - 1;
  } while (n >= 0);
  return s;
}

export function initGroupLetters() {
  const list = document.querySelector('[data-group-letters]');
  if (!list) return;

  const url = list.dataset.url;
  const saveBtn = document.querySelector('[data-gl-save]');
  const resetBtn = document.querySelector('[data-gl-reset]');
  const dirtyTag = document.querySelector('[data-gl-dirty]');

  const items = () => [...list.querySelectorAll('.grp-letters__item')];
  const original = items().map((el) => el.dataset.groupId);

  const idsNow = () => items().map((el) => el.dataset.groupId);
  const isDirty = () => idsNow().some((id, i) => id !== original[i]);

  // Re-letter badges to match the current DOM order and reflect dirty state.
  function refresh() {
    items().forEach((el, i) => {
      const badge = el.querySelector('[data-gl-badge]');
      if (badge) badge.textContent = letterAt(i);
    });
    const dirty = isDirty();
    [saveBtn, resetBtn, dirtyTag].forEach((n) => n && (n.hidden = !dirty));
  }

  function move(item, dir) {
    if (dir < 0 && item.previousElementSibling) {
      list.insertBefore(item, item.previousElementSibling);
    } else if (dir > 0 && item.nextElementSibling) {
      list.insertBefore(item.nextElementSibling, item);
    }
    refresh();
  }

  list.addEventListener('click', (e) => {
    const up = e.target.closest('[data-gl-up]');
    const down = e.target.closest('[data-gl-down]');
    if (!up && !down) return;
    const item = e.target.closest('.grp-letters__item');
    if (item) move(item, up ? -1 : 1);
  });

  resetBtn &&
    resetBtn.addEventListener('click', () => {
      // Restore the original DOM order, then refresh.
      original.forEach((id) => {
        const el = list.querySelector(`.grp-letters__item[data-group-id="${id}"]`);
        if (el) list.appendChild(el);
      });
      refresh();
    });

  saveBtn &&
    saveBtn.addEventListener('click', async () => {
      if (!isDirty()) return;
      saveBtn.disabled = true;
      try {
        const res = await post(url, { group_ids: idsNow() });
        toast.success(res.warning || 'Orden de grupos guardado.');
        setTimeout(() => window.location.reload(), res.warning ? 900 : 500);
      } catch (e) {
        saveBtn.disabled = false;
        const msg =
          e instanceof HttpError && e.body && e.body.warning
            ? e.body.warning
            : 'No se pudo guardar el orden.';
        toast.error(msg);
      }
    });
}
