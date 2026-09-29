// Public "Últimos cambios" bottomsheet — shows recent court/time changes so
// players understand a rescheduled match. A [data-changes-url] button fetches
// the JSON feed and renders it in a bottom sheet. Read-only, no libraries.

export function initPublicChanges() {
    const triggers = document.querySelectorAll('[data-changes-url]');
    if (!triggers.length) return;

    let sheet = null;

    triggers.forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!sheet) sheet = buildSheet();
            sheet.open(btn.dataset.changesUrl, btn.dataset.changesTitle || 'Últimos cambios');
        });
    });
}

function buildSheet() {
    const overlay = document.createElement('div');
    overlay.className = 'pc-changes-overlay';
    overlay.innerHTML = `
    <div class="pc-changes" role="dialog" aria-modal="true" aria-label="Últimos cambios">
      <div class="pc-changes__grip"></div>
      <div class="pc-changes__head">
        <div class="pc-changes__title">Últimos cambios</div>
        <button type="button" class="pc-changes__close" aria-label="Cerrar">&times;</button>
      </div>
      <div class="pc-changes__sub">Cambios de cancha y horario · últimas 48 h</div>
      <div class="pc-changes__list" data-changes-list></div>
    </div>`;
    document.body.appendChild(overlay);

    const listEl = overlay.querySelector('[data-changes-list]');
    const titleEl = overlay.querySelector('.pc-changes__title');

    const hide = () => overlay.classList.remove('is-open');
    overlay.addEventListener('click', (e) => { if (e.target === overlay) hide(); });
    overlay.querySelector('.pc-changes__close').addEventListener('click', hide);

    async function open(url, title) {
        titleEl.textContent = title;
        listEl.innerHTML = '<div class="pc-changes__loading">Cargando…</div>';
        overlay.classList.add('is-open');

        let data = null;
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            data = await res.json();
        } catch (_) {
            listEl.innerHTML = '<div class="pc-changes__empty">No se pudieron cargar los cambios.</div>';
            return;
        }

        const changes = (data && data.changes) || [];
        if (!changes.length) {
            listEl.innerHTML = '<div class="pc-changes__empty">Sin cambios en las últimas 48 horas.</div>';
            return;
        }

        listEl.innerHTML = '';
        changes.forEach((ch) => {
            const item = document.createElement('div');
            item.className = 'pc-change';
            item.innerHTML = `
              <div class="pc-change__top">
                <span class="pc-change__match">${esc(ch.match || ch.category || '')}</span>
                <span class="pc-change__ago">${esc(ch.ago || '')}</span>
              </div>
              <div class="pc-change__summary">${esc(ch.summary || '')}</div>`;
            listEl.appendChild(item);
        });
    }

    return { open };
}

function esc(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
