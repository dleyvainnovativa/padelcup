/**
 * core/toast.js
 * Lightweight toast notifications. No dependency; renders into a fixed
 * stack and auto-dismisses. Styled by .toast-* classes in theme.css.
 *
 * Usage:
 *   import { toast } from './core/toast';
 *   toast.success('Pareja inscrita correctamente');
 *   toast.error('No se pudo guardar', { title: 'Error' });
 */

const ICONS = {
  success: 'fa-circle-check',
  warning: 'fa-triangle-exclamation',
  error:   'fa-circle-xmark',
  info:    'fa-circle-info',
};

let stackEl = null;

function ensureStack() {
  if (stackEl) return stackEl;
  stackEl = document.createElement('div');
  stackEl.className = 'toast-stack';
  stackEl.setAttribute('aria-live', 'polite');
  stackEl.setAttribute('aria-atomic', 'true');
  document.body.appendChild(stackEl);
  return stackEl;
}

function show(type, message, { title = '', duration = 4000 } = {}) {
  const stack = ensureStack();

  const item = document.createElement('div');
  item.className = `toast-item toast-item--${type}`;
  item.setAttribute('role', type === 'error' ? 'alert' : 'status');

  item.innerHTML = `
    <i class="toast-item__icon fa-solid ${ICONS[type] || ICONS.info}"></i>
    <div class="toast-item__body">
      ${title ? `<div class="toast-item__title"></div>` : ''}
      <div class="toast-item__text"></div>
    </div>
    <button class="toast-item__close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
  `;

  // textContent (not innerHTML) to avoid XSS from dynamic messages
  if (title) item.querySelector('.toast-item__title').textContent = title;
  item.querySelector('.toast-item__text').textContent = message;

  const remove = () => {
    item.classList.add('is-leaving');
    item.addEventListener('animationend', () => item.remove(), { once: true });
  };

  item.querySelector('.toast-item__close').addEventListener('click', remove);
  stack.appendChild(item);

  if (duration > 0) setTimeout(remove, duration);
  return remove;
}

export const toast = {
  success: (msg, opts) => show('success', msg, opts),
  warning: (msg, opts) => show('warning', msg, opts),
  error:   (msg, opts) => show('error', msg, opts),
  info:    (msg, opts) => show('info', msg, opts),
};

export default toast;