/**
 * Alpine component for the tiebreak-order editor: drag-to-reorder the active
 * criteria, remove one back to the "available" pool, or add one back. The active
 * list is mirrored into hidden <input name="tiebreak_order[]"> fields (in order),
 * so the form submits the exact order shown.
 */
export function registerTiebreakOrder(Alpine) {
  Alpine.data('tiebreakOrder', ({ active, inactive, labels }) => ({
    active: Array.isArray(active) ? [...active] : [],
    inactive: Array.isArray(inactive) ? [...inactive] : [],
    labels: labels || {},
    _from: null,

    dragStart(idx) { this._from = idx; },

    drop(idx) {
      if (this._from === null || this._from === idx) { this._from = null; return; }
      const moved = this.active.splice(this._from, 1)[0];
      this.active.splice(idx, 0, moved);
      this._from = null;
    },

    remove(idx) {
      const [key] = this.active.splice(idx, 1);
      if (key && !this.inactive.includes(key)) this.inactive.push(key);
    },

    add(key) {
      this.inactive = this.inactive.filter((k) => k !== key);
      if (!this.active.includes(key)) this.active.push(key);
    },
  }));
}
