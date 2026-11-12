// Topbar search: live dropdown over the manager's own tournaments, categories
// and players. Debounced GET to dashboard.search; results are grouped and each
// row links straight to its page. Keyboard: ↑/↓ to move, Enter to open, Esc to
// close; click-outside closes too.

import { get } from '../core/http';

const MIN = 2;
const DEBOUNCE = 220;

const ICONS = {
  tournaments: 'fa-trophy',
  categories: 'fa-layer-group',
  players: 'fa-user',
};
const LABELS = {
  tournaments: 'Torneos',
  categories: 'Categorías',
  players: 'Jugadores',
};

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

export function initTopbarSearch() {
  const root = document.querySelector('[data-topbar-search]');
  if (!root) return;
  const input = root.querySelector('[data-search-input]');
  const panel = root.querySelector('[data-search-results]');
  const url = root.dataset.searchUrl;
  if (!input || !panel || !url) return;

  let timer = null;
  let token = 0; // guards against out-of-order responses
  let items = []; // flat list of {url} for keyboard nav
  let active = -1;

  function close() {
    panel.hidden = true;
    panel.innerHTML = '';
    items = [];
    active = -1;
  }

  function open() {
    if (panel.innerHTML.trim()) panel.hidden = false;
  }

  function highlight() {
    panel.querySelectorAll('.tbs__row').forEach((el, i) => {
      el.classList.toggle('is-active', i === active);
    });
  }

  function render(data) {
    const groups = ['tournaments', 'categories', 'players'];
    let html = '';
    items = [];
    for (const g of groups) {
      const rows = data[g] || [];
      if (!rows.length) continue;
      html += `<div class="tbs__group">${LABELS[g]}</div>`;
      for (const r of rows) {
        const idx = items.length;
        items.push(r);
        const sub =
          g === 'players'
            ? `${esc(r.category)} · ${esc(r.tournament)}`
            : g === 'categories'
              ? esc(r.tournament)
              : '';
        html += `<a class="tbs__row" href="${esc(r.url)}" data-idx="${idx}">
          <i class="fa-solid ${ICONS[g]} tbs__icon"></i>
          <span class="tbs__text"><span class="tbs__name">${esc(r.name)}</span>${
            sub ? `<span class="tbs__sub">${sub}</span>` : ''
          }</span></a>`;
      }
    }
    if (!items.length) {
      html = `<div class="tbs__empty">Sin resultados para «${esc(data.q || '')}»</div>`;
    }
    panel.innerHTML = html;
    active = -1;
    open();
  }

  async function run(q) {
    const mine = ++token;
    try {
      const data = await get(`${url}?q=${encodeURIComponent(q)}`);
      if (mine !== token) return; // a newer query already fired
      render(data);
    } catch (e) {
      if (mine === token) close();
    }
  }

  input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < MIN) {
      close();
      return;
    }
    timer = setTimeout(() => run(q), DEBOUNCE);
  });

  input.addEventListener('keydown', (e) => {
    if (panel.hidden || !items.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      active = (active + 1) % items.length;
      highlight();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active - 1 + items.length) % items.length;
      highlight();
    } else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      window.location.href = items[active].url;
    } else if (e.key === 'Escape') {
      close();
    }
  });

  input.addEventListener('focus', open);
  document.addEventListener('click', (e) => {
    if (!root.contains(e.target)) close();
  });
}
