// Agile score entry for the results page. Delegated handlers so it works for
// every [data-score-input] (current and dynamically shown via Alpine).
//
// Behavior:
//  - focus/tap → select all (typing overwrites cleanly)
//  - type a single digit (0–7) → fill + auto-advance to the next score input
//  - backspace on an empty field → jump back to the previous field
//  - Enter → submit the form
//  - only digits 0–7 allowed

export function initScoreEntry() {
  const isScore = (el) => el && el.matches?.('[data-score-input]');

  // All score inputs that belong to the SAME form, in DOM order.
  function siblings(input) {
    const form = input.closest('form');
    if (!form) return [input];
    return Array.from(form.querySelectorAll('[data-score-input]'));
  }

  function focusNext(input) {
    const list = siblings(input);
    const i = list.indexOf(input);
    if (i > -1 && i < list.length - 1) {
      const next = list[i + 1];
      next.focus();
      next.select?.();
    } else {
      // Last field: drop focus so Enter/submit is the natural next action.
      input.blur();
    }
  }

  function focusPrev(input) {
    const list = siblings(input);
    const i = list.indexOf(input);
    if (i > 0) {
      const prev = list[i - 1];
      prev.focus();
      prev.select?.();
    }
  }

  // Select-all on focus (covers tap on mobile and click on desktop).
  document.addEventListener('focusin', (e) => {
    if (isScore(e.target)) {
      // Defer so the browser's own focus selection doesn't override us.
      setTimeout(() => e.target.select?.(), 0);
    }
  });

  // Keypress filtering + Enter-to-submit + backspace-to-previous.
  document.addEventListener('keydown', (e) => {
    if (!isScore(e.target)) return;
    const input = e.target;

    if (e.key === 'Enter') {
      e.preventDefault();
      const form = input.closest('form');
      form?.requestSubmit?.() ?? form?.submit();
      return;
    }

    if (e.key === 'Backspace' && input.value === '') {
      e.preventDefault();
      focusPrev(input);
      return;
    }

    // Allow navigation / control keys.
    const allowed = ['Backspace', 'Tab', 'ArrowLeft', 'ArrowRight', 'Delete', 'Home', 'End'];
    if (allowed.includes(e.key) || e.metaKey || e.ctrlKey) return;

    // Only digits 0–7 are valid padel set scores.
    if (!/^[0-7]$/.test(e.key)) {
      e.preventDefault();
    }
  });

  // Auto-advance after a valid digit is entered.
  document.addEventListener('input', (e) => {
    if (!isScore(e.target)) return;
    const input = e.target;
    // Clamp to a single 0–7 digit.
    let v = (input.value || '').replace(/[^0-7]/g, '');
    if (v.length > 1) v = v.slice(-1); // keep last typed digit
    input.value = v;
    if (v.length === 1) {
      focusNext(input);
    }
  });
}