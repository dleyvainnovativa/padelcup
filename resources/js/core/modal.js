/**
 * core/modal.js
 * Helpers around Bootstrap 5 modals so the rest of the app doesn't
 * touch the Bootstrap API directly. Includes a promise-based confirm().
 *
 * Requires Bootstrap's Modal (imported in app.js as `bootstrap`).
 *
 * Usage:
 *   import { openModal, closeModal, confirm } from './core/modal';
 *   openModal('#editPairModal');
 *
 *   if (await confirm({ title: 'Eliminar grupo', body: '¿Seguro?' })) { ... }
 */

import { Modal } from 'bootstrap';

/** Get (or create) the Bootstrap Modal instance for a selector/element. */
function instance(target) {
  const el = typeof target === 'string' ? document.querySelector(target) : target;
  if (!el) return null;
  return Modal.getOrCreateInstance(el);
}

export function openModal(target)  { instance(target)?.show(); }
export function closeModal(target) { instance(target)?.hide(); }

/**
 * Promise-based confirmation dialog. Builds a transient modal, resolves
 * true on confirm and false on cancel/dismiss. No markup needed in the page.
 *
 * Pass `bodyList` (array of strings) instead of `body` to render a bulleted
 * list — e.g. a set of scheduling conflicts — with an optional `intro` line
 * above it. List items are inserted as text (no HTML injection).
 */
export function confirm({
  title = 'Confirmar',
  body = '¿Deseas continuar?',
  bodyList = null,       // optional array of strings → rendered as a list
  intro = null,          // optional lead-in line shown above bodyList
  confirmText = 'Confirmar',
  cancelText = 'Cancelar',
  variant = 'accent', // 'accent' | 'danger'
} = {}) {
  return new Promise((resolve) => {
    const confirmBtnClass = variant === 'danger' ? 'btn-danger' : 'btn-accent';

    const wrapper = document.createElement('div');
    wrapper.className = 'modal fade';
    wrapper.setAttribute('tabindex', '-1');
    wrapper.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg);border-color:var(--border);background:var(--surface);color:var(--text);">
          <div class="modal-header" style="border-color:var(--border);">
            <h5 class="modal-title" style="font-size:15px;font-weight:700;"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body" style="font-size:14px;color:var(--text-muted);"></div>
          <div class="modal-footer" style="border-color:var(--border);">
            <button type="button" class="btn btn-soft" data-role="cancel"></button>
            <button type="button" class="btn ${confirmBtnClass}" data-role="confirm"></button>
          </div>
        </div>
      </div>`;

    wrapper.querySelector('.modal-title').textContent = title;

    // Body: either a plain-text message, or a structured list (bodyList) with an
    // optional intro line. bodyList items are set via textContent (no HTML
    // injection). Falls back to the plain `body` string for existing callers.
    const bodyEl = wrapper.querySelector('.modal-body');
    if (Array.isArray(bodyList) && bodyList.length) {
      bodyEl.textContent = '';
      if (intro) {
        const p = document.createElement('div');
        p.textContent = intro;
        p.style.marginBottom = '8px';
        bodyEl.appendChild(p);
      }
      const ul = document.createElement('ul');
      ul.style.cssText = 'margin:0;padding-left:18px;display:flex;flex-direction:column;gap:4px;';
      bodyList.forEach((line) => {
        const li = document.createElement('li');
        li.textContent = line;
        ul.appendChild(li);
      });
      bodyEl.appendChild(ul);
    } else {
      bodyEl.textContent = body;
    }

    wrapper.querySelector('[data-role="cancel"]').textContent = cancelText;
    wrapper.querySelector('[data-role="confirm"]').textContent = confirmText;

    document.body.appendChild(wrapper);
    const modal = Modal.getOrCreateInstance(wrapper);

    let result = false;
    wrapper.querySelector('[data-role="confirm"]').addEventListener('click', () => {
      result = true;
      modal.hide();
    });
    wrapper.querySelector('[data-role="cancel"]').addEventListener('click', () => {
      result = false;
      modal.hide();
    });
    wrapper.addEventListener('hidden.bs.modal', () => {
      wrapper.remove();
      resolve(result);
    }, { once: true });

    modal.show();
  });
}

export default { openModal, closeModal, confirm };