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
 */
export function confirm({
  title = 'Confirmar',
  body = '¿Deseas continuar?',
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
    wrapper.querySelector('.modal-body').textContent = body;
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