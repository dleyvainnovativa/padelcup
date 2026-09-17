/**
 * Alpine component for the schedule "Jugadores" bottom sheet.
 *
 * The three lists (2+ categorías / horario preferido / 3+ en un día) are now
 * TABS. One shared search filters only the rows in the ACTIVE tab. Picking a
 * player drives the calendar highlight input (categoryHighlight.js) then closes
 * the sheet.
 */
export function registerPlayersSheet(Alpine) {
  Alpine.data('playersSheet', () => ({
    q: '',
    empty: false,
    plTab: '', // set by x-init in the blade to the first available tab

    init() {
      // Re-filter when the query OR the active tab changes.
      this.$watch('q', () => this.filter());
      this.$watch('plTab', () => this.filter());
      // Initial pass once the DOM (and plTab) are ready.
      this.$nextTick(() => this.filter());
    },

    filter() {
      const needle = this.q.trim().toLowerCase();
      const root = this.$root;
      let anyVisible = false;

      root.querySelectorAll('[data-pl-section]').forEach((sec) => {
        const isActiveTab = !this.plTab || sec.dataset.plTab === this.plTab;

        // Rows: visible only if in the active tab AND matching the query.
        sec.querySelectorAll('[data-pl-name]').forEach((row) => {
          const hitName = !needle || (row.dataset.plName || '').includes(needle);
          const show = isActiveTab && hitName;
          row.style.display = show ? '' : 'none';
          if (show) anyVisible = true;
        });
      });

      // Empty state = the active tab has no matching rows. (Section visibility
      // itself is handled by x-show="plTab === ..." in the blade.)
      this.empty = !anyVisible;
    },

    pick(name) {
      const input = document.querySelector('[data-player-highlight]');
      if (input) {
        input.value = name;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
      this.q = '';
      this.filter();
      this.$dispatch('close-players-sheet');
    },
  }));
}
