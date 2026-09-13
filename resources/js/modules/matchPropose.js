/**
 * modules/matchPropose.js
 * Player-facing "propose result" drawer, opened from the [data-propose-match]
 * button on public match cards. Mirrors the manager's score sheet: pair names,
 * three set rows, a confirm button. Submits via fetch and reloads on success.
 *
 * Wired in app.js:  if ([data-propose-match]) import('./modules/matchPropose')...
 */
function csrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function buildSheet() {
  const overlay = document.createElement('div');
  overlay.className = 'pub-sheet';
  overlay.innerHTML = `
    <div class="pub-sheet__backdrop" data-close></div>
    <div class="pub-sheet__panel" role="dialog" aria-modal="true" aria-label="Proponer resultado">
      <div class="pub-sheet__grip"></div>
      <button type="button" class="pub-sheet__close" data-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
      <div class="pub-sheet__ctx" data-ctx></div>
      <h3 class="pub-sheet__title" data-title></h3>
      <div class="pub-sheet__card">
        <div class="pub-sheet__head"><i class="fa-solid fa-pen"></i> <span data-heading>Proponer resultado</span></div>
        <div class="pub-sheet__cols">
          <span class="pub-sheet__col" data-col-a></span>
          <span class="pub-sheet__col" data-col-b></span>
        </div>
        <div class="pub-sheet__sets" data-sets></div>
        <div class="pub-sheet__err" data-err hidden></div>
        <button type="button" class="pub-sheet__submit" data-submit>Enviar propuesta</button>
      </div>
      <p class="pub-sheet__hint" data-hint>El organizador revisará y confirmará tu propuesta.</p>
    </div>`;
  document.body.appendChild(overlay);

  const setsWrap = overlay.querySelector('[data-sets]');
  for (let i = 0; i < 3; i++) {
    const row = document.createElement('div');
    row.className = 'pub-sheet__row';
    row.innerHTML = `
      <span class="pub-sheet__label">Set ${i + 1}</span>
      <input type="number" min="0" max="7" inputmode="numeric" class="pub-sheet__in" data-a="${i}">
      <span class="pub-sheet__dash">-</span>
      <input type="number" min="0" max="7" inputmode="numeric" class="pub-sheet__in" data-b="${i}">`;
    setsWrap.appendChild(row);
  }

  const hide = () => { overlay.classList.remove('is-open'); document.body.style.overflow = ''; };
  overlay.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', hide));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });

  return {
    el: overlay,
    show(cfg) {
      overlay.querySelector('[data-ctx]').textContent = cfg.ctx || '';
      overlay.querySelector('[data-title]').textContent = `${cfg.a} · ${cfg.b}`;
      overlay.querySelector('[data-col-a]').textContent = cfg.a;
      overlay.querySelector('[data-col-b]').textContent = cfg.b;
      if (cfg.heading) overlay.querySelector('[data-heading]').textContent = cfg.heading;
      if (cfg.hint) overlay.querySelector('[data-hint]').textContent = cfg.hint;
      if (cfg.cta) overlay.querySelector('[data-submit]').textContent = cfg.cta;
      // Reset then optionally prefill from an existing prediction.
      overlay.querySelectorAll('.pub-sheet__in').forEach((i) => (i.value = ''));
      if (Array.isArray(cfg.prefill)) {
        cfg.prefill.forEach((set, i) => {
          const a = overlay.querySelector(`[data-a="${i}"]`);
          const b = overlay.querySelector(`[data-b="${i}"]`);
          if (a) a.value = set[0] ?? '';
          if (b) b.value = set[1] ?? '';
        });
      }
      const err = overlay.querySelector('[data-err]'); err.hidden = true; err.textContent = '';
      overlay.dataset.url = cfg.url;
      overlay.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      overlay.querySelector('.pub-sheet__in')?.focus();
    },
    hide,
    readSets() {
      const sets = [];
      for (let i = 0; i < 3; i++) {
        const a = overlay.querySelector(`[data-a="${i}"]`).value;
        const b = overlay.querySelector(`[data-b="${i}"]`).value;
        if (a !== '' || b !== '') sets.push([a === '' ? 0 : +a, b === '' ? 0 : +b]);
      }
      return sets;
    },
    showError(msg) {
      const err = overlay.querySelector('[data-err]');
      err.textContent = msg; err.hidden = false;
    },
    submitBtn: overlay.querySelector('[data-submit]'),
  };
}

export function initMatchPropose() {
  const proposeTriggers = document.querySelectorAll('[data-propose-match]');
  const predictTriggers = document.querySelectorAll('[data-predict-match]');
  if (!proposeTriggers.length && !predictTriggers.length) return;

  const sheet = buildSheet();
  let mode = 'propose'; // or 'predict'

  proposeTriggers.forEach((btn) => {
    btn.addEventListener('click', () => {
      mode = 'propose';
      sheet.show({
        url: btn.dataset.proposeUrl,
        a: btn.dataset.proposeA,
        b: btn.dataset.proposeB,
        ctx: btn.dataset.proposeCtx,
        heading: 'Proponer resultado',
        cta: 'Enviar propuesta',
        hint: 'El organizador revisará y confirmará tu propuesta.',
      });
    });
  });

  predictTriggers.forEach((btn) => {
    btn.addEventListener('click', () => {
      mode = 'predict';
      let current = null;
      try { current = btn.dataset.predictCurrent ? JSON.parse(btn.dataset.predictCurrent) : null; } catch { /* ignore */ }
      sheet.show({
        url: btn.dataset.predictUrl,
        a: btn.dataset.predictA,
        b: btn.dataset.predictB,
        ctx: btn.dataset.predictCtx,
        heading: 'Predice el marcador',
        cta: current ? 'Actualizar predicción' : 'Guardar predicción',
        hint: 'Adivina el marcador exacto. 1 punto si aciertas todos los sets.',
        prefill: current,
      });
    });
  });

  sheet.submitBtn.addEventListener('click', async () => {
    const sets = sheet.readSets();
    if (sets.length < 2) {
      sheet.showError('Ingresa el marcador de al menos dos sets.');
      return;
    }
    sheet.submitBtn.disabled = true;
    const label = sheet.submitBtn.textContent;
    sheet.submitBtn.textContent = 'Enviando…';
    try {
      const res = await fetch(sheet.el.dataset.url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf(),
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ sets }),
      });
      if (res.ok) {
        window.location.reload();
        return;
      }
      const data = await res.json().catch(() => ({}));
      const first = data?.errors ? Object.values(data.errors)[0]?.[0] : (data?.message || 'No se pudo enviar.');
      sheet.showError(first);
    } catch {
      sheet.showError('Error de red. Intenta de nuevo.');
    } finally {
      sheet.submitBtn.disabled = false;
      sheet.submitBtn.textContent = label;
    }
  });
}

export default { initMatchPropose };