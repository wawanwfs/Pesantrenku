// Preferensi tema: 'light' | 'dark' | 'system'. Nilai awal dipasang lebih dulu oleh
// public/theme-init.js; modul ini hanya membaca, mengubah, dan menyinkronkannya.
import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'pk-theme';
const PREFERENCES = ['light', 'dark', 'system'];

function readStoredPreference() {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY);
    return PREFERENCES.includes(value) ? value : 'system';
  } catch {
    return 'system';
  }
}

function systemPrefersDark() {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function resolveTheme(preference) {
  return preference === 'dark' || (preference === 'system' && systemPrefersDark()) ? 'dark' : 'light';
}

export function applyTheme(preference, { animate = false } = {}) {
  const root = document.documentElement;
  const next = resolveTheme(preference);
  if (animate) {
    // Kelas pembantu (bukan style inline) agar peralihan warna halus; dilepas setelah selesai.
    root.classList.add('pk-theme-transition');
    window.setTimeout(() => root.classList.remove('pk-theme-transition'), 320);
  }
  root.setAttribute('data-theme', next);
  root.setAttribute('data-theme-preference', preference);
  return next;
}

export function useTheme() {
  const [preference, setPreferenceState] = useState(() => (typeof window === 'undefined' ? 'system' : readStoredPreference()));
  const [resolved, setResolved] = useState(() => (typeof window === 'undefined' ? 'light' : resolveTheme(readStoredPreference())));

  const setPreference = useCallback((value) => {
    const next = PREFERENCES.includes(value) ? value : 'system';
    try {
      if (next === 'system') window.localStorage.removeItem(STORAGE_KEY);
      else window.localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Penyimpanan bisa diblokir (mode privat); tema tetap berlaku untuk sesi ini.
    }
    setPreferenceState(next);
    setResolved(applyTheme(next, { animate: true }));
  }, []);

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') return undefined;
    const query = window.matchMedia('(prefers-color-scheme: dark)');
    const onSystemChange = () => {
      if (readStoredPreference() === 'system') setResolved(applyTheme('system', { animate: true }));
    };
    const onStorage = (event) => {
      if (event.key !== STORAGE_KEY && event.key !== null) return;
      const next = readStoredPreference();
      setPreferenceState(next);
      setResolved(applyTheme(next, { animate: true }));
    };
    query.addEventListener('change', onSystemChange);
    window.addEventListener('storage', onStorage);
    return () => {
      query.removeEventListener('change', onSystemChange);
      window.removeEventListener('storage', onStorage);
    };
  }, []);

  return { preference, resolved, setPreference, toggle: () => setPreference(resolved === 'dark' ? 'light' : 'dark') };
}
