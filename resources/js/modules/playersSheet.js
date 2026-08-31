/**
 * Alpine component for the schedule "Jugadores" bottom sheet: live-filters the
 * player rows by name, and on pick sets the calendar highlight search input
 * (driving categoryHighlight.js) then closes the sheet.
 */
export function registerPlayersSheet(Alpine) {
  Alpine.data('playersSheet', () => ({
    q: '',
    empty: false,

    init() {
      // Re-filter whenever the query changes.
      this.$watch('q', () => this.filter());
    },

    filter() {
      const needle = this.q.trim().toLowerCase();
      const root = this.$root;
      let anyVisible = false;

      root.querySelectorAll('[data-pl-name]').forEach((row) => {
        const hit = !needle || (row.dataset.plName || '').includes(needle);
        row.style.display = hit ? '' : 'none';
        if (hit) anyVisible = true;
      });

      // Hide section headers whose rows are all filtered out.
      root.querySelectorAll('[data-pl-section]').forEach((sec) => {
        const visible = sec.querySelectorAll('[data-pl-name]:not([style*="display: none"])').length;
        sec.style.display = visible ? '' : 'none';
      });

      this.empty = !anyVisible;
    },

    pick(name) {
      // Drive the existing calendar highlight bar.
      const input = document.querySelector('[data-player-highlight]');
      if (input) {
        input.value = name;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
      // Reset filter and ask the parent to close the sheet.
      this.q = '';
      this.filter();
      this.$dispatch('close-players-sheet');
    },
  }));
}
