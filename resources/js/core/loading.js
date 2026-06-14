/**
 * core/loading.js
 * Helpers to toggle loading states on buttons and containers.
 * Styled by .btn-loading / .overlay-loading / .spinner in theme.css.
 *
 * Usage:
 *   import { buttonLoading, withLoading } from './core/loading';
 *   const stop = buttonLoading(btn);  // ... later: stop();
 *
 *   await withLoading(btn, async () => { await post(...); });
 */

/** Put a button into a loading state. Returns a function that restores it. */
export function buttonLoading(btn, { disable = true } = {}) {
  if (!btn) return () => {};
  const wasDisabled = btn.disabled;
  btn.classList.add('btn-loading');
  if (disable) btn.disabled = true;
  btn.setAttribute('aria-busy', 'true');

  return function restore() {
    btn.classList.remove('btn-loading');
    btn.disabled = wasDisabled;
    btn.removeAttribute('aria-busy');
  };
}

/** Dim a container (table, card) while something loads. Returns a restore fn. */
export function containerLoading(el) {
  if (!el) return () => {};
  el.classList.add('overlay-loading');
  el.setAttribute('aria-busy', 'true');
  return function restore() {
    el.classList.remove('overlay-loading');
    el.removeAttribute('aria-busy');
  };
}

/**
 * Wrap an async function with a loading state on a target element.
 * Restores automatically even if the function throws.
 */
export async function withLoading(target, fn) {
  const isButton = target && target.tagName === 'BUTTON';
  const stop = isButton ? buttonLoading(target) : containerLoading(target);
  try {
    return await fn();
  } finally {
    stop();
  }
}

export default { buttonLoading, containerLoading, withLoading };