// Calendar highlight filters: (1) multi-select category chips by tint, and
// (2) a player-name search box. When any filter is active, matches that don't
// satisfy ALL active filters are dimmed. Pure client-side.

export function initCategoryHighlight() {
  const board = document.querySelector('[data-sched-board]');
  if (!board) return;

  const chips = document.querySelectorAll('[data-cat-chip]');
  const search = document.querySelector('[data-player-highlight]');
  const clearBtn = document.querySelector('[data-player-highlight-clear]');

  const selectedCats = new Set();
  let nameQuery = '';

  chips.forEach((chip) => {
    chip.addEventListener('click', () => {
      const id = chip.dataset.catChip;
      if (selectedCats.has(id)) { selectedCats.delete(id); chip.classList.remove('is-on'); }
      else { selectedCats.add(id); chip.classList.add('is-on'); }
      apply();
    });
  });

  if (search) {
    search.addEventListener('input', () => {
      nameQuery = search.value.trim().toLowerCase();
      if (clearBtn) clearBtn.style.display = nameQuery ? '' : 'none';
      apply();
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      nameQuery = '';
      if (search) search.value = '';
      clearBtn.style.display = 'none';
      apply();
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
      // Highlight only when it satisfies every active filter.
      el.classList.toggle('is-dimmed', !(catOk && nameOk));
    });
  }
}