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
        panel: '#0C0C0E',
        panelOpacity: 0.55,
        defaultInk: 'white',
        accent: null, // no colored accent (winner dot uses ink)
    },
    paper: {
        label: 'Paper',
        panel: '#0C0C0E',
        panelOpacity: 0.55,
        defaultInk: 'white',
        accent: '#3da26e', // green — stays green regardless of ink switch
    },
    hero: {
        label: 'Hero',
        panel: '#101014',
        panelOpacity: 0.55,
        defaultInk: 'white',
        accent: null,
    },
};

// Build the full color set for a theme given the chosen ink (black|white).
function inkColors(ink) {
    const base = ink === 'black' ? '20,20,15' : '255,255,255';
    return {
        text: `rgba(${base},1)`,
        muted: `rgba(${base},0.55)`,
        faint: `rgba(${base},0.42)`,
        line: `rgba(${base},0.14)`,
        loser: `rgba(${base},0.42)`,
        box: `rgba(${base},0.12)`, // empty score-box fill
    };
}

/* ----------------------------------------------------------------------------
   Voleo wordmark, drawn onto the canvas as the footer brand (replaces the old
   fillText('VOLEO')). The SVG is recolored per ink (black / white) and cached
   as a decoded <img>; the trailing dot stays lime in both. Image decode is
   async, so a module-level re-render hook lets the preview refresh once a logo
   finishes loading. viewBox 1365x398 → aspect ≈ 3.429.
---------------------------------------------------------------------------- */
const VOLEO_LOGO_ASPECT = 1365 / 398;
const VOLEO_DOT = '#d9f27a';

// Optional callback the drawer sets so a late-decoded logo can refresh preview.
let _voleoReRender = null;
function setVoleoReRender(fn) { _voleoReRender = fn; }

function voleoSvg(inkColor) {
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1365 398" style="fill-rule:evenodd;clip-rule:evenodd;">`
        + `<g transform="matrix(1,0,0,1,-1478.153171,-1510.333408)"><g transform="matrix(1.40332,0,0,1.40332,1446,1481)">`
        + `<path d="M932.92,263.522C937.839,225.414 978.945,229.053 990.232,248.648C1010.371,283.608 963.56,313.382 939.901,285.159C933.084,277.027 932.942,265.34 932.92,263.522Z" fill="${VOLEO_DOT}"/>`
        + `<path d="M910.04,192.484C899.785,326.489 696.149,338.604 707.978,200.558C712.527,147.474 769.302,87.302 845.522,103.398C877.494,110.149 904.046,135.984 908.765,168.458C910.504,180.43 910.275,180.404 910.04,192.484ZM797.534,253.024C865.713,257.64 883.985,150.063 817.5,145.495C806.094,144.712 778.995,150.12 765.274,179.397C751.417,208.964 760.652,247.331 797.534,253.024Z" fill="${inkColor}"/>`
        + `<g transform="matrix(0.712596,0,0,0.712596,0,0)"><path d="M980.872,244.861C980.905,249.393 981.128,279.392 973.16,300.994C972.004,304.129 970.671,303.703 890.413,304.361C781.686,305.252 781.178,305.368 780.706,306.477C777.977,312.887 791.695,379.628 865.017,359.533C869.433,358.322 909.695,332.206 948.351,357.95C954.96,362.351 950.743,364.047 945.414,370.02C941.506,374.402 895.039,434.643 806.321,419.599C696.104,400.909 676.911,249.791 774.966,175.604C846.303,121.631 969.075,133.446 980.872,244.861ZM907.243,256.855C913.86,256.714 913.819,256.742 914.389,256.702C920.658,256.264 915.879,208.77 881.845,200.567C815.877,184.668 784.85,253.457 786.989,257.352C788.43,259.977 876.845,256.62 907.243,256.855Z" fill="${inkColor}"/></g>`
        + `<path d="M425.5,298.038C421.765,297.707 419.55,299.221 420.169,295.447C420.284,294.748 443.18,184.379 443.838,181.584C452.276,145.768 450.342,145.42 458.822,109.584C460.848,101.024 464.247,78.032 466.517,77.576C478.385,75.197 510.589,92.436 503.287,124.442C495.352,159.228 474.484,263.323 472.06,275.416C467.792,296.706 467.306,297.152 465.485,297.35C463.693,297.545 429.17,297.962 425.5,298.038Z" fill="${inkColor}"/>`
        + `<g transform="matrix(0.712596,0,0,0.712596,0,0)"><path d="M466.614,148.831C634.657,156.179 620.7,392.869 452.467,423.964C360.266,441.005 282.072,374.237 304.48,273.081C319.307,206.144 381.669,146.556 466.614,148.831ZM446.99,211.834C362.313,221.031 342.156,351.596 431.505,362.886C470.982,367.874 528.392,318.774 509.868,251.963C504.262,231.743 484.327,211.526 453.966,211.609C451.639,211.616 449.316,211.828 446.99,211.834Z" fill="${inkColor}"/></g>`
        + `<g transform="matrix(0.712596,0,0,0.712596,0,0)"><path d="M204.822,101.756C213.777,23.692 298.76,13.785 322.59,48.059C342.872,77.231 326.283,96.73 338.942,95.015C449.786,80.005 533.854,29.531 538.397,30.969C541.508,31.953 547.909,59.975 527.541,86.756C495.306,129.139 372.416,140.564 331.831,146.33C324.063,147.433 325.939,150.417 313.108,174.429C308.862,182.376 192.857,419.103 190.058,421.589C188.172,423.263 101.285,423.239 100.607,422.702C97.449,420.2 61.873,237.427 49.405,207.159C36.409,175.61 32.09,177.615 32.154,174.674C32.272,169.178 74.848,162.046 99.868,180.951C147.475,216.924 136.83,337.954 148.196,362.685C153.071,373.291 161.435,351.583 173.116,327.579C259.229,150.604 262.634,148.07 258.731,147.244C233.049,141.812 207.37,143.214 204.822,101.756ZM261.751,112.171C298.597,107.184 299.702,69.953 282.82,65.111C252.667,56.464 221.138,110.547 261.751,112.171Z" fill="${inkColor}"/></g>`
        + `</g></g></svg>`;
}

const _voleoCache = {}; // ink -> { img, ready }
function getVoleoLogo(ink) {
    if (_voleoCache[ink]) return _voleoCache[ink].ready ? _voleoCache[ink].img : null;
    const inkColor = ink === 'black' ? '#14140f' : '#ffffff';
    const img = new Image();
    const entry = { img, ready: false };
    _voleoCache[ink] = entry;
    img.onload = () => { entry.ready = true; if (_voleoReRender) _voleoReRender(); };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(voleoSvg(inkColor));
    return null;
}
// Warm both variants up front so they're ready by export time.
function preloadVoleoLogos() { getVoleoLogo('black'); getVoleoLogo('white'); }

// Resolves once the given ink's logo has decoded (or immediately if ready).
function whenVoleoReady(ink) {
    return new Promise((resolve) => {
        if (getVoleoLogo(ink)) return resolve();     // already decoded
        const entry = _voleoCache[ink];
        const prev = entry.img.onload;
        entry.img.onload = () => { if (prev) prev(); resolve(); };
        // Safety timeout so export never hangs if decode fails.
        setTimeout(resolve, 1500);
    });
}

/* Draw the Voleo wordmark where the old fillText('VOLEO') sat.
   align: 'left' | 'right'. (x, yBaseline) = old text anchor. sizePx ≈ old font
   size. Falls back to the plain text (in fallbackColor / fMono) until decoded. */
function drawVoleoBrand(ctx, ink, x, yBaseline, sizePx, align, fallbackColor, fMono) {
    const img = getVoleoLogo(ink);
    if (img) {
        const h = sizePx * 1.25;                 // logo height ≈ text size, slight bump
        const w = h * VOLEO_LOGO_ASPECT;
        const top = yBaseline - h * 0.82;        // baseline → top of glyph box
        const left = align === 'right' ? x - w : x;
        ctx.drawImage(img, left, top, w, h);
    } else {
        ctx.save();
        ctx.textAlign = align;
        ctx.fillStyle = fallbackColor;
        ctx.font = `500 ${sizePx}px ${fMono}`;
        ctx.fillText('VOLEO', x, yBaseline);
        ctx.restore();
    }
}

/* ============================================================================
   DRAWER
   ========================================================================== */
function createDrawer() {
    preloadVoleoLogos(); // warm both ink variants of the Voleo wordmark
    // State for the current session.
    const state = {
        data: null,
        img: null, // HTMLImageElement of the uploaded photo
        theme: 'ledger',
        ink: THEMES.ledger.defaultInk, // 'black' | 'white' — flips ALL text
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
        try {
            return navigator.canShare({
                files: [new File([new Blob()], 'x.png', {
                    type: 'image/png'
                })]
            });
        } catch (e) {
            return false;
        }
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
    emptyEl.addEventListener('dragover', (e) => {
        e.preventDefault();
        emptyEl.classList.add('is-drag');
    });
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
        img.onerror = () => {
            URL.revokeObjectURL(url);
        };
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
        setTimeout(() => {
            if (!root.classList.contains('is-open')) root.style.display = 'none';
        }, 300);
    }

    // --- Render (preview) ---
    function render() {
        if (!state.img || !state.data) return;
        // Let a late-decoded Voleo logo refresh this preview.
        setVoleoReRender(render);
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
            // Ensure the Voleo logo for the current ink is decoded first, so the
            // exported PNG shows the wordmark (not the text fallback).
            whenVoleoReady(state.ink).then(() => {
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
        const file = new File([blob], filename(), {
            type: 'image/png'
        });

        if (navigator.canShare && navigator.canShare({
                files: [file]
            })) {
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

    return {
        open,
        close
    };
}

/* ============================================================================
   COMPOSITOR — draws photo + themed scoreboard overlay onto a canvas.
   Auto-sizes to the uploaded image. Layout is proportional to image height so
   it scales to any portrait ratio (1080×1350, 1080×1920, etc.).
   ========================================================================== */
function drawComposite(canvas, img, d, opts) {
    const theme = THEMES[opts.theme] || THEMES.ledger;
    const ink = opts.ink || theme.defaultInk;
    const c = inkColors(ink); // text colors derived from ink
    const accent = theme.accent; // fixed accent (Paper green), or null

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

    const fUI = getComputedStyle(document.documentElement).getPropertyValue('--font-ui').trim() ||
        "'Inter', sans-serif";
    const fMono = getComputedStyle(document.documentElement).getPropertyValue('--font-mono').trim() ||
        "'JetBrains Mono', monospace";

    // Route to the theme layout.
    const layout = {
        ctx,
        W,
        H,
        u,
        pad,
        c,
        accent,
        fUI,
        fMono,
        d,
        ink: opts.ink,
    };
    if (opts.theme === 'paper') drawPaper(layout);
    else if (opts.theme === 'hero') drawHero(layout);
    else drawLedger(layout);
}

/* ---- Shared helpers ---- */
function setsAndGames(d) {
    let aSets = 0,
        bSets = 0,
        aGames = 0,
        bGames = 0;
    (d.sets || []).forEach(([a, b]) => {
        a = +a || 0;
        b = +b || 0;
        aGames += a;
        bGames += b;
        if (a > b) aSets++;
        else if (b > a) bSets++;
    });
    return {
        aSets,
        bSets,
        aGames,
        bGames
    };
}

function pairName(d, side) {
    const raw = side === 'a' ? d.pairA : d.pairB;
    return raw || '';
}

/* ---- Theme 1: LEDGER (dark, stat strip) — matches reference HTML spacing ---- */
function drawLedger({
    ctx,
    W,
    H,
    u,
    pad,
    c,
    fUI,
    fMono,
    d,
    ink
}) {
    const winA = d.winner === 'a',
        winB = d.winner === 'b';
    const {
        aSets,
        bSets,
        aGames,
        bGames
    } = setsAndGames(d);

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
    const rowH = 210 * u; // generous row height (was cramped before)
    const block1Top = H * 0.30; // start of first row
    const rowMidGap = rowH; // second row sits a full rowH below

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
    drawVoleoBrand(ctx, ink, pad, H - 56 * u, 19 * u, 'left', c.muted, fMono);
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
    const size = 100 * u,
        gap = 30 * u;
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
function drawPaper({
    ctx,
    W,
    H,
    u,
    pad,
    c,
    accent,
    fUI,
    fMono,
    d,
    ink
}) {
    const {
        aSets,
        bSets,
        aGames,
        bGames
    } = setsAndGames(d);
    const green = accent || c.text;

    // Header (two mono lines) + accent dot.
    ctx.textAlign = 'left';
    ctx.fillStyle = c.muted;
    ctx.font = `500 ${20 * u}px ${fMono}`;
    ctx.fillText((d.tournament || '').toUpperCase(), pad, 96 * u);
    const ctxLine = [d.category, d.context].filter(Boolean).join(' · ').toUpperCase();
    ctx.fillText(truncate(ctxLine, 40), pad, 130 * u);

    ctx.fillStyle = green;
    ctx.beginPath();
    ctx.arc(W - pad - 11 * u, 92 * u, 11 * u, 0, Math.PI * 2);
    ctx.fill();

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

    drawVoleoBrand(ctx, ink, W - pad, H - 78 * u, 19 * u, 'right', c.muted, fMono);
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
function drawHero({
    ctx,
    W,
    H,
    u,
    pad,
    c,
    fUI,
    fMono,
    d,
    ink
}) {
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
    drawVoleoBrand(ctx, ink, pad, H - 70 * u, 19 * u, 'left', c.muted, fMono);
}

/* ---- primitive helpers ---- */
function line(ctx, x1, y1, x2, y2, color) {
    ctx.strokeStyle = color;
    ctx.lineWidth = Math.max(1, (x2 - x1) * 0 + 1);
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

function dot(ctx, x, y, r, color, filled) {
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    if (filled) {
        ctx.fillStyle = color;
        ctx.fill();
    } else {
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }
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
    let lineStr = '',
        yy = y;
    words.forEach((w) => {
        const test = lineStr ? lineStr + ' ' + w : w;
        if (ctx.measureText(test).width > maxW && lineStr) {
            ctx.fillText(lineStr, x, yy);
            lineStr = w;
            yy += lh;
        } else {
            lineStr = test;
        }
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
    return [parts, winner ? `🏆 ${winner}` : '', 'Voleo'].filter(Boolean).join('\n');
}