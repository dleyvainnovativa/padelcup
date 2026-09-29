// Bracket preview + per-group winners (manager).
//
// Two jobs on the groups/draw page:
//   1. Live preview panel [data-bracket-preview] — shows "N clasificados →
//      M slots, K byes, primera ronda: Cuartos", plus a warning when the bracket
//      isn't clean (byes or same-group R1 collisions). Refreshes when the
//      per-group winner inputs change (saved via the winnersPerGroup endpoint).
//   2. Confirm-on-build — intercepts the "Generar llave" form; if the preview
//      isn't clean, shows a themed confirm before submitting.

import { post, get } from '../core/http';
import toast from '../core/toast';
import { confirm as confirmModal } from '../core/modal';

export function initBracketPreview() {
    const panel = document.querySelector('[data-bracket-preview]');
    if (!panel) return;

    const previewUrl = panel.dataset.previewUrl;
    const saveUrl = panel.dataset.saveUrl;

    function renderPreview(p) {
        if (!p) return;
        const cleanCls = p.clean ? 'is-clean' : 'is-warn';
        const byeTxt = p.byes > 0 ? `${p.byes} bye${p.byes === 1 ? '' : 's'}` : 'sin byes';
        panel.querySelector('[data-bp-summary]').innerHTML =
            `<strong>${p.qualifiers}</strong> clasificados → llave de <strong>${p.size}</strong> `
            + `(${byeTxt}) · 1ª ronda: <strong>${p.first_round}</strong>`;
        const hintEl = panel.querySelector('[data-bp-hint]');
        hintEl.textContent = p.hint || (p.clean ? 'Llave exacta, sin byes.' : '');
        hintEl.className = 'bp-hint ' + (p.hint ? 'is-warn' : 'is-ok');
        panel.classList.remove('is-clean', 'is-warn');
        panel.classList.add(cleanCls);
        panel.dataset.clean = p.clean ? '1' : '0';
    }

    async function refresh() {
        try {
            const p = await get(previewUrl);
            renderPreview(p);
        } catch (_) { /* leave last state */ }
    }

    // Per-group winner inputs → save (debounced) then re-render from response.
    let t = null;
    panel.querySelectorAll('[data-winners-input]').forEach((inp) => {
        inp.addEventListener('change', () => {
            clearTimeout(t);
            t = setTimeout(async () => {
                const winners = {};
                panel.querySelectorAll('[data-winners-input]').forEach((el) => {
                    winners[el.dataset.groupPos] = el.value;
                });
                try {
                    const res = await post(saveUrl, { winners });
                    if (res && res.preview) renderPreview(res.preview);
                    toast.success('Ganadores por grupo actualizados.');
                } catch (_) {
                    toast.error('No se pudo guardar.');
                }
            }, 350);
        });
    });

    // Confirm-on-build: intercept the build form when the preview isn't clean.
    const buildForm = document.querySelector('[data-bracket-build-form]');
    if (buildForm) {
        buildForm.addEventListener('submit', async (e) => {
            if (buildForm.dataset.confirmed === '1') return; // already confirmed
            // Only guard when we know the bracket is not clean.
            if (panel.dataset.clean === '1') return;
            e.preventDefault();

            // Re-fetch to be sure we warn on the latest state.
            let p = null;
            try { p = await get(previewUrl); } catch (_) {}
            if (p) renderPreview(p);

            if (p && p.clean) {
                buildForm.dataset.confirmed = '1';
                buildForm.submit();
                return;
            }

            const body = (p && p.hint)
                ? p.hint + '\n\n¿Generar la llave de todos modos?'
                : 'La llave tendrá byes o cruces del mismo grupo en la primera ronda. ¿Generar de todos modos?';
            const ok = await confirmModal({
                title: 'Revisar la llave',
                body,
                confirmText: 'Generar de todos modos',
                variant: 'danger',
            });
            if (ok) {
                buildForm.dataset.confirmed = '1';
                buildForm.submit();
            }
        });
    }

    // Initial state.
    refresh();
}
