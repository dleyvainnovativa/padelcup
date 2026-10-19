// Mesa de control (event-day board). Inline status stepping + per-player
// payments, both AJAX so the mesa never reloads mid-tap. A soft 30s refresh
// keeps delays/counts fresh; it's deferred while the operator is interacting.

import { post } from '../core/http';
import toast from '../core/toast';

const STATUS_ORDER = ['pending', 'called', 'playing', 'finished'];

export function initMesaControl() {
  const board = document.querySelector('[data-control-board]');
  if (!board) return;

  // --- Status stepping -------------------------------------------------
  board.querySelectorAll('[data-ctrl-steps-row]').forEach((row) => {
    const url = row.dataset.url;
    row.querySelectorAll('[data-ctrl-status]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const status = btn.dataset.status;
        const buttons = row.querySelectorAll('[data-ctrl-status]');
        buttons.forEach((b) => (b.disabled = true));
        bumpRefresh();
        try {
          const res = await post(url, { status });
          applyStatus(row, res.status || status);
          toast.success('Estado: ' + (res.label || status));
        } catch (_) {
          toast.error('No se pudo actualizar el estado.');
        } finally {
          buttons.forEach((b) => (b.disabled = false));
        }
      });
    });
  });

  function applyStatus(row, status) {
    row.dataset.current = status;
    const idx = STATUS_ORDER.indexOf(status);
    row.querySelectorAll('[data-ctrl-status]').forEach((b) => {
      const i = STATUS_ORDER.indexOf(b.dataset.status);
      b.classList.toggle('is-active', i <= idx);
      b.classList.toggle('is-current', i === idx);
    });
  }

  // --- Per-player payments --------------------------------------------
  board.querySelectorAll('[data-pago-cb]').forEach((cb) => {
    cb.addEventListener('change', async () => {
      const url = cb.dataset.url;
      const pid = parseInt(cb.dataset.player, 10);
      cb.disabled = true;
      bumpRefresh();
      try {
        const res = await post(url, { player_id: pid, paid: cb.checked });
        const states = res.states || {};
        // Reflect server truth across every checkbox for these players.
        Object.entries(states).forEach(([playerId, paid]) => {
          board.querySelectorAll(`[data-pago-cb][data-player="${playerId}"]`).forEach((other) => {
            other.checked = !!paid;
            const label = other.closest('[data-pago]');
            if (label) {
              label.classList.toggle('is-paid', !!paid);
              const st = label.querySelector('[data-pago-state]');
              if (st) st.textContent = paid ? 'Pagado' : 'Pendiente';
            }
          });
        });
        toast.success('Pago actualizado.');
      } catch (_) {
        toast.error('No se pudo actualizar el pago.');
        cb.checked = !cb.checked;
      } finally {
        cb.disabled = false;
      }
    });
  });

  // --- Soft 30s refresh (deferred while interacting) ------------------
  let refreshAt = Date.now() + 30000;
  function bumpRefresh() { refreshAt = Date.now() + 30000; }
  setInterval(() => {
    if (Date.now() >= refreshAt && document.visibilityState === 'visible') {
      window.location.reload();
    }
  }, 5000);
}
