// Match result share studio.
//
// Clicking a [data-share-match] button opens a bottom-to-top drawer where the
// user: uploads a background photo, picks a THEME (5 now), tunes a background
// PANEL color + opacity, and sets TEXT colors.
//
// Color model (v2): three independent channels — TEXT, SUBTLE, ACCENT — are
// ALWAYS what drives the artwork. The "Negro / Blanco" buttons are quick presets
// that set text+subtle; an "Colores avanzados" accordion exposes the three color
// pickers directly. faint/line/loser/box are derived as opacity steps of TEXT.
//
// Library-free: pure canvas. Portrait layout scales proportionally to the photo.
//
// Data contract: d = { tournament, category, context, pairA, pairB,
//                       sets:[[a,b],...], winner:'a'|'b' }
// NOTE: there is NO duration/minutes in the data — templates that reference a
// "MIN" slot in their inspiration fill it with SETS instead.

export function initMatchShare() {
    let drawer = null;

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
   THEMES — each seeds panel color/opacity AND the three color channels
   (defaultColors). Choosing a theme resets those; the user can then tweak any
   channel via the Negro/Blanco presets or the advanced pickers.
   ========================================================================== */
const THEMES = {
    ledger: {
        label: 'Ledger',
        panel: '#0C0C0E',
        panelOpacity: 0.55,
        defaultColors: { text: '#ffffff', subtle: '#c9c9c9', accent: '#d9f27a' },
    },
    paper: {
        label: 'Paper',
        panel: '#0C0C0E',
        panelOpacity: 0.55,
        defaultColors: { text: '#ffffff', subtle: '#c9c9c9', accent: '#3da26e' },
    },
    hero: {
        label: 'Hero',
        panel: '#101014',
        panelOpacity: 0.55,
        defaultColors: { text: '#ffffff', subtle: '#c9c9c9', accent: '#d9f27a' },
    },
    // marcador: {
    //     label: 'Marcador',
    //     panel: '#0C0C0E',
    //     panelOpacity: 0.22,
    //     defaultColors: { text: '#f0dcae', subtle: '#c9b48a', accent: '#f0dcae' },
    // },
    // duelo: {
    //     label: 'Duelo',
    //     panel: '#0C0C0E',
    //     panelOpacity: 0.22,
    //     defaultColors: { text: '#f0dcae', subtle: '#c9b48a', accent: '#f0dcae' },
    // },
};

// Quick presets for the Negro / Blanco buttons — they set text + subtle only,
// leaving the accent channel (and its picker) untouched.
const INK_PRESETS = {
    black: { text: '#14140f', subtle: '#5f5f55' },
    white: { text: '#ffffff', subtle: '#cfcfcf' },
};

// Build the full color set the templates consume from the 3 user channels.
// text/subtle/accent are explicit; faint/line/loser/box derive from TEXT so the
// winner/loser contrast holds regardless of the subtle choice.
function buildColors(colors) {
    const t = colors.text || '#ffffff';
    const [r, g, b] = hexToRgb(t);
    const base = `${r},${g},${b}`;
    return {
        text: t,
        muted: colors.subtle || `rgba(${base},0.55)`,
        faint: `rgba(${base},0.42)`,
        line: `rgba(${base},0.14)`,
        loser: `rgba(${base},0.45)`,
        box: `rgba(${base},0.12)`,
        accent: colors.accent || t,
    };
}

/* ----------------------------------------------------------------------------
   Voleo wordmark, drawn onto the canvas as the footer brand. Recolored to the
   current TEXT color and cached by that color string; the trailing dot stays
   lime. Async decode → a re-render hook refreshes the preview once ready.
---------------------------------------------------------------------------- */
const VOLEO_LOGO_ASPECT = 1365 / 398;
const VOLEO_DOT = '#d9f27a';

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

const _voleoCache = {}; // colorString -> { img, ready }
function getVoleoLogo(color) {
    if (_voleoCache[color]) return _voleoCache[color].ready ? _voleoCache[color].img : null;
    const img = new Image();
    const entry = { img, ready: false };
    _voleoCache[color] = entry;
    img.onload = () => { entry.ready = true; if (_voleoReRender) _voleoReRender(); };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(voleoSvg(color));
    return null;
}
function preloadVoleoLogos() { getVoleoLogo('#14140f'); getVoleoLogo('#ffffff'); }

function whenVoleoReady(color) {
    return new Promise((resolve) => {
        if (getVoleoLogo(color)) return resolve();
        const entry = _voleoCache[color];
        const prev = entry.img.onload;
        entry.img.onload = () => { if (prev) prev(); resolve(); };
        setTimeout(resolve, 1500);
    });
}

/* Draw the Voleo wordmark. align: 'left' | 'right' | 'center'. */
function drawVoleoBrand(ctx, color, x, yBaseline, sizePx, align, fallbackColor, fMono) {
    const img = getVoleoLogo(color);
    if (img) {
        const h = sizePx * 1.25;
        const w = h * VOLEO_LOGO_ASPECT;
        const top = yBaseline - h * 0.82;
        let left = x;
        if (align === 'right') left = x - w;
        else if (align === 'center') left = x - w / 2;
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
    preloadVoleoLogos();
    const state = {
        data: null,
        img: null,
        theme: 'ledger',
        colors: { ...THEMES.ledger.defaultColors }, // {text, subtle, accent}
        panelColor: THEMES.ledger.panel,
        panelOpacity: THEMES.ledger.panelOpacity,
    };

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
              <input type="range" class="ms-range" data-ms-opacity min="0" max="100" value="55">
              <span class="ms-range__val" data-ms-opacity-val>55%</span>
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

            <button type="button" class="ms-accordion__toggle" data-ms-adv-toggle aria-expanded="false">
              <i class="fas fa-sliders"></i> Colores avanzados
              <i class="fas fa-chevron-down ms-accordion__chev"></i>
            </button>
            <div class="ms-accordion__panel" data-ms-adv hidden>
              <div class="ms-adv-row">
                <span class="ms-adv-row__label">Texto</span>
                <input type="color" class="ms-color ms-color--sm" data-ms-c-text>
              </div>
              <div class="ms-adv-row">
                <span class="ms-adv-row__label">Sutil</span>
                <input type="color" class="ms-color ms-color--sm" data-ms-c-subtle>
              </div>
              <div class="ms-adv-row">
                <span class="ms-adv-row__label">Acento</span>
                <input type="color" class="ms-color ms-color--sm" data-ms-c-accent>
              </div>
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

    const $ = (sel) => root.querySelector(sel);
    const canvas = $('[data-ms-canvas]');
    const emptyEl = $('[data-ms-drop]');
    const fileInput = $('[data-ms-file]');
    const themesEl = $('[data-ms-themes]');
    const colorInput = $('[data-ms-color]');
    const opacityInput = $('[data-ms-opacity]');
    const opacityVal = $('[data-ms-opacity-val]');
    const inkEl = $('[data-ms-ink]');
    const advToggle = $('[data-ms-adv-toggle]');
    const advPanel = $('[data-ms-adv]');
    const cText = $('[data-ms-c-text]');
    const cSubtle = $('[data-ms-c-subtle]');
    const cAccent = $('[data-ms-c-accent]');
    const exportBtn = $('[data-ms-export]');
    const rephotoBtn = $('[data-ms-rephoto]');
    const shareBtn = $('[data-ms-share]');

    const canShareFiles = !!(navigator.canShare && (() => {
        try {
            return navigator.canShare({ files: [new File([new Blob()], 'x.png', { type: 'image/png' })] });
        } catch (e) { return false; }
    })());
    if (canShareFiles) {
        shareBtn.hidden = false;
        exportBtn.classList.remove('ms-btn--primary');
    } else {
        exportBtn.classList.add('ms-btn--primary');
        exportBtn.classList.remove('ms-btn--ghost');
    }

    // Reflect current colors into the pickers + preset button highlight.
    function syncColorUI() {
        cText.value = toHexInput(state.colors.text);
        cSubtle.value = toHexInput(state.colors.subtle);
        cAccent.value = toHexInput(state.colors.accent);
        const t = state.colors.text.toLowerCase();
        inkEl.querySelectorAll('[data-ms-ink-opt]').forEach((el) => {
            const preset = INK_PRESETS[el.dataset.msInkOpt];
            el.classList.toggle('is-active', preset && preset.text.toLowerCase() === t);
        });
    }

    // Theme thumbnails (swatch shows the theme's accent — the artwork's key hue).
    Object.entries(THEMES).forEach(([key, t]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'ms-theme' + (key === state.theme ? ' is-active' : '');
        b.dataset.msTheme = key;
        b.innerHTML = `<span class="ms-theme__sw" style="background:${t.defaultColors.accent}"></span><span class="ms-theme__name">${t.label}</span>`;
        themesEl.appendChild(b);
    });

    syncColorUI();

    // --- Events ---
    root.querySelectorAll('[data-ms-close]').forEach((el) => el.addEventListener('click', close));

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files && e.target.files[0];
        if (file) loadPhoto(file);
    });

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
        state.panelColor = t.panel;
        state.panelOpacity = t.panelOpacity;
        state.colors = { ...t.defaultColors };
        colorInput.value = t.panel;
        opacityInput.value = Math.round(t.panelOpacity * 100);
        opacityVal.textContent = Math.round(t.panelOpacity * 100) + '%';
        syncColorUI();
        themesEl.querySelectorAll('[data-ms-theme]').forEach((el) => el.classList.toggle('is-active', el === b));
        render();
    });

    // Negro / Blanco presets set text + subtle (accent untouched).
    inkEl.addEventListener('click', (e) => {
        const b = e.target.closest('[data-ms-ink-opt]');
        if (!b) return;
        const preset = INK_PRESETS[b.dataset.msInkOpt];
        state.colors.text = preset.text;
        state.colors.subtle = preset.subtle;
        syncColorUI();
        render();
    });

    // Advanced accordion.
    advToggle.addEventListener('click', () => {
        const open = advPanel.hidden;
        advPanel.hidden = !open;
        advToggle.setAttribute('aria-expanded', String(open));
        advToggle.classList.toggle('is-open', open);
    });
    cText.addEventListener('input', () => { state.colors.text = cText.value; syncColorUI(); render(); });
    cSubtle.addEventListener('input', () => { state.colors.subtle = cSubtle.value; syncColorUI(); render(); });
    cAccent.addEventListener('input', () => { state.colors.accent = cAccent.value; render(); });

    colorInput.addEventListener('input', () => { state.panelColor = colorInput.value; render(); });
    opacityInput.addEventListener('input', () => {
        state.panelOpacity = opacityInput.value / 100;
        opacityVal.textContent = opacityInput.value + '%';
        render();
    });

    exportBtn.addEventListener('click', exportPng);
    shareBtn.addEventListener('click', sharePng);

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
        img.onerror = () => URL.revokeObjectURL(url);
        img.src = url;
    }

    function open(data) {
        state.data = data;
        state.img = null;
        emptyEl.style.display = '';
        canvas.style.display = 'none';
        exportBtn.disabled = true;
        rephotoBtn.disabled = true;
        if (shareBtn) shareBtn.disabled = true;
        fileInput.value = '';

        document.body.classList.add('pc-drawer-open');
        const sbw = window.innerWidth - document.documentElement.clientWidth;
        if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
        document.body.style.overflow = 'hidden';

        root.style.display = 'block';
        requestAnimationFrame(() => { requestAnimationFrame(() => root.classList.add('is-open')); });
    }

    function close() {
        root.classList.remove('is-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        document.body.classList.remove('pc-drawer-open');
        setTimeout(() => { if (!root.classList.contains('is-open')) root.style.display = 'none'; }, 300);
    }

    function render() {
        if (!state.img || !state.data) return;
        setVoleoReRender(render);
        drawComposite(canvas, state.img, state.data, {
            theme: state.theme,
            colors: state.colors,
            panelColor: state.panelColor,
            panelOpacity: state.panelOpacity,
            preview: true,
        });
    }

    function makeBlob() {
        return new Promise((resolve) => {
            if (!state.img || !state.data) return resolve(null);
            whenVoleoReady(state.colors.text).then(() => {
                const out = document.createElement('canvas');
                drawComposite(out, state.img, state.data, {
                    theme: state.theme,
                    colors: state.colors,
                    panelColor: state.panelColor,
                    panelOpacity: state.panelOpacity,
                    preview: false,
                });
                out.toBlob((blob) => resolve(blob), 'image/png');
            });
        });
    }

    function filename() { return slug(state.data.category || 'partido') + '-resultado.png'; }

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

    async function sharePng() {
        const blob = await makeBlob();
        if (!blob) return;
        const file = new File([blob], filename(), { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            try {
                await navigator.share({ files: [file], title: state.data.category || 'Resultado', text: shareCaption(state.data) });
            } catch (e) { /* cancelled */ }
        } else {
            await exportPng();
        }
    }

    return { open, close };
}

/* ============================================================================
   COMPOSITOR
   ========================================================================== */
function drawComposite(canvas, img, d, opts) {
    const c = buildColors(opts.colors);
    const accent = c.accent;
    const logoColor = opts.colors.text;

    const maxW = opts.preview ? 720 : img.naturalWidth;
    const scaleToOut = maxW / img.naturalWidth;
    const W = Math.round(img.naturalWidth * scaleToOut);
    const H = Math.round(img.naturalHeight * scaleToOut);

    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');

    ctx.clearRect(0, 0, W, H);
    ctx.drawImage(img, 0, 0, W, H);

    const [pr, pg, pb] = hexToRgb(opts.panelColor);
    ctx.fillStyle = `rgba(${pr},${pg},${pb},${opts.panelOpacity})`;
    ctx.fillRect(0, 0, W, H);

    const u = W / 1080;
    const pad = 80 * u;
    const fUI = getComputedStyle(document.documentElement).getPropertyValue('--font-ui').trim() || "'Inter', sans-serif";
    const fMono = getComputedStyle(document.documentElement).getPropertyValue('--font-mono').trim() || "'JetBrains Mono', monospace";

    const layout = { ctx, W, H, u, pad, c, accent, fUI, fMono, d, logoColor };
    if (opts.theme === 'paper') drawPaper(layout);
    else if (opts.theme === 'hero') drawHero(layout);
    else if (opts.theme === 'marcador') drawMarcador(layout);
    else if (opts.theme === 'duelo') drawDuelo(layout);
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

function pairName(d, side) { return (side === 'a' ? d.pairA : d.pairB) || ''; }

/* ---- Theme 1: LEDGER ---- */
function drawLedger({ ctx, W, H, u, pad, c, fUI, fMono, d, logoColor }) {
    const winA = d.winner === 'a', winB = d.winner === 'b';
    const { aSets, bSets, aGames, bGames } = setsAndGames(d);

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

    const rowH = 210 * u;
    const block1Top = H * 0.30;
    const rowMidGap = rowH;

    drawLedgerRow(ctx, W, pad, u, block1Top, pairName(d, 'a'), d.sets.map((s) => s[0]), winA, c, fUI);
    const divY = block1Top + rowH - 78 * u;
    line(ctx, pad, divY, W - pad, divY, c.line);
    drawLedgerRow(ctx, W, pad, u, block1Top + rowMidGap, pairName(d, 'b'), d.sets.map((s) => s[1]), winB, c, fUI);

    const stripY = H - 200 * u;
    line(ctx, pad, stripY, W - pad, stripY, c.line);
    const colW = (W - pad * 2) / 2;
    stat(ctx, pad, stripY + 62 * u, u, 'SETS', `${aSets} – ${bSets}`, c, fMono, fUI);
    stat(ctx, pad + colW, stripY + 62 * u, u, 'GAMES', `${aGames} – ${bGames}`, c, fMono, fUI);

    drawVoleoBrand(ctx, logoColor, pad, H - 56 * u, 19 * u, 'left', c.muted, fMono);
}

function drawLedgerRow(ctx, W, pad, u, top, name, scores, isWin, c, fUI) {
    const nameColor = isWin ? c.text : c.loser;
    const scoreColor = isWin ? c.text : c.loser;
    const parts = splitPair(name);
    const nameSize = 54 * u;
    const nameLH = 62 * u;

    let nameX = pad;
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
    parts.forEach((ln, i) => ctx.fillText(truncate(ln, 22), nameX, top + i * nameLH));

    const scoreY = top + (parts.length > 1 ? nameLH : 0) - 6 * u;
    ctx.textAlign = 'center';
    const size = 100 * u, gap = 30 * u;
    const total = scores.length * size + (scores.length - 1) * gap;
    let x = W - pad - total + size / 2;
    ctx.fillStyle = scoreColor;
    ctx.font = `${isWin ? 800 : 600} ${104 * u}px ${fUI}`;
    scores.forEach((s) => { ctx.fillText(String(s), x, scoreY); x += size + gap; });
}

/* ---- Theme 2: PAPER ---- */
function drawPaper({ ctx, W, H, u, pad, c, accent, fUI, fMono, d, logoColor }) {
    const { aSets, bSets, aGames, bGames } = setsAndGames(d);
    const green = accent || c.text;

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

    const top = H * 0.34;
    ctx.fillStyle = green;
    ctx.font = `500 ${18 * u}px ${fMono}`;
    ctx.fillText('GANADORES', pad, top);

    const winnerName = d.winner === 'b' ? pairName(d, 'b') : pairName(d, 'a');
    const loserName = d.winner === 'b' ? pairName(d, 'a') : pairName(d, 'b');
    const winScores = d.winner === 'b' ? d.sets.map((s) => s[1]) : d.sets.map((s) => s[0]);
    const loseScores = d.winner === 'b' ? d.sets.map((s) => s[0]) : d.sets.map((s) => s[1]);

    ctx.fillStyle = c.text;
    ctx.font = `700 ${78 * u}px ${fUI}`;
    const wParts = splitPair(winnerName);
    const nLH = 82 * u;
    wParts.forEach((ln, i) => ctx.fillText(truncate(ln, 20), pad, top + 76 * u + i * nLH));

    const scoreY = top + 76 * u + wParts.length * nLH + 96 * u;
    ctx.fillStyle = green;
    ctx.font = `800 ${132 * u}px ${fUI}`;
    drawScoreDigits(ctx, pad, scoreY, u, winScores, 40 * u, 'left');

    const divY = scoreY + 56 * u;
    line(ctx, pad, divY, W - pad, divY, c.line);

    ctx.fillStyle = c.loser;
    ctx.font = `500 ${44 * u}px ${fUI}`;
    ctx.fillText(truncate(loserName, 30), pad, divY + 78 * u);
    ctx.font = `600 ${64 * u}px ${fUI}`;
    drawScoreDigits(ctx, pad, divY + 156 * u, u, loseScores, 30 * u, 'left');

    ctx.fillStyle = c.faint;
    ctx.font = `500 ${17 * u}px ${fMono}`;
    ctx.fillText('SETS', pad, H - 118 * u);
    ctx.fillText('GAMES', pad + 240 * u, H - 118 * u);
    ctx.fillStyle = c.text;
    ctx.font = `700 ${34 * u}px ${fUI}`;
    ctx.fillText(`${aSets} – ${bSets}`, pad, H - 78 * u);
    ctx.fillText(`${aGames} – ${bGames}`, pad + 240 * u, H - 78 * u);

    drawVoleoBrand(ctx, logoColor, W - pad, H - 78 * u, 19 * u, 'right', c.muted, fMono);
    ctx.textAlign = 'left';
}

function drawScoreDigits(ctx, x, y, u, scores, gap, align) {
    ctx.textAlign = 'left';
    let cx = x;
    scores.forEach((s) => { const str = String(s); ctx.fillText(str, cx, y); cx += ctx.measureText(str).width + gap; });
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

function splitPair(name) {
    if (!name) return [''];
    const m = name.split(/\s*[/·]\s*/);
    return m.length >= 2 ? [m[0], m.slice(1).join(' · ')] : [name];
}

/* ---- Theme 3: HERO ---- */
function drawHero({ ctx, W, H, u, pad, c, fUI, fMono, d, logoColor }) {
    const winA = d.winner === 'a';

    ctx.textAlign = 'left';
    ctx.fillStyle = c.muted;
    ctx.font = `500 ${20 * u}px ${fMono}`;
    const head = [d.tournament, [d.category, d.context].filter(Boolean).join(' · ')].filter(Boolean);
    ctx.fillText((head[0] || '').toUpperCase(), pad, 100 * u);
    if (head[1]) ctx.fillText(truncate(head[1], 40).toUpperCase(), pad, 134 * u);

    const cy = H * 0.34;
    ctx.fillStyle = c.text;
    ctx.font = `800 ${180 * u}px ${fUI}`;
    const lines = (d.sets || []).map(([a, b]) => `${a}–${b}`);
    lines.slice(0, 3).forEach((ln, i) => ctx.fillText(ln, pad, cy + i * 170 * u));

    const afterY = cy + Math.min(lines.length, 3) * 170 * u - 40 * u;
    line(ctx, pad, afterY, W - pad, afterY, c.line);

    ctx.font = `700 ${46 * u}px ${fUI}`;
    const rowY = afterY + 70 * u;
    dot(ctx, pad + 8 * u, rowY - 14 * u, 8 * u, c.text, true);
    ctx.fillStyle = c.text;
    ctx.fillText(truncate(pairName(d, winA ? 'a' : 'b'), 34), pad + 36 * u, rowY);

    dot(ctx, pad + 8 * u, rowY + 60 * u - 14 * u, 8 * u, c.loser, false);
    ctx.fillStyle = c.loser;
    ctx.font = `500 ${46 * u}px ${fUI}`;
    ctx.fillText(truncate(pairName(d, winA ? 'b' : 'a'), 34), pad + 36 * u, rowY + 60 * u);

    drawVoleoBrand(ctx, logoColor, pad, H - 70 * u, 19 * u, 'left', c.muted, fMono);
}

/* ---- Theme 4: MARCADOR (single-line score, center chip, stat wings) ----
   Inspiration slot "48 MIN" → filled with SETS (no duration in the data). */
function drawMarcador({ ctx, W, H, u, pad, c, accent, fUI, fMono, d, logoColor }) {
    const { aSets, bSets, aGames, bGames } = setsAndGames(d);

    // Top: tournament · category, centered mono muted.
    ctx.textAlign = 'center';
    ctx.fillStyle = c.muted;
    ctx.font = `500 ${18 * u}px ${fMono}`;
    ctx.fillText(truncate([d.tournament, d.category].filter(Boolean).join(' · ').toUpperCase(), 46), W / 2, 92 * u);

    // Big single-line score, auto-fit to width.
    const scoreStr = (d.sets || []).map(([a, b]) => `${a}–${b}`).join('   ');
    const px = fitFont(ctx, scoreStr, W - pad * 2, 210 * u, 800, fUI);
    const scoreY = H * 0.42;
    ctx.fillStyle = accent;
    ctx.textAlign = 'center';
    ctx.font = `800 ${px}px ${fUI}`;
    ctx.fillText(scoreStr, W / 2, scoreY);

    // Row: center chip (winner caption) + stat wings (GAMES left, SETS right).
    const rowY = scoreY + 96 * u;
    const chip = drawChip(ctx, W / 2, rowY, winnerCaption(d), 30 * u, fUI, accent, contrastColor(accent), 28 * u, 17 * u);
    const leftCX = (pad + chip.x) / 2;
    const rightCX = (chip.x + chip.w + (W - pad)) / 2;
    drawWingInline(ctx, leftCX, rowY, `${aGames}–${bGames}`, 'GAMES', u, fUI, fMono, c.muted, c.faint);
    drawWingInline(ctx, rightCX, rowY, `${aSets}–${bSets}`, 'SETS', u, fUI, fMono, c.muted, c.faint);

    drawVoleoBrand(ctx, logoColor, W / 2, H - 64 * u, 19 * u, 'center', c.muted, fMono);
    ctx.textAlign = 'left';
}

/* ---- Theme 5: DUELO (stacked score, names left/right, center chip) ----
   Inspiration slot "48 min · Grupo A" → "SETS · context" (no duration). */
function drawDuelo({ ctx, W, H, u, pad, c, accent, fUI, fMono, d, logoColor }) {
    const { aSets, bSets } = setsAndGames(d);
    const winA = d.winner !== 'b';

    // Top line.
    ctx.textAlign = 'center';
    ctx.fillStyle = c.muted;
    ctx.font = `500 ${18 * u}px ${fMono}`;
    ctx.fillText(truncate([d.tournament, d.category].filter(Boolean).join(' · ').toUpperCase(), 46), W / 2, 92 * u);

    // Stacked score, one set per line, centered.
    const lines = (d.sets || []).map(([a, b]) => `${a}–${b}`).slice(0, 3);
    const px = fitFont(ctx, lines[0] || '0–0', (W - pad * 2) * 0.72, 190 * u, 800, fUI);
    const lh = px * 0.98;
    const blockTop = H * 0.30;
    ctx.fillStyle = accent;
    ctx.textAlign = 'center';
    ctx.font = `800 ${px}px ${fUI}`;
    lines.forEach((ln, i) => ctx.fillText(ln, W / 2, blockTop + i * lh));
    const scoreBottom = blockTop + (lines.length - 1) * lh;

    // Names + center chip.
    const rowY = scoreBottom + 100 * u;
    const chipLabel = [`${aSets}–${bSets}`, d.context || d.category].filter(Boolean).join(' · ');
    const chip = drawChip(ctx, W / 2, rowY, chipLabel, 26 * u, fUI, accent, contrastColor(accent), 24 * u, 15 * u);

    const wName = winA ? pairName(d, 'a') : pairName(d, 'b');
    const lName = winA ? pairName(d, 'b') : pairName(d, 'a');
    // Clamp each name column so it never runs under the centered chip.
    const gap = 26 * u;
    const leftMaxW = (chip.x - gap) - pad;
    const rightMaxW = (W - pad) - (chip.x + chip.w + gap);
    drawStackNames(ctx, pad, rowY, splitPair(wName), c.text, 'left', u, fUI, leftMaxW);
    drawStackNames(ctx, W - pad, rowY, splitPair(lName), c.muted, 'right', u, fUI, rightMaxW);

    drawVoleoBrand(ctx, logoColor, W / 2, H - 64 * u, 19 * u, 'center', c.muted, fMono);
    ctx.textAlign = 'left';
}

/* ---- new-template helpers ---- */
// Auto-shrink a font so `text` fits within maxW.
function fitFont(ctx, text, maxW, startPx, weight, family) {
    let px = startPx;
    ctx.font = `${weight} ${px}px ${family}`;
    while (ctx.measureText(text).width > maxW && px > 12) {
        px -= 2;
        ctx.font = `${weight} ${px}px ${family}`;
    }
    return px;
}

// A filled rounded chip with centered text; returns its box {x,y,w,h}.
function drawChip(ctx, cx, cy, text, fontPx, family, fillColor, textColor, padX, padY) {
    ctx.font = `700 ${fontPx}px ${family}`;
    const tw = ctx.measureText(text).width;
    const w = tw + padX * 2;
    const h = fontPx + padY * 2;
    const x = cx - w / 2;
    const y = cy - h / 2;
    roundRect(ctx, x, y, w, h, h * 0.30);
    ctx.fillStyle = fillColor;
    ctx.fill();
    ctx.fillStyle = textColor;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, cx, cy + fontPx * 0.04);
    ctx.textBaseline = 'alphabetic';
    return { x, y, w, h };
}

// Inline "value LABEL" wing, centered on cx (value bold, label mono faint).
function drawWingInline(ctx, cx, y, value, label, u, fUI, fMono, valColor, labColor) {
    ctx.textBaseline = 'middle';
    ctx.font = `700 ${26 * u}px ${fUI}`;
    const vw = ctx.measureText(value).width;
    ctx.font = `500 ${16 * u}px ${fMono}`;
    const lw = ctx.measureText(' ' + label).width;
    let x = cx - (vw + lw) / 2;
    ctx.textAlign = 'left';
    ctx.fillStyle = valColor;
    ctx.font = `700 ${26 * u}px ${fUI}`;
    ctx.fillText(value, x, y);
    x += vw;
    ctx.fillStyle = labColor;
    ctx.font = `500 ${16 * u}px ${fMono}`;
    ctx.fillText(' ' + label, x, y);
    ctx.textBaseline = 'alphabetic';
}

// Two-line names anchored at x (align left/right), vertically centered on cy.
// Each line is shrunk to fit maxW, then ellipsized if still too wide, so names
// never collide with the centered chip.
function drawStackNames(ctx, x, cy, parts, color, align, u, fUI, maxW) {
    ctx.fillStyle = color;
    ctx.textAlign = align;
    ctx.textBaseline = 'middle';
    const lh = 42 * u;
    const startY = cy - (parts.length - 1) * lh / 2;
    const floor = 22 * u;
    parts.forEach((p, i) => {
        let px = 34 * u;
        ctx.font = `700 ${px}px ${fUI}`;
        if (maxW) {
            while (ctx.measureText(p).width > maxW && px > floor) {
                px -= 1;
                ctx.font = `700 ${px}px ${fUI}`;
            }
        }
        let text = p;
        if (maxW && ctx.measureText(text).width > maxW) {
            while (text.length > 1 && ctx.measureText(text + '…').width > maxW) text = text.slice(0, -1);
            text = text + '…';
        }
        ctx.fillText(text, x, startY + i * lh);
    });
    ctx.textBaseline = 'alphabetic';
}

// Winner caption from the winning pair's first names → "A & B ganan".
function winnerCaption(d) {
    const w = d.winner === 'b' ? d.pairB : d.pairA;
    const parts = splitPair(w || '').map((p) => p.trim().split(/\s+/)[0]).filter(Boolean);
    if (parts.length >= 2) return `${parts[0]} & ${parts[1]} ganan`;
    if (parts.length === 1) return `${parts[0]} gana`;
    return 'Resultado';
}

// Dark or light text that reads on top of `hex` (by luminance).
function contrastColor(hex) {
    const [r, g, b] = hexToRgb(hex);
    const L = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return L > 0.6 ? '#14140f' : '#ffffff';
}

/* ---- primitive helpers ---- */
function line(ctx, x1, y1, x2, y2, color) {
    ctx.strokeStyle = color;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

function dot(ctx, x, y, r, color, filled) {
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
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

function truncate(s, n) {
    s = s || '';
    return s.length > n ? s.slice(0, n - 1) + '…' : s;
}

function hexToRgb(hex) {
    const m = (hex || '#000000').replace('#', '');
    const v = m.length === 3 ? m.split('').map((c) => c + c).join('') : m;
    const n = parseInt(v, 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

// Normalize any color string to a 6-digit hex for <input type=color>.
function toHexInput(color) {
    if (!color) return '#ffffff';
    if (color[0] === '#') {
        const h = color.slice(1);
        if (h.length === 3) return '#' + h.split('').map((c) => c + c).join('');
        return '#' + h.slice(0, 6);
    }
    const [r, g, b] = hexToRgb(color); // falls back to 0 for rgba()
    const to = (n) => n.toString(16).padStart(2, '0');
    return `#${to(r)}${to(g)}${to(b)}`;
}

function slug(s) {
    return (s || '').toString().toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

function shareCaption(d) {
    const winner = d.winner === 'b' ? d.pairB : d.pairA;
    const parts = [d.tournament, d.category].filter(Boolean).join(' · ');
    return [parts, winner ? `🏆 ${winner}` : '', 'Voleo'].filter(Boolean).join('\n');
}
