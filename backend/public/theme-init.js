/*
 * Menerapkan tema sebelum cat pertama. Berkas eksternal agar lolos CSP (tanpa skrip inline).
 * Preferensi: 'light' | 'dark' | 'system' (default) di localStorage['pk-theme'].
 */
(function () {
  var root = document.documentElement;
  var preference = 'system';
  try {
    preference = window.localStorage.getItem('pk-theme') || 'system';
  } catch (error) {
    preference = 'system';
  }
  if (preference !== 'light' && preference !== 'dark') preference = 'system';
  var prefersDark = false;
  try {
    prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  } catch (error) {
    prefersDark = false;
  }
  root.setAttribute('data-theme', preference === 'dark' || (preference === 'system' && prefersDark) ? 'dark' : 'light');
  root.setAttribute('data-theme-preference', preference);

  // Preferensi gerak manual ('reduce') menambah, tidak menggantikan, prefers-reduced-motion milik OS.
  try {
    if (window.localStorage.getItem('pk-motion') === 'reduce') root.setAttribute('data-motion', 'reduce');
    var density = window.localStorage.getItem('pk-density');
    if (density === 'compact') root.setAttribute('data-density', 'compact');
  } catch (error) {
    // Penyimpanan diblokir: gunakan bawaan.
  }
})();
