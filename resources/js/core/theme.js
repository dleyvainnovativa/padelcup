/**
 * core/theme.js
 * Light/dark theme toggle. Persists the choice in a cookie (NOT localStorage),
 * so the server can read it and render the correct theme on first paint,
 * avoiding a flash. The <html data-theme> attribute is the source of truth.
 *
 * Usage:
 *   import { initTheme } from './core/theme';
 *   initTheme();  // wires up [data-theme-toggle] buttons
 */

const COOKIE = 'tc_theme';
const ONE_YEAR = 60 * 60 * 24 * 365;

function readCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name, value) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${ONE_YEAR}; SameSite=Lax`;
}

export function getTheme() {
  return document.documentElement.getAttribute('data-theme') || 'light';
}

export function setTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  writeCookie(COOKIE, theme);
  // Sync any toggle icons on the page
  document.querySelectorAll('[data-theme-toggle] i').forEach((icon) => {
    icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  });
}

export function toggleTheme() {
  setTheme(getTheme() === 'dark' ? 'light' : 'dark');
}

export function initTheme() {
  // The server should have already set data-theme from the cookie.
  // If it didn't (e.g. first ever visit), fall back to OS preference.
  if (!document.documentElement.getAttribute('data-theme')) {
    const cookie = readCookie(COOKIE);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    setTheme(cookie || (prefersDark ? 'dark' : 'light'));
  } else {
    // ensure icons match
    setTheme(getTheme());
  }

  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', toggleTheme);
  });
}

export default { initTheme, toggleTheme, setTheme, getTheme };