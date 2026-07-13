// Ads carousel — gentle auto-slide, keeps each ad's current size.
//
// Works on the existing markup: .pub-ads__track[data-ad-carousel] containing
// .pub-ad children. It does NOT resize anything — it turns the track into a
// horizontally scrolling row and advances it on a timer, so several ads at their
// current size slide across, rather than one big ad at a time.
//
// - Pauses on hover / touch.
// - Respects prefers-reduced-motion (no auto-slide).
// - Does nothing if there's only one ad.
// - Loops seamlessly by cloning the first ads onto the end.

export function initAdCarousel() {
  const tracks = document.querySelectorAll('[data-ad-carousel]');
  tracks.forEach(setupTrack);
}

function setupTrack(track) {
  const ads = Array.from(track.children).filter((c) => c.classList.contains('pub-ad'));
  if (ads.length <= 1) return; // nothing to rotate

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Ensure the track is a horizontal, scrollable row without changing ad sizes.
  track.style.display = 'flex';
  track.style.overflowX = 'hidden';
  track.style.scrollBehavior = 'smooth';
  track.style.gap = track.style.gap || '12px';
  // Keep each ad from shrinking so its size is preserved.
  ads.forEach((ad) => { ad.style.flex = '0 0 auto'; });

  if (reduce) {
    // Accessibility: let the user scroll manually, no auto-motion.
    track.style.overflowX = 'auto';
    return;
  }

  // Clone leading ads to the end so the loop is seamless.
  const clones = ads.map((ad) => {
    const c = ad.cloneNode(true);
    c.setAttribute('aria-hidden', 'true');
    c.dataset.clone = '1';
    track.appendChild(c);
    return c;
  });

  let paused = false;
  track.addEventListener('mouseenter', () => { paused = true; });
  track.addEventListener('mouseleave', () => { paused = false; });
  track.addEventListener('touchstart', () => { paused = true; }, { passive: true });
  track.addEventListener('touchend', () => { setTimeout(() => { paused = false; }, 2500); });

  // Advance one "ad width" every few seconds.
  let idx = 0;
  const STEP_MS = 2000;

  const advance = () => {
    if (paused || document.hidden) return;
    idx++;
    const target = ads[0].offsetWidth + gapPx(track);
    track.scrollTo({ left: track.scrollLeft + target, behavior: 'smooth' });

    // When we've scrolled past the original set (into the clones), jump back to
    // the start with no animation so it loops forever without a visible reset.
    if (idx >= ads.length) {
      setTimeout(() => {
        track.style.scrollBehavior = 'auto';
        track.scrollTo({ left: 0 });
        track.style.scrollBehavior = 'smooth';
        idx = 0;
      }, 600); // after the smooth scroll finishes
    }
  };

  let timer = setInterval(advance, STEP_MS);

  // Pause when the page/tab is hidden (saves cycles, avoids desync).
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(timer); }
    else { timer = setInterval(advance, STEP_MS); }
  });
}

function gapPx(el) {
  const g = parseInt(getComputedStyle(el).columnGap || getComputedStyle(el).gap || '0', 10);
  return Number.isFinite(g) ? g : 0;
}
