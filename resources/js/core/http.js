/**
 * core/http.js
 * Thin fetch wrapper for the app. Handles CSRF, JSON encoding/decoding,
 * and consistent error shape. Every AJAX call in the app goes through here.
 *
 * Usage:
 *   import { get, post, put, del } from './core/http';
 *   const data = await get('/api/tournaments');
 *   await post('/dashboard/categories', { name: '5ta Femenil' });
 */

/** Read the CSRF token Laravel renders into <meta name="csrf-token"> */
function csrfToken() {
  const el = document.querySelector('meta[name="csrf-token"]');
  return el ? el.getAttribute('content') : '';
}

/** Standard error thrown by all helpers. Carries status + parsed body. */
export class HttpError extends Error {
  constructor(message, status, body) {
    super(message);
    this.name = 'HttpError';
    this.status = status;
    this.body = body;
    // Laravel validation errors live in body.errors as { field: [msg, ...] }
    this.validationErrors = body && body.errors ? body.errors : null;
  }
}

async function request(method, url, body = null, options = {}) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken(),
    ...(options.headers || {}),
  };

  const config = { method, headers, ...options };

  if (body !== null && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    config.body = JSON.stringify(body);
  } else if (body instanceof FormData) {
    config.body = body; // browser sets multipart boundary
  }

  let response;
  try {
    response = await fetch(url, config);
  } catch (networkErr) {
    throw new HttpError('Error de conexión. Revisa tu internet.', 0, null);
  }

  // 204 No Content
  if (response.status === 204) return null;

  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const payload = isJson ? await response.json().catch(() => null) : await response.text();

  if (!response.ok) {
    const message =
      (payload && payload.message) ||
      (response.status === 419 ? 'Tu sesión expiró. Recarga la página.' : 'Ocurrió un error.');
    throw new HttpError(message, response.status, payload);
  }

  return payload;
}

export const get  = (url, options)        => request('GET', url, null, options);
export const post = (url, body, options)  => request('POST', url, body, options);
export const put  = (url, body, options)  => request('PUT', url, body, options);
export const del  = (url, options)        => request('DELETE', url, null, options);

export default { get, post, put, del, HttpError };