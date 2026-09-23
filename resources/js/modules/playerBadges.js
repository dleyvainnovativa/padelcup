// Player-search badges for the manager schedule board.
//
// Below the "Resaltar jugador por nombre…" search box, show up to 5 badges of
// players whose name matches the query. Each badge:
//   - click       → sets the highlight search to that exact player (drives
//                    categoryHighlight.js, which dims non-matching matches)
//   - "ⓘ" / long  → opens a bottomsheet listing that player's scheduled matches
//                    (count, partner, court, datetime). Tapping a match there
//                    scrolls to it on the board and opens its control sheet.
//
// Data comes from a JSON blob rendered by the blade (window.__playerMatchIndex),
// keyed nothing — it's a flat list [{key,name,count,matches:[...]}].

const MAX_BADGES = 5;

export function initPlayerBadges() {
  const search = document.querySelector('[data-player-highlight]');
  const host = document.querySelector('[data-player-badges]');
  const board = document.querySelector('[data-sched-board]');
  if (!search || !host || !board) return;

  let index = [];
  try {
    index = JSON.parse(document.getElementById('player-match-index')?.textContent || '[]');
  } catch (_) { index = []; }
  if (!index.length) return;

  const sheet = buildBadgeSheet(board);

  function render() {
    const q = search.value.trim().toLowerCase();
    host.innerHTML = '';
    if (!q) { host.hidden = true; return; }

    const hits = index
      .filter((p) => p.name.toLowerCase().includes(q) || p.key.includes(q))
      .slice(0, MAX_BADGES);

    if (!hits.length) { host.hidden = true; return; }
    host.hidden = false;

    hits.forEach((p) => {
      const badge = document.createElement('span');
      badge.className = 'pl-badge';
      badge.innerHTML = `
        <button type="button" class="pl-badge__name" title="Resaltar a ${escapeHtml(p.name)}">
          ${escapeHtml(p.name)}<span class="pl-badge__count">${p.count}</span>
        </button>
        <button type="button" class="pl-badge__info" title="Ver partidos de ${escapeHtml(p.name)}" aria-label="Ver partidos">
          <i class="fa-solid fa-circle-info"></i>
        </button>`;

      // Highlight: put the exact name in the search box and fire input.
      badge.querySelector('.pl-badge__name').addEventListener('click', () => {
        search.value = p.name;
        search.dispatchEvent(new Event('input', { bubbles: true }));
      });

      // Info: open the bottomsheet with this player's matches.
      badge.querySelector('.pl-badge__info').addEventListener('click', (e) => {
        e.stopPropagation();
        sheet.open(p);
      });

      host.appendChild(badge);
    });
  }

  search.addEventListener('input', render);
  // Render once on load (query may be restored from the URL by categoryHighlight).
  render();
}

// --- Bottomsheet listing a player's matches --------------------------------
function buildBadgeSheet(board) {
  const overlay = document.createElement('div');
  overlay.className = 'sched-sheet-overlay pl-badge-sheet';
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
      <div class="sched-sheet__list pl-badge-sheet__list"></div>
    </div>`;
  document.body.appendChild(overlay);

  const titleEl = overlay.querySelector('.sched-sheet__title');
  const subEl = overlay.querySelector('.sched-sheet__sub');
  const listEl = overlay.querySelector('.pl-badge-sheet__list');

  const hide = () => overlay.classList.remove('is-open');
  overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
  overlay.querySelector('.sched-sheet__close').addEventListener('click', hide);

  function open(player) {
    titleEl.textContent = player.name;
    subEl.textContent = `${player.count} ${player.count === 1 ? 'partido' : 'partidos'} programados`;
    listEl.innerHTML = '';

    player.matches.forEach((m) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'pl-badge-match';
      const partner = m.partner
        ? `<span class="pl-badge-match__partner"><i class="fa-solid fa-user-group"></i> con ${escapeHtml(m.partner)}</span>`
        : `<span class="pl-badge-match__partner pl-badge-match__partner--solo"><i class="fa-solid fa-user"></i> Individual</span>`;
      item.innerHTML = `
        <div class="pl-badge-match__top">
          <span class="pl-badge-match__cat">${escapeHtml(m.category)}</span>
          <span class="pl-badge-match__when">${escapeHtml(m.label)}</span>
        </div>
        <div class="pl-badge-match__bottom">
          ${partner}
          <span class="pl-badge-match__court"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(m.court)}</span>
        </div>`;

      item.addEventListener('click', () => {
        hide();
        jumpToMatch(board, m.match_id);
      });
      listEl.appendChild(item);
    });

    overlay.classList.add('is-open');
  }

  return { open };
}

// Scroll to a match on the board and open its control sheet (simulate a click).
function jumpToMatch(board, matchId) {
  const el = board.querySelector(`.sched-match[data-match-id="${matchId}"]`);
  if (!el) return;

  // If the match is on a day tab that's hidden, switch to that day first.
  const body = el.closest('[data-day-body]');
  const dayKey = body?.dataset.dayBody;
  if (dayKey && !isVisible(el)) {
    const dayBtn = board.querySelector(`.sched-day[data-day="${dayKey}"]`);
    if (dayBtn) dayBtn.click(); // Alpine setDay() switches the tab
  }

  // Wait a tick for the day tab to render, then scroll + open.
  setTimeout(() => {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('pl-badge-flash');
    setTimeout(() => el.classList.remove('pl-badge-flash'), 1600);
    el.click(); // opens the existing match-control sheet
  }, 120);
}

function isVisible(el) {
  return el && el.offsetParent !== null;
}

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
