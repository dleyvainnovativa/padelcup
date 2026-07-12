// Public page enhancements: auto-refresh (live), Web Share, and QR generation.

export function initPublicPages() {
  restoreScroll();       // must run before other init, ASAP on load
  initAutoRefresh();
  initShare();
  initQR();
  initTabPersistence();
}

// --- State preservation across the auto-refresh reload --------------
// The page reloads every 60s to stay live. Without help, that throws the user
// back to the top and (for a manually-clicked tab) to the default tab. We save
// the scroll position (and let the tab live in the URL) so the reload lands the
// user exactly where they were.

// A key unique to this page (path + query), so two tabs/pages don't clobber.
function scrollKey() {
  return 'pc_scroll:' + location.pathname + location.search;
}

function saveScroll() {
  try {
    sessionStorage.setItem(scrollKey(), String(window.scrollY || window.pageYOffset || 0));
  } catch (e) { /* storage unavailable — ignore */ }
}

function restoreScroll() {
  try {
    const raw = sessionStorage.getItem(scrollKey());
    if (raw === null) return;
    const y = parseInt(raw, 10);
    if (!y) return;
    // Restore after layout settles (Alpine x-cloak, images, fonts). A couple of
    // rAFs + a short timeout covers most reflow; capped so we never fight the user.
    let tries = 0;
    const tryScroll = () => {
      window.scrollTo(0, y);
      if (++tries < 5 && Math.abs((window.scrollY || 0) - y) > 4) {
        requestAnimationFrame(tryScroll);
      }
    };
    requestAnimationFrame(tryScroll);
    setTimeout(() => window.scrollTo(0, y), 250);
    // Clear it so a manual navigation later doesn't unexpectedly jump.
    sessionStorage.removeItem(scrollKey());
  } catch (e) { /* ignore */ }
}

// --- Auto-refresh: reload every 60s, pause when tab hidden ---------
function initAutoRefresh() {
  const el = document.querySelector('[data-auto-refresh]');
  if (!el) return;

  const seconds = parseInt(el.dataset.autoRefresh, 10) || 60;
  let timer = null;

  const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      if (!document.hidden) {
        // Save where the user is so the reload lands them back in place.
        saveScroll();
        // Preserve the current query string (e.g. buscar mi partido).
        window.location.reload();
      } else {
        schedule(); // tab hidden — wait and check again
      }
    }, seconds * 1000);
  };

  // Pause/resume on visibility change.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) schedule();
  });

  schedule();
}

// --- Share: Web Share API with copy-link fallback ------------------
function initShare() {
  document.querySelectorAll('[data-share]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const url = btn.dataset.share || window.location.href;
      const title = btn.dataset.shareTitle || document.title;
      if (navigator.share) {
        try {
          await navigator.share({ title, url });
        } catch (e) { /* user cancelled */ }
      } else {
        try {
          await navigator.clipboard.writeText(url);
          const original = btn.innerHTML;
          btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado';
          setTimeout(() => { btn.innerHTML = original; }, 1600);
        } catch (e) {
          window.prompt('Copia el enlace:', url);
        }
      }
    });
  });
}

// --- QR: render a QR code for the target URL into [data-qr] ---------
async function initQR() {
  const holders = document.querySelectorAll('[data-qr]');
  if (!holders.length) return;

  // Load a tiny QR library from CDN on demand.
  await loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js');
  holders.forEach((h) => {
    const url = h.dataset.qr;
    if (!url || !window.QRCode) return;
    h.innerHTML = '';
    new window.QRCode(h, {
      text: url,
      width: 180,
      height: 180,
      colorDark: '#111111',
      colorLight: '#ffffff',
      correctLevel: window.QRCode.CorrectLevel.M,
    });
  });
}

function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const s = document.createElement('script');
    s.src = src;
    s.onload = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

// --- Tab persistence: write the active category tab into the URL ----
// The category page's Alpine reads ?tab= on load, but clicking a tab doesn't
// update the URL — so the 60s reload would drop a manually-chosen tab back to
// the default. We mirror the clicked tab into ?tab= via replaceState (no history
// spam, no navigation), so the reload restores it. Day/search filters already
// live in the URL, so they persist on their own.
function initTabPersistence() {
  const tabs = document.querySelectorAll('.pub-tabs .pub-tab');
  if (!tabs.length) return;

  // Map each tab button to its tab key by reading the Alpine @click expression
  // (tab = 'calendar' → 'calendar'). Falls back to aria-label if not found.
  const keyFor = (btn) => {
    const click = btn.getAttribute('@click') || btn.getAttribute('x-on:click') || '';
    const m = click.match(/tab\s*=\s*'([^']+)'/);
    return m ? m[1] : null;
  };

  tabs.forEach((btn) => {
    btn.addEventListener('click', () => {
      const key = keyFor(btn);
      if (!key) return;
      try {
        const url = new URL(location.href);
        url.searchParams.set('tab', key);
        history.replaceState(null, '', url);
      } catch (e) { /* ignore */ }
    });
  });
}