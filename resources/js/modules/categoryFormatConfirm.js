// Category edit: confirm a format change through the themed modal (not a native
// window.confirm). The confirm fires only when the format actually changes AND
// the category already has results — because the server then rebuilds the
// structure and drops the old stage's matches. On OK we set the hidden
// confirm_structure_reset flag and resubmit.

import { confirm as confirmModal } from '../core/modal';

export function initCategoryFormatConfirm() {
  const form = document.getElementById('categoryEditForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    if (form.dataset.formatConfirmed === '1') return; // already confirmed; let it pass

    const sel = form.querySelector('select[name="format"]');
    const changed = sel && sel.value !== form.dataset.origFormat;
    if (!changed || form.dataset.hasResults !== '1') return; // no confirm needed

    e.preventDefault();
    const ok = await confirmModal({
      title: 'Cambiar el formato',
      body:
        'Al cambiar el formato se reconstruirá la estructura de la categoría y se ' +
        'perderán los partidos y resultados actuales. ¿Continuar?',
      confirmText: 'Cambiar formato',
      cancelText: 'Cancelar',
      variant: 'danger',
    });
    if (!ok) return;

    const flag = document.getElementById('confirmStructureReset');
    if (flag) flag.value = '1';
    form.dataset.formatConfirmed = '1';
    form.submit();
  });
}
