/**
 * resources/js/app.js
 * Vite entry point. Imports fonts, Bootstrap JS, the theme stylesheet,
 * and initializes the core modules. Feature modules (scheduler, bracket,
 * groupBuilder) are imported lazily only on the pages that need them.
 */

// --- Fonts (self-hosted via @fontsource) ---
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/700.css';

// --- Bootstrap JS (we import the bundle for Modal/Dropdown/etc.) ---
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap; // available for inline data-bs-* components

// --- Alpine.js for interactive islands ---
import Alpine from 'alpinejs';
window.Alpine = Alpine;

// --- Styles: Bootstrap first, then our theme so tokens win ---
import 'bootstrap/dist/css/bootstrap.min.css';
import '../css/theme.css';

// --- Core modules ---
import { initTheme } from './core/theme';
import { toast } from './core/toast';
import http from './core/http';
import modal from './core/modal';
import forms from './core/forms';
import loading from './core/loading';

// Expose a small app namespace for inline scripts / debugging
window.TC = { toast, http, modal, forms, loading };

// --- Mobile sidebar toggle (works without a framework) ---
function initSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const backdrop = document.querySelector('.sidebar-backdrop');
  document.querySelectorAll('[data-sidebar-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      sidebar?.classList.toggle('is-open');
      backdrop?.classList.toggle('is-open');
    });
  });
  backdrop?.addEventListener('click', () => {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-open');
  });
}

// --- Boot ---
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initSidebar();

  // Declarative themed confirmations (replaces native confirm()).
  import('./modules/confirms').then((m) => m.initConfirms());

  // Expose the themed confirm for inline page scripts that need it.
  window.tcConfirm = modal.confirm;

  // Feature module: group builder (only on the groups page).
  if (document.querySelector('[data-group-board]')) {
    import('./modules/groupBuilder').then((m) => m.initGroupBuilder());
  }

  // Feature module: scheduling board (only on the calendar page).
  if (document.querySelector('[data-sched-board]')) {
    import('./modules/schedule').then((m) => m.initSchedule());
  }

  // Feature module: bracket results (tap a bracket match to score).
  if (document.querySelector('[data-bracket-board]')) {
    import('./modules/bracketResults').then((m) => m.initBracketResults());
    import('./modules/bracketSwap').then((m) => m.initBracketSwap());
  }

  // Feature module: agile score entry (results page).
  if (document.querySelector('[data-score-input]')) {
    import('./modules/scoreEntry').then((m) => m.initScoreEntry());
  }

  // Feature module: public pages (auto-refresh, share, QR).
  if (document.querySelector('[data-auto-refresh], [data-share], [data-qr]')) {
    import('./modules/publicPages').then((m) => m.initPublicPages());
  }

  // Feature module: shareable match result image.
  if (document.querySelector('[data-share-match]')) {
    import('./modules/matchShare').then((m) => m.initMatchShare());
  }
  Alpine.start();
});
