/**
 * Arrivals-board display (TV / kiosk). Polls the display JSON feed, renders the
 * "en juego" + "próximos" board, auto-paginates when there are more matches than
 * fit (max per page), rotates pages on a timer, shows a live clock, and runs a
 * "últimos resultados" ticker. Config comes from data-* attributes on the root
 * plus the page's own query string (venues/ahead/back), so a screen just opens a
 * URL. All timers are self-healing on each refresh. No websockets (polling only).
 */
export function initDisplayBoard() {
  const root = document.querySelector('[data-display-board]');
  if (!root) return;

  const dataUrl = root.dataset.dataUrl;
  const max = Math.max(1, parseInt(root.dataset.max, 10) || 6);
  const rotateMs = (Math.max(4, parseInt(root.dataset.rotate, 10) || 12)) * 1000;
  const refreshMs = (Math.max(10, parseInt(root.dataset.refresh, 10) || 30)) * 1000;

  const rowsEl = root.querySelector('[data-disp-rows]');
  const venueEl = root.querySelector('[data-disp-venue]');
  const clockEl = root.querySelector('[data-disp-clock]');
  const pagerEl = root.querySelector('[data-disp-pager]');
  const tickerWrap = root.querySelector('[data-disp-ticker-wrap]');
  const tickerEl = root.querySelector('[data-disp-ticker]');

  let board = [];
  let pages = [];
  let pageIndex = 0;
  let rotateTimer = null;

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  function chunk(arr, n) {
    const out = [];
    for (let i = 0; i < arr.length; i += n) out.push(arr.slice(i, i + n));
    return out;
  }

  function rowHtml(m) {
    const live = m.bucket === 'current';
    const done = m.bucket === 'finished';
    const aWin = m.winnerSide === 'a';
    const bWin = m.winnerSide === 'b';
    const phase = m.phase ? `<span class="disp__phase">${esc(m.phase)}</span>` : '';
    const trophy = ' <i class="fa-solid fa-trophy disp__win-ic"></i>';

    // Status cell: the score (+ FINAL) for a decided match, else the status badge.
    const statusCell = (done && m.score)
      ? `<span class="disp__score">${esc(m.score)}</span><span class="disp__badge disp__badge--finished">Finalizado</span>`
      : `<span class="disp__badge disp__badge--${m.status || 'pending'}">${esc(m.statusLabel)}</span>`;

    // Full names stacked: team 1 / "vs" / team 2 (no horizontal truncation).
    return `
      <div class="disp__row disp__row--data${live ? ' is-live' : ''}${done ? ' is-done' : ''}">
        <span class="disp__c-time">${esc(m.time)}</span>
        <span class="disp__c-court">${esc(m.court || '—')}${m.venue ? `<small>${esc(m.venue)}</small>` : ''}</span>
        <span class="disp__c-cat">${esc(m.category || '')}${phase}</span>
        <span class="disp__c-pairs disp__pairs">
          <span class="disp__team${aWin ? ' is-win' : ''}">${esc(m.pairA)}${aWin ? trophy : ''}</span>
          <span class="disp__vs">vs</span>
          <span class="disp__team${bWin ? ' is-win' : ''}">${esc(m.pairB)}${bWin ? trophy : ''}</span>
        </span>
        <span class="disp__c-status">${statusCell}</span>
      </div>`;
  }

  function renderPage() {
    if (!pages.length) {
      rowsEl.innerHTML = '<div class="disp__empty">No hay partidos en este momento.</div>';
      pagerEl.hidden = true;
      return;
    }
    if (pageIndex >= pages.length) pageIndex = 0;
    rowsEl.innerHTML = pages[pageIndex].map(rowHtml).join('');

    if (pages.length > 1) {
      pagerEl.hidden = false;
      pagerEl.innerHTML = pages.map((_, i) =>
        `<span class="disp__dot${i === pageIndex ? ' is-on' : ''}"></span>`).join('');
    } else {
      pagerEl.hidden = true;
    }
  }

  function startRotation() {
    if (rotateTimer) clearInterval(rotateTimer);
    if (pages.length <= 1) return;
    rotateTimer = setInterval(() => {
      pageIndex = (pageIndex + 1) % pages.length;
      renderPage();
    }, rotateMs);
  }

  function renderTicker(finished) {
    if (!finished || !finished.length) { tickerWrap.hidden = true; tickerEl.innerHTML = ''; return; }
    tickerWrap.hidden = false;
    const one = finished.map((m) => {
      const win = m.winner ? `<b>${esc(m.winner)}</b>` : `${esc(m.pairA)} / ${esc(m.pairB)}`;
      const score = m.score ? ` <span class="disp__tick-score">${esc(m.score)}</span>` : '';
      return `<span class="disp__tick"><em>${esc(m.category || '')}</em> ${win}${score}</span>`;
    }).join('<span class="disp__tick-sep">•</span>');
    // Duplicate the content so the -50% marquee loop is seamless.
    tickerEl.innerHTML = `<span class="disp__tick-seg">${one}</span><span class="disp__tick-seg" aria-hidden="true">${one}</span>`;
  }

  async function refresh() {
    try {
      const res = await fetch(dataUrl + window.location.search, {
        headers: { Accept: 'application/json' }, cache: 'no-store',
      });
      const json = await res.json();
      board = json.board || [];
      if (venueEl) venueEl.textContent = [json.dayLabel, json.venueLabel].filter(Boolean).join(' · ');
      renderTicker(json.finished);

      const prevCount = pages.length;
      pages = chunk(board, max);
      if (pageIndex >= pages.length) pageIndex = 0;
      renderPage();
      // Re-arm rotation if the number of pages changed.
      if (pages.length !== prevCount) startRotation();
    } catch (e) {
      // Keep showing the last good board on a transient failure.
    }
  }

  function tickClock() {
    if (!clockEl) return;
    const d = new Date();
    clockEl.textContent = d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
  }

  tickClock();
  setInterval(tickClock, 1000);
  refresh();
  setInterval(refresh, refreshMs);
}
