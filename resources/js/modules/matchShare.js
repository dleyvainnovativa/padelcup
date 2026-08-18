// Match result share studio.
//
// Replaces the old instant-download card. Now, clicking a [data-share-match]
// button opens a bottom-to-top drawer where the user:
//   1. uploads a background photo (the export auto-sizes to this image),
//   2. picks one of 3 themes (Ledger / Paper / Hero) — theme sets TEXT color,
//   3. tunes a background PANEL color + its opacity (color picker + slider),
//   4. exports a PNG compositing photo + themed scoreboard overlay.
//
// Library-free: pure canvas. Portrait-oriented layout scales proportionally to
// the uploaded image (landscape is a future second ratio branch). Uses the
// app's existing fonts (Inter via --font-ui, JetBrains Mono via --font-mono).
//
// Data contract is unchanged from the old module:
//   d = { tournament, category, context, pairA, pairB, sets:[[a,b],...], winner:'a'|'b' }

export function initMatchShare() {
  let drawer = null; // singleton drawer instance

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-share-match]');
    if (!btn) return;
    e.preventDefault();
    const data = JSON.parse(btn.dataset.shareMatch);
    if (!drawer) drawer = createDrawer();
    drawer.open(data);
  });
}

/* ============================================================================
   THEMES — panel color/opacity are user-editable defaults. Text colors derive
   from a single `ink` (black or white) at opacity steps, so a switch can flip
   ALL text at once. `accent` is independent and never flips (Paper stays green).
   `defaultInk` seeds the switch when a theme is chosen.
   ========================================================================== */
const THEMES = {
  ledger: {
    label: 'Ledger',
    panel: '#0C0C0E', panelOpacity: 0.55,
    defaultInk: 'white',
    accent: null,                 // no colored accent (winner dot uses ink)
  },
  paper: {
    label: 'Paper',
    panel: '#0C0C0E', panelOpacity: 0.55,
    defaultInk: 'white',
    accent: '#3da26e',            // green — stays green regardless of ink switch
  },
  hero: {
    label: 'Hero',
    panel: '#101014', panelOpacity: 0.55,
    defaultInk: 'white',
    accent: null,
  },
};

// Build the full color set for a theme given the chosen ink (black|white).
function inkColors(ink) {
  const base = ink === 'black' ? '20,20,15' : '255,255,255';
  return {
    text:  `rgba(${base},1)`,
    muted: `rgba(${base},0.55)`,
    faint: `rgba(${base},0.42)`,
    line:  `rgba(${base},0.14)`,
    loser: `rgba(${base},0.42)`,
    box:   `rgba(${base},0.12)`,   // empty score-box fill
  };
}

/* ============================================================================
   DRAWER
   ========================================================================== */
function createDrawer() {
  // State for the current session.
  const state = {
    data: null,
    img: null,            // HTMLImageElement of the uploaded photo
    theme: 'ledger',
    ink: THEMES.ledger.defaultInk,  // 'black' | 'white' — flips ALL text
    panelColor: THEMES.ledger.panel,
    panelOpacity: THEMES.ledger.panelOpacity,
  };

  // --- Build DOM ---
  const root = document.createElement('div');
  root.className = 'ms-drawer';
  root.innerHTML = `
    <div class="ms-drawer__scrim" data-ms-close></div>
    <div class="ms-drawer__sheet" role="dialog" aria-modal="true" aria-label="Compartir resultado">
      <div class="ms-drawer__grip"></div>
      <div class="ms-drawer__head">
        <h3 class="ms-drawer__title">Compartir resultado</h3>
        <button type="button" class="ms-drawer__x" data-ms-close aria-label="Cerrar">&times;</button>
      </div>

      <div class="ms-drawer__body">
        <div class="ms-preview">
          <canvas class="ms-preview__canvas" data-ms-canvas></canvas>
          <label class="ms-preview__empty" data-ms-drop>
            <input type="file" accept="image/*" data-ms-file hidden>
            <div class="ms-preview__empty-inner">
              <div class="ms-preview__icon"><i class="fas fa-camera"></i></div>
              <div class="ms-preview__hint">Toca para subir una foto</div>
              <div class="ms-preview__sub">La imagen del resultado se ajusta a tu foto</div>
            </div>
          </label>
        </div>

        <div class="ms-controls">
          <div class="ms-field">
            <div class="ms-field__label">Tema</div>
            <div class="ms-themes" data-ms-themes></div>
          </div>

          <div class="ms-field">
            <div class="ms-field__label">Fondo del panel</div>
            <div class="ms-row">
              <input type="color" class="ms-color" data-ms-color value="#0C0C0E">
              <input type="range" class="ms-range" data-ms-opacity min="0" max="100" value="62">
              <span class="ms-range__val" data-ms-opacity-val>62%</span>
            </div>
          </div>

          <div class="ms-field">
            <div class="ms-field__label">Color del texto</div>
            <div class="ms-ink" data-ms-ink>
              <button type="button" class="ms-ink__opt" data-ms-ink-opt="black">
                <span class="ms-ink__dot" style="background:#111"></span> Negro
              </button>
              <button type="button" class="ms-ink__opt" data-ms-ink-opt="white">
                <span class="ms-ink__dot" style="background:#fff;border:1px solid #ccc"></span> Blanco
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="ms-drawer__foot">
        <button type="button" class="ms-btn ms-btn--ghost" data-ms-rephoto disabled>Cambiar foto</button>
        <button type="button" class="ms-btn ms-btn--ghost" data-ms-export disabled>Descargar</button>
        <button type="button" class="ms-btn ms-btn--primary" data-ms-share disabled hidden>
          <span class="ms-btn__ico"><i class='fas fa-arrow-up-right-from-square'></i></span> Compartir
        </button>
      </div>
    </div>`;
  document.body.appendChild(root);

  // --- Refs ---
  const $ = (sel) => root.querySelector(sel);
  const canvas = $('[data-ms-canvas]');
  const emptyEl = $('[data-ms-drop]');
  const fileInput = $('[data-ms-file]');
  const themesEl = $('[data-ms-themes]');
  const colorInput = $('[data-ms-color]');
  const opacityInput = $('[data-ms-opacity]');
  const opacityVal = $('[data-ms-opacity-val]');
  const inkEl = $('[data-ms-ink]');
  const exportBtn = $('[data-ms-export]');
  const rephotoBtn = $('[data-ms-rephoto]');
  const shareBtn = $('[data-ms-share]');

  // Show the "Compartir" button only where the browser can share files
  // (mobile Safari/Chrome). Elsewhere the download button is the primary action.
  const canShareFiles = !!(navigator.canShare && (() => {
    try { return navigator.canShare({ files: [new File([new Blob()], 'x.png', { type: 'image/png' })] }); }
    catch (e) { return false; }
  })());
  if (canShareFiles) {
    shareBtn.hidden = false;
    exportBtn.classList.remove('ms-btn--primary');
  } else {
    // No file share: download is the primary action.
    exportBtn.classList.add('ms-btn--primary');
    exportBtn.classList.remove('ms-btn--ghost');
  }

  // Reflect ink selection in the toggle.
  function syncInkUI() {
    inkEl.querySelectorAll('[data-ms-ink-opt]').forEach((el) =>
      el.classList.toggle('is-active', el.dataset.msInkOpt === state.ink));
  }
  syncInkUI();

  // Theme thumbnails.
  Object.entries(THEMES).forEach(([key, t]) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'ms-theme' + (key === state.theme ? ' is-active' : '');
    b.dataset.msTheme = key;
    b.innerHTML = `<span class="ms-theme__sw" style="background:${t.panel}"></span><span class="ms-theme__name">${t.label}</span>`;
    themesEl.appendChild(b);
  });

  // --- Events ---
  root.querySelectorAll('[data-ms-close]').forEach((el) =>
    el.addEventListener('click', close));

  fileInput.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (file) loadPhoto(file);
  });

  // Drag & drop onto the empty area.
  emptyEl.addEventListener('dragover', (e) => { e.preventDefault(); emptyEl.classList.add('is-drag'); });
  emptyEl.addEventListener('dragleave', () => emptyEl.classList.remove('is-drag'));
  emptyEl.addEventListener('drop', (e) => {
    e.preventDefault();
    emptyEl.classList.remove('is-drag');
    const file = e.dataTransfer.files && e.dataTransfer.files[0];
    if (file) loadPhoto(file);
  });

  rephotoBtn.addEventListener('click', () => fileInput.click());

  themesEl.addEventListener('click', (e) => {
    const b = e.target.closest('[data-ms-theme]');
    if (!b) return;
    state.theme = b.dataset.msTheme;
    const t = THEMES[state.theme];
    // Switching theme resets panel color/opacity AND seeds the ink to that
    // theme's default (user can still flip it afterward — independent toggle).
    state.panelColor = t.panel;
    state.panelOpacity = t.panelOpacity;
    state.ink = t.defaultInk;
    colorInput.value = t.panel;
    opacityInput.value = Math.round(t.panelOpacity * 100);
    opacityVal.textContent = Math.round(t.panelOpacity * 100) + '%';
    syncInkUI();
    themesEl.querySelectorAll('[data-ms-theme]').forEach((el) =>
      el.classList.toggle('is-active', el === b));
    render();
  });

  inkEl.addEventListener('click', (e) => {
    const b = e.target.closest('[data-ms-ink-opt]');
    if (!b) return;
    state.ink = b.dataset.msInkOpt; // 'black' | 'white'
    syncInkUI();
    render();
  });

  colorInput.addEventListener('input', () => {
    state.panelColor = colorInput.value;
    render();
  });
  opacityInput.addEventListener('input', () => {
    state.panelOpacity = opacityInput.value / 100;
    opacityVal.textContent = opacityInput.value + '%';
    render();
  });

  exportBtn.addEventListener('click', exportPng);
  shareBtn.addEventListener('click', sharePng);

  // --- Photo loading ---
  function loadPhoto(file) {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      state.img = img;
      URL.revokeObjectURL(url);
      emptyEl.style.display = 'none';
      canvas.style.display = 'block';
      exportBtn.disabled = false;
      rephotoBtn.disabled = false;
      if (shareBtn) shareBtn.disabled = false;
      render();
    };
    img.onerror = () => { URL.revokeObjectURL(url); };
    img.src = url;
  }

  // --- Public API ---
  function open(data) {
    state.data = data;
    // Reset per-open visual state (keep last theme choice).
    state.img = null;
    emptyEl.style.display = '';
    canvas.style.display = 'none';
    exportBtn.disabled = true;
    rephotoBtn.disabled = true;
    if (shareBtn) shareBtn.disabled = true;
    fileInput.value = '';

    // Pause the public-page 60s auto-refresh so it can't reload the page and
    // wipe the editor mid-edit. publicPages.js checks this flag.
    document.body.classList.add('pc-drawer-open');

    // Lock background scroll, compensating for the scrollbar width so the page
    // doesn't jump/shift when the scrollbar disappears.
    const sbw = window.innerWidth - document.documentElement.clientWidth;
    if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
    document.body.style.overflow = 'hidden';

    // Make the drawer displayable FIRST (removes display:none), then add the
    // .is-open class on the NEXT frame so the CSS transform has a starting
    // point to animate from. Adding both in one frame makes it appear instantly.
    root.style.display = 'block';
    requestAnimationFrame(() => {
      requestAnimationFrame(() => root.classList.add('is-open'));
    });
  }
  function close() {
    root.classList.remove('is-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    document.body.classList.remove('pc-drawer-open');
    // Hide after the slide-out transition finishes (matches CSS duration).
    setTimeout(() => { if (!root.classList.contains('is-open')) root.style.display = 'none'; }, 300);
  }

  // --- Render (preview) ---
  function render() {
    if (!state.img || !state.data) return;
    drawComposite(canvas, state.img, state.data, {
      theme: state.theme,
      ink: state.ink,
      panelColor: state.panelColor,
      panelOpacity: state.panelOpacity,
      preview: true,
    });
  }

  // --- Render the full-res PNG to a Blob (shared by download + share) ---
  function makeBlob() {
    return new Promise((resolve) => {
      if (!state.img || !state.data) return resolve(null);
      const out = document.createElement('canvas');
      drawComposite(out, state.img, state.data, {
        theme: state.theme,
        ink: state.ink,
        panelColor: state.panelColor,
        panelOpacity: state.panelOpacity,
        preview: false,
      });
      out.toBlob((blob) => resolve(blob), 'image/png');
    });
  }

  function filename() {
    return slug(state.data.category || 'partido') + '-resultado.png';
  }

  // --- Download (fallback / desktop) ---
  async function exportPng() {
    const blob = await makeBlob();
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename();
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  // --- Native share sheet (Instagram Stories, WhatsApp, Facebook, …) ---
  // Uses the Web Share API with a file. On phones this opens the OS share
  // sheet with all installed apps as targets — the closest thing to a direct
  // "post to Stories" a web page can do. Falls back to download when the
  // browser can't share files (most desktops).
  async function sharePng() {
    const blob = await makeBlob();
    if (!blob) return;
    const file = new File([blob], filename(), { type: 'image/png' });

    if (navigator.canShare && navigator.canShare({ files: [file] })) {
      try {
        await navigator.share({
          files: [file],
          title: state.data.category || 'Resultado',
          text: shareCaption(state.data),
        });
      } catch (e) {
        // User cancelled the share sheet — do nothing.
      }
    } else {
      // No file-share support → download instead.
      await exportPng();
    }
  }

  return { open, close };
}

/* ============================================================================
   COMPOSITOR — draws photo + themed scoreboard overlay onto a canvas.
   Auto-sizes to the uploaded image. Layout is proportional to image height so
   it scales to any portrait ratio (1080×1350, 1080×1920, etc.).
   ========================================================================== */
function drawComposite(canvas, img, d, opts) {
  const theme = THEMES[opts.theme] || THEMES.ledger;
  const ink = opts.ink || theme.defaultInk;
  const c = inkColors(ink);          // text colors derived from ink
  const accent = theme.accent;       // fixed accent (Paper green), or null

  // Export at the photo's native size (capped for preview to keep it snappy).
  const maxW = opts.preview ? 720 : img.naturalWidth;
  const scaleToOut = maxW / img.naturalWidth;
  const W = Math.round(img.naturalWidth * scaleToOut);
  const H = Math.round(img.naturalHeight * scaleToOut);

  canvas.width = W;
  canvas.height = H;
  const ctx = canvas.getContext('2d');

  // 1) Photo.
  ctx.clearRect(0, 0, W, H);
  ctx.drawImage(img, 0, 0, W, H);

  // 2) Panel overlay (user color + opacity).
  const [pr, pg, pb] = hexToRgb(opts.panelColor);
  ctx.fillStyle = `rgba(${pr},${pg},${pb},${opts.panelOpacity})`;
  ctx.fillRect(0, 0, W, H);

  // Unit = proportional to width so type scales with the image.
  const u = W / 1080; // design was authored at 1080 wide
  const pad = 80 * u;

  const fUI = getComputedStyle(document.documentElement).getPropertyValue('--font-ui').trim()
    || "'Inter', sans-serif";
  const fMono = getComputedStyle(document.documentElement).getPropertyValue('--font-mono').trim()
    || "'JetBrains Mono', monospace";

  // Route to the theme layout.
  const layout = { ctx, W, H, u, pad, c, accent, fUI, fMono, d };
  if (opts.theme === 'paper') drawPaper(layout);
  else if (opts.theme === 'hero') drawHero(layout);
  else drawLedger(layout);
}

/* ---- Shared helpers ---- */
function setsAndGames(d) {
  let aSets = 0, bSets = 0, aGames = 0, bGames = 0;
  (d.sets || []).forEach(([a, b]) => {
    a = +a || 0; b = +b || 0;
    aGames += a; bGames += b;
    if (a > b) aSets++; else if (b > a) bSets++;
  });
  return { aSets, bSets, aGames, bGames };
}

function pairName(d, side) {
  const raw = side === 'a' ? d.pairA : d.pairB;
  return raw || '';
}

/* ---- Theme 1: LEDGER (dark, stat strip) — matches reference HTML spacing ---- */
function drawLedger({ ctx, W, H, u, pad, c, fUI, fMono, d }) {
  const winA = d.winner === 'a', winB = d.winner === 'b';
  const { aSets, bSets, aGames, bGames } = setsAndGames(d);

  // Header: tournament (mono, muted) + category (bold) left; group/round right.
  ctx.textAlign = 'left';
  ctx.fillStyle = c.muted;
  ctx.font = `500 ${20 * u}px ${fMono}`;
  ctx.fillText((d.tournament || '').toUpperCase(), pad, 96 * u);

  ctx.fillStyle = c.text;
  ctx.font = `700 ${40 * u}px ${fUI}`;
  ctx.fillText(truncate(d.category || '', 30), pad, 148 * u);

  if (d.context) {
    ctx.textAlign = 'right';
    ctx.fillStyle = c.muted;
    ctx.font = `500 ${19 * u}px ${fMono}`;
    ctx.fillText(d.context.toUpperCase(), W - pad, 120 * u);
  }
  ctx.textAlign = 'left';
  line(ctx, pad, 186 * u, W - pad, 186 * u, c.line);

  // --- Two spacious score rows (names stacked on two lines, big score column).
  // Mirrors the HTML: each row ~38px vertical padding, a divider between them.
  const rowH = 210 * u;               // generous row height (was cramped before)
  const block1Top = H * 0.30;         // start of first row
  const rowMidGap = rowH;             // second row sits a full rowH below

  drawLedgerRow(ctx, W, pad, u, block1Top, pairName(d, 'a'),
    d.sets.map((s) => s[0]), winA, c, fUI);

  // Divider between the two pairs.
  const divY = block1Top + rowH - 78 * u;
  line(ctx, pad, divY, W - pad, divY, c.line);

  drawLedgerRow(ctx, W, pad, u, block1Top + rowMidGap, pairName(d, 'b'),
    d.sets.map((s) => s[1]), winB, c, fUI);

  // Stat strip (SETS / GAMES) — no duration.
  const stripY = H - 200 * u;
  line(ctx, pad, stripY, W - pad, stripY, c.line);
  const colW = (W - pad * 2) / 2;
  stat(ctx, pad, stripY + 62 * u, u, 'SETS', `${aSets} – ${bSets}`, c, fMono, fUI);
  stat(ctx, pad + colW, stripY + 62 * u, u, 'GAMES', `${aGames} – ${bGames}`, c, fMono, fUI);

  // Footer brand.
  ctx.textAlign = 'left';
  ctx.fillStyle = c.muted;
  ctx.font = `500 ${19 * u}px ${fMono}`;
  ctx.fillText('PADELCUP', pad, H - 56 * u);
}

// One Ledger row: name stacked on up to 2 lines (left), score digits big (right),
// vertically centered together with real breathing room.
function drawLedgerRow(ctx, W, pad, u, top, name, scores, isWin, c, fUI) {
  // Dimming follows the actual result: the WINNER is bright/bold, the loser is
  // muted — regardless of whether the winner is pair A or pair B.
  const nameColor = isWin ? c.text : c.loser;
  const scoreColor = isWin ? c.text : c.loser;

  // Split the pair name onto two lines at " / " or " · " if present.
  const parts = splitPair(name);
  const nameSize = 54 * u;
  const nameLH = 62 * u;

  // Winner dot + name lines, left column.
  let nameX = pad;
  const centerY = top + (parts.length > 1 ? nameLH * 0.5 : 0);
  if (isWin) {
    ctx.fillStyle = c.text;
    ctx.beginPath();
    ctx.arc(pad + 11 * u, top - nameSize * 0.35, 10 * u, 0, Math.PI * 2);
    ctx.fill();
    nameX = pad + 40 * u;
  }
  ctx.textAlign = 'left';
  ctx.fillStyle = nameColor;
  ctx.font = `${isWin ? 700 : 500} ${nameSize}px ${fUI}`;
  parts.forEach((ln, i) => {
    ctx.fillText(truncate(ln, 22), nameX, top + i * nameLH);
  });

  // Score digits, right column — vertically aligned to the name block center.
  const scoreY = top + (parts.length > 1 ? nameLH : 0) - 6 * u;
  ctx.textAlign = 'center';
  const size = 100 * u, gap = 30 * u;
  const total = scores.length * size + (scores.length - 1) * gap;
  let x = W - pad - total + size / 2;
  ctx.fillStyle = scoreColor;
  ctx.font = `${isWin ? 800 : 600} ${104 * u}px ${fUI}`;
  scores.forEach((s) => {
    ctx.fillText(String(s), x, scoreY);
    x += size + gap;
  });
}

/* ---- Theme 2: PAPER (light, one accent) — roomier name→score spacing ---- */
function drawPaper({ ctx, W, H, u, pad, c, accent, fUI, fMono, d }) {
  const { aSets, bSets, aGames, bGames } = setsAndGames(d);
  const green = accent || c.text;

  // Header (two mono lines) + accent dot.
  ctx.textAlign = 'left';
  ctx.fillStyle = c.muted;
  ctx.font = `500 ${20 * u}px ${fMono}`;
  ctx.fillText((d.tournament || '').toUpperCase(), pad, 96 * u);
  const ctxLine = [d.category, d.context].filter(Boolean).join(' · ').toUpperCase();
  ctx.fillText(truncate(ctxLine, 40), pad, 130 * u);

  ctx.fillStyle = green;
  ctx.beginPath(); ctx.arc(W - pad - 11 * u, 92 * u, 11 * u, 0, Math.PI * 2); ctx.fill();

  // Winners block — GANADORES label, big stacked name, then score on its OWN
  // line well below (this is the spacing the reference shows).
  const top = H * 0.34;
  ctx.fillStyle = green;
  ctx.font = `500 ${18 * u}px ${fMono}`;
  ctx.fillText('GANADORES', pad, top);

  const winnerName = d.winner === 'b' ? pairName(d, 'b') : pairName(d, 'a');
  const loserName = d.winner === 'b' ? pairName(d, 'a') : pairName(d, 'b');
  const winScores = d.winner === 'b' ? d.sets.map((s) => s[1]) : d.sets.map((s) => s[0]);
  const loseScores = d.winner === 'b' ? d.sets.map((s) => s[0]) : d.sets.map((s) => s[1]);

  // Winner name, up to two lines.
  ctx.fillStyle = c.text;
  ctx.font = `700 ${78 * u}px ${fUI}`;
  const wParts = splitPair(winnerName);
  const nLH = 82 * u;
  wParts.forEach((ln, i) => ctx.fillText(truncate(ln, 20), pad, top + 76 * u + i * nLH));

  // Winner score — its own line, generous gap below the name.
  const scoreY = top + 76 * u + wParts.length * nLH + 96 * u;
  ctx.fillStyle = green;
  ctx.font = `800 ${132 * u}px ${fUI}`;
  drawScoreDigits(ctx, pad, scoreY, u, winScores, 40 * u, 'left');

  // Divider.
  const divY = scoreY + 56 * u;
  line(ctx, pad, divY, W - pad, divY, c.line);

  // Loser name + score, muted, with matching breathing room.
  ctx.fillStyle = c.loser;
  ctx.font = `500 ${44 * u}px ${fUI}`;
  ctx.fillText(truncate(loserName, 30), pad, divY + 78 * u);
  ctx.font = `600 ${64 * u}px ${fUI}`;
  drawScoreDigits(ctx, pad, divY + 156 * u, u, loseScores, 30 * u, 'left');

  // Footer stats + brand.
  ctx.fillStyle = c.faint;
  ctx.font = `500 ${17 * u}px ${fMono}`;
  ctx.fillText('SETS', pad, H - 118 * u);
  ctx.fillText('GAMES', pad + 240 * u, H - 118 * u);
  ctx.fillStyle = c.text;
  ctx.font = `700 ${34 * u}px ${fUI}`;
  ctx.fillText(`${aSets} – ${bSets}`, pad, H - 78 * u);
  ctx.fillText(`${aGames} – ${bGames}`, pad + 240 * u, H - 78 * u);

  ctx.textAlign = 'right';
  ctx.fillStyle = c.muted;
  ctx.font = `500 ${19 * u}px ${fMono}`;
  ctx.fillText('PADELCUP', W - pad, H - 78 * u);
  ctx.textAlign = 'left';
}

// Draw a row of big score digits from an x anchor.
function drawScoreDigits(ctx, x, y, u, scores, gap, align) {
  ctx.textAlign = 'left';
  let cx = x;
  scores.forEach((s) => {
    const str = String(s);
    ctx.fillText(str, cx, y);
    cx += ctx.measureText(str).width + gap;
  });
}

function stat(ctx, x, y, u, label, value, c, fMono, fUI) {
  ctx.textAlign = 'left';
  ctx.fillStyle = c.faint;
  ctx.font = `500 ${18 * u}px ${fMono}`;
  ctx.fillText(label, x, y - 40 * u);
  ctx.fillStyle = c.text;
  ctx.font = `700 ${46 * u}px ${fUI}`;
  ctx.fillText(value, x, y);
}

// Split "A / B" or "A · B" into two lines; else single line.
function splitPair(name) {
  if (!name) return [''];
  const m = name.split(/\s*[/·]\s*/);
  return m.length >= 2 ? [m[0], m.slice(1).join(' · ')] : [name];
}

/* ---- Theme 3: HERO (scoreline is the image) ---- */
function drawHero({ ctx, W, H, u, pad, c, fUI, fMono, d }) {
  const winA = d.winner === 'a';

  ctx.textAlign = 'left';
  ctx.fillStyle = c.muted;
  ctx.font = `500 ${20 * u}px ${fMono}`;
  const head = [d.tournament, [d.category, d.context].filter(Boolean).join(' · ')].filter(Boolean);
  ctx.fillText((head[0] || '').toUpperCase(), pad, 100 * u);
  if (head[1]) ctx.fillText(truncate(head[1], 40).toUpperCase(), pad, 134 * u);

  // Giant scoreline (each set as "a–b" stacked).
  const cy = H * 0.34;
  ctx.fillStyle = c.text;
  ctx.font = `800 ${180 * u}px ${fUI}`;
  const lines = (d.sets || []).map(([a, b]) => `${a}–${b}`);
  lines.slice(0, 3).forEach((ln, i) => {
    ctx.fillText(ln, pad, cy + i * 170 * u);
  });

  const afterY = cy + Math.min(lines.length, 3) * 170 * u - 40 * u;
  line(ctx, pad, afterY, W - pad, afterY, c.line);

  // Pair rows with winner dot.
  ctx.font = `700 ${46 * u}px ${fUI}`;
  const rowY = afterY + 70 * u;
  dot(ctx, pad + 8 * u, rowY - 14 * u, 8 * u, c.text, true);
  ctx.fillStyle = c.text;
  ctx.fillText(truncate(pairName(d, winA ? 'a' : 'b'), 34), pad + 36 * u, rowY);

  dot(ctx, pad + 8 * u, rowY + 60 * u - 14 * u, 8 * u, c.loser, false);
  ctx.fillStyle = c.loser;
  ctx.font = `500 ${46 * u}px ${fUI}`;
  ctx.fillText(truncate(pairName(d, winA ? 'b' : 'a'), 34), pad + 36 * u, rowY + 60 * u);

  // Footer.
  ctx.textAlign = 'left';
  ctx.fillStyle = c.muted;
  ctx.font = `500 ${19 * u}px ${fMono}`;
  ctx.fillText('PADELCUP', pad, H - 70 * u);
}

/* ---- primitive helpers ---- */
function line(ctx, x1, y1, x2, y2, color) {
  ctx.strokeStyle = color; ctx.lineWidth = Math.max(1, (x2 - x1) * 0 + 1);
  ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
}
function dot(ctx, x, y, r, color, filled) {
  ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2);
  if (filled) { ctx.fillStyle = color; ctx.fill(); }
  else { ctx.strokeStyle = color; ctx.lineWidth = 1.5; ctx.stroke(); }
}
function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}
function wrapText(ctx, text, x, y, maxW, lh) {
  const words = (text || '').split(' ');
  let lineStr = '', yy = y;
  words.forEach((w) => {
    const test = lineStr ? lineStr + ' ' + w : w;
    if (ctx.measureText(test).width > maxW && lineStr) {
      ctx.fillText(lineStr, x, yy); lineStr = w; yy += lh;
    } else { lineStr = test; }
  });
  if (lineStr) ctx.fillText(lineStr, x, yy);
}
function truncate(s, n) {
  s = s || '';
  return s.length > n ? s.slice(0, n - 1) + '\u2026' : s;
}
function hexToRgb(hex) {
  const m = (hex || '#000000').replace('#', '');
  const v = m.length === 3 ? m.split('').map((c) => c + c).join('') : m;
  const n = parseInt(v, 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}
function slug(s) {
  return (s || '').toString().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

// Short caption included with the shared file (some apps use it as the message).
function shareCaption(d) {
  const winner = d.winner === 'b' ? d.pairB : d.pairA;
  const parts = [d.tournament, d.category].filter(Boolean).join(' · ');
  return [parts, winner ? `🏆 ${winner}` : '', 'PadelCup'].filter(Boolean).join('\n');
}