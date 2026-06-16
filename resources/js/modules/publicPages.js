// Public page enhancements: auto-refresh (live), Web Share, and QR generation.

export function initPublicPages() {
  initAutoRefresh();
  initShare();
  initQR();
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
