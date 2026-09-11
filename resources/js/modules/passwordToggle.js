/**
 * modules/passwordToggle.js
 * Show/hide toggle for password inputs. Any input wrapped in a
 * <div class="pw-field"> gets an eye button that flips type between
 * "password" and "text". Progressive enhancement — the field works
 * without JS; the button only appears once wired.
 *
 * Call initPasswordToggle() once on load.
 */
export function initPasswordToggle() {
  document.querySelectorAll('.pw-field').forEach((field) => {
    const input = field.querySelector('input[type="password"]');
    if (!input || field.dataset.pwReady === '1') return;
    field.dataset.pwReady = '1';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.setAttribute('aria-label', 'Mostrar contraseña');
    btn.setAttribute('title', 'Mostrar contraseña');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';

    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
      const label = show ? 'Ocultar contraseña' : 'Mostrar contraseña';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      // Keep focus/caret on the field so typing continues uninterrupted.
      input.focus();
    });

    field.appendChild(btn);
  });
}

export default { initPasswordToggle };
