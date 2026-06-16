// Bracket position swap: in "edit positions" mode, tap two round-1 slots to
// swap their occupants (seed labels or pairs). Posts to the swap route.

export function initBracketSwap() {
  const board = document.querySelector('[data-bracket-board]');
  const toggle = document.querySelector('[data-swap-toggle]');
  const swapUrl = board?.dataset.swapUrl;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!board || !toggle || !swapUrl) return;

  let editing = false;
  let first = null;

  toggle.addEventListener('click', () => {
    editing = !editing;
    board.classList.toggle('is-swapping', editing);
    toggle.classList.toggle('is-active', editing);
    toggle.innerHTML = editing
      ? '<i class="fa-solid fa-check"></i> Listo'
      : '<i class="fa-solid fa-arrows-up-down-left-right"></i> Editar posiciones';
    clearFirst();
  });

  board.addEventListener('click', (e) => {
    if (!editing) return;
    const slot = e.target.closest('[data-swap-slot]');
    if (!slot) return;
    e.preventDefault();
    e.stopPropagation();

    if (!first) {
      first = slot;
      slot.classList.add('is-selected');
      return;
    }
    if (slot === first) { clearFirst(); return; }

    // Second slot picked → submit the swap.
    postSwap({
      match_a: first.dataset.swapMatch,
      side_a: first.dataset.swapSide,
      match_b: slot.dataset.swapMatch,
      side_b: slot.dataset.swapSide,
    });
  });

  function clearFirst() {
    if (first) first.classList.remove('is-selected');
    first = null;
  }

  async function postSwap(payload) {
    try {
      const res = await fetch(swapUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload),
      });
      if (res.ok) {
        window.location.reload();
      } else {
        const data = await res.json().catch(() => ({}));
        alert(data.message || 'No se pudo intercambiar.');
        clearFirst();
      }
    } catch (err) {
      alert('Error de red al intercambiar.');
      clearFirst();
    }
  }
}
