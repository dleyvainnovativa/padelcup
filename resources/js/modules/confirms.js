import { confirm as confirmModal } from '../core/modal';

/**
 * Declarative confirmation: any <form data-confirm="message"> or
 * <a data-confirm="message"> shows the themed modal before proceeding,
 * replacing native confirm(). Optional data attributes:
 *   data-confirm-title, data-confirm-ok, data-confirm-variant (accent|danger)
 *
 * Call initConfirms() once on load.
 */
export function initConfirms() {
  // Forms: intercept submit, run themed confirm, resubmit if accepted.
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (form.dataset.confirmed === '1') return; // already confirmed; let it pass

    e.preventDefault();
    const ok = await confirmModal({
      title: form.dataset.confirmTitle || 'Confirmar',
      body: form.dataset.confirm,
      confirmText: form.dataset.confirmOk || 'Confirmar',
      variant: form.dataset.confirmVariant || 'accent',
    });
    if (ok) {
      form.dataset.confirmed = '1';
      form.submit();
    }
  });

  // Links: intercept click.
  document.addEventListener('click', async (e) => {
    const link = e.target.closest('a[data-confirm]');
    if (!link) return;

    e.preventDefault();
    const ok = await confirmModal({
      title: link.dataset.confirmTitle || 'Confirmar',
      body: link.dataset.confirm,
      confirmText: link.dataset.confirmOk || 'Confirmar',
      variant: link.dataset.confirmVariant || 'accent',
    });
    if (ok) window.location.href = link.href;
  });
}