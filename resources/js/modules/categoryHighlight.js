// Calendar highlight filters: (1) multi-select category chips by tint, and
// (2) a player-name search box. When any filter is active, matches that don't
// satisfy ALL active filters are dimmed. Pure client-side.
//
// Filter state is persisted in the URL query string (?cats=1,2&player=ana) so it
// survives page reloads — e.g. after the manager sets a result or moves a match
// (which reload the page). On load we restore from the URL; on every change we
// rewrite it with history.replaceState (no new history entry, no navigation).

export function initCategoryHighlight() {
  const board = document.querySelector('[data-sched-board]');
  if (!board) return;

  const chips = document.querySelectorAll('[data-cat-chip]');
  const search = document.querySelector('[data-player-highlight]');
  const clearBtn = document.querySelector('[data-player-highlight-clear]');

  const selectedCats = new Set();
  let nameQuery = '';

  // --- Restore state from the URL ----------------------------------
  (function restore() {
    const params = new URLSearchParams(window.location.search);
    const cats = params.get('cats');
    const player = params.get('player');

    if (cats) {
      cats.split(',').filter(Boolean).forEach((id) => selectedCats.add(id));
      chips.forEach((chip) => {
        if (selectedCats.has(chip.dataset.catChip)) chip.classList.add('is-on');
      });
    }
    if (player) {
      nameQuery = player.trim().toLowerCase();
      if (search) search.value = player;
      if (clearBtn) clearBtn.style.display = nameQuery ? '' : 'none';
    }
  })();

  // --- Persist current state into the URL --------------------------
  function syncUrl() {
    const params = new URLSearchParams(window.location.search);

    if (selectedCats.size > 0) params.set('cats', [...selectedCats].join(','));
    else params.delete('cats');

    if (nameQuery.length > 0) params.set('player', search ? search.value.trim() : nameQuery);
    else params.delete('player');

    const qs = params.toString();
    const newUrl = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
    window.history.replaceState(null, '', newUrl);
  }

  chips.forEach((chip) => {
    chip.addEventListener('click', () => {
      const id = chip.dataset.catChip;
      if (selectedCats.has(id)) { selectedCats.delete(id); chip.classList.remove('is-on'); }
      else { selectedCats.add(id); chip.classList.add('is-on'); }
      apply();
      syncUrl();
    });
  });

  if (search) {
    search.addEventListener('input', () => {
      nameQuery = search.value.trim().toLowerCase();
      if (clearBtn) clearBtn.style.display = nameQuery ? '' : 'none';
      apply();
      syncUrl();
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      nameQuery = '';
      if (search) search.value = '';
      clearBtn.style.display = 'none';
      apply();
      syncUrl();
    });
  }

  function apply() {
    const catActive = selectedCats.size > 0;
    const nameActive = nameQuery.length > 0;

    if (!catActive && !nameActive) {
      board.classList.remove('is-highlighting');
      board.querySelectorAll('[data-match-cat]').forEach((el) => el.classList.remove('is-dimmed'));
      return;
    }

    board.classList.add('is-highlighting');
    board.querySelectorAll('[data-match-cat]').forEach((el) => {
      const catOk = !catActive || selectedCats.has(el.dataset.matchCat);
      const players = el.dataset.matchPlayers || '';
      const nameOk = !nameActive || players.includes(nameQuery);
      el.classList.toggle('is-dimmed', !(catOk && nameOk));
    });
  }

  // Apply restored state on load (so a reload re-dims correctly).
  apply();
}
