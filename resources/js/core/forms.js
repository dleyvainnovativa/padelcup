/**
 * core/forms.js
 * Serialize a <form> into a plain object (or FormData), handling
 * checkboxes, multi-selects, and nested bracket keys like name="court[0][id]".
 *
 * Usage:
 *   import { serialize, applyErrors, clearErrors } from './core/forms';
 *   const data = serialize(formEl);
 *   applyErrors(formEl, httpError.validationErrors);
 */

/** Serialize a form to a plain JS object. */
export function serialize(form) {
  const fd = new FormData(form);
  const obj = {};

  for (const [key, value] of fd.entries()) {
    setNested(obj, key, value);
  }

  // Unchecked checkboxes don't appear in FormData; normalize them to false.
  form.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
    if (cb.name && !(cb.name in obj)) setNested(obj, cb.name, false);
    else if (cb.name && cb.checked) setNested(obj, cb.name, true);
  });

  return obj;
}

/** Return a FormData (use for file uploads). */
export function toFormData(form) {
  return new FormData(form);
}

/** Support keys like "a[b][0]" -> nested object/array assignment. */
function setNested(obj, path, value) {
  const keys = path.replace(/\]/g, '').split('[');
  let cur = obj;
  keys.forEach((k, i) => {
    const last = i === keys.length - 1;
    if (last) {
      cur[k] = value;
    } else {
      const nextIsIndex = /^\d+$/.test(keys[i + 1]);
      if (cur[k] == null) cur[k] = nextIsIndex ? [] : {};
      cur = cur[k];
    }
  });
}

/**
 * Apply Laravel-style validation errors ({ field: [msg] }) to a form,
 * marking inputs .is-invalid and writing the first message into a
 * sibling .invalid-feedback (Bootstrap convention).
 */
export function applyErrors(form, errors) {
  clearErrors(form);
  if (!errors) return;

  Object.entries(errors).forEach(([field, messages]) => {
    const input = form.querySelector(`[name="${field}"]`);
    if (!input) return;
    input.classList.add('is-invalid');
    let feedback = input.parentElement.querySelector('.invalid-feedback');
    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      input.parentElement.appendChild(feedback);
    }
    feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
  });
}

export function clearErrors(form) {
  form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  form.querySelectorAll('.invalid-feedback').forEach((el) => (el.textContent = ''));
}

export default { serialize, toFormData, applyErrors, clearErrors };