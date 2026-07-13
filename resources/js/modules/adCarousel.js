// Ads carousel — gentle auto-slide, keeps each ad's current size.
//
// Works on the existing markup: .pub-ads__track[data-ad-carousel] containing
// .pub-ad children. It does NOT resize anything — it turns the track into a
// horizontally scrolling row and advances it on a timer, so several ads at their
// current size slide across, rather than one big ad at a time.
//
// - Always manually scrollable (thin scrollbar).
// - If the user manually scrolls (wheel / touch / drag), autoplay STOPS
//   permanently for this page load and hands control to the user.
// - Pauses (temporarily) on hover and when the tab is hidden.
// - Respects prefers-reduced-motion (no auto-slide; manual scroll only).
// - Does nothing if there's only one ad.
// - Loops seamlessly by cloning the leading ads onto the end.

export function initAdCarousel() {
  const tracks = document.querySelectorAll('[data-ad-carousel]');
  tracks.forEach(setupTrack);
}

function setupTrack(track) {
  const ads = Array.from(track.children).filter((c) => c.classList.contains('pub-ad'));
  if (ads.length <= 1) return; // nothing to rotate

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Always a horizontal, MANUALLY scrollable row — without changing ad sizes.
  track.style.display = 'flex';
  track.style.overflowX = 'auto';
  track.style.scrollBehavior = 'smooth';
  track.style.gap = track.style.gap || '12px';
  track.classList.add('pub-ads__track--carousel'); // for the thin-scrollbar CSS
  ads.forEach((ad) => { ad.style.flex = '0 0 auto'; });

  if (reduce) return; // reduced motion: manual scroll only, no autoplay

  // Clone leading ads to the end so the auto-loop is seamless.
  ads.forEach((ad) => {
    const c = ad.cloneNode(true);
    c.setAttribute('aria-hidden', 'true');
    c.dataset.clone = '1';
    c.classList.add('pub-ad--clone');
    track.appendChild(c);
  });

  let stopped = false;   // permanent: user took manual control
  let paused = false;    // temporary: hover / tab hidden
  let idx = 0;
  const STEP_MS = 2000;
  let timer = null;

  // --- Permanent stop on a genuine USER gesture ---------------------
  // IMPORTANT: listen for real input (wheel / touch / pointer drag), NOT the
  // generic 'scroll' event — autoplay's own scrollTo() fires 'scroll' too and
  // would otherwise make autoplay stop itself on the first tick.
  const stop = () => {
    if (stopped) return;
    stopped = true;
    document.querySelectorAll('.pub-ad--clone').forEach((t) => t.classList.add('d-none'));
    if (timer) { clearInterval(timer); timer = null; }
    // Leave the current scroll position as-is; the cloned ads just look like the
    // set repeating, which is fine for manual browsing.
  };
  track.addEventListener('wheel', stop, { passive: true });
  track.addEventListener('touchmove', stop, { passive: true });
  track.addEventListener('pointerdown', (e) => {
    // Pointer press for scrollbar drag / drag-scroll = manual control.
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    stop();
  }, { passive: true });
  // Keyboard scrolling (arrows/space) while the track is focused.
  track.addEventListener('keydown', (e) => {
    if (['ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown', ' '].includes(e.key)) stop();
  });

  // --- Temporary pause (hover) -------------------------------------
  track.addEventListener('mouseenter', () => { paused = true; });
  track.addEventListener('mouseleave', () => { paused = false; });

  const advance = () => {
    if (stopped || paused || document.hidden) return;
    idx++;
    const target = ads[0].offsetWidth + gapPx(track);
    track.scrollTo({ left: track.scrollLeft + target, behavior: 'smooth' });

    // When we've scrolled past the original set into the clones, jump back to the
    // start with no animation so the loop is invisible.
    if (idx >= ads.length) {
      setTimeout(() => {
        if (stopped) return; // user grabbed it mid-loop — don't yank them back
        track.style.scrollBehavior = 'auto';
        track.scrollTo({ left: 0 });
        track.style.scrollBehavior = 'smooth';
        idx = 0;
      }, 600);
    }
  };

  timer = setInterval(advance, STEP_MS);

  // Pause when the tab is hidden; resume only if the user hasn't taken over.
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      if (timer) { clearInterval(timer); timer = null; }
    } else if (!stopped && !timer) {
      timer = setInterval(advance, STEP_MS);
    }
  });
}

function gapPx(el) {
  const g = parseInt(getComputedStyle(el).columnGap || getComputedStyle(el).gap || '0', 10);
  return Number.isFinite(g) ? g : 0;
}