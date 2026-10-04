# Handoff pengembangan UI/UX (4 Oktober 2026)

Status rilis: **NO-GO production** (tidak berubah). Sesi ini hanya menyentuh frontend, dokumentasi, dan
tes e2e. Backend, migrasi, dan mobile **tidak diubah**.

## Keterbatasan lingkungan sesi (penting untuk membaca klaim di bawah)

- Sesi cloud hanya punya PHP 8.3; `composer.json` meminta ^8.4.1 dan dependensi Symfony 8 memakai
  property hooks (parse error di 8.3). Artinya **`php artisan test` dan `npm run test:e2e` terhadap
  backend sungguhan TIDAK dijalankan**. Tidak ada klaim hijau untuk keduanya.
- Yang dijalankan dan lolos: `npm run build`, `npm run lint:csp`, `npm run check:performance-budget`,
  eslint `no-undef`/`rules-of-hooks` (konfigurasi sementara di luar repo), `scripts/ui-preview/crawl.mjs`
  (148 halaman dari 10 panel × terang/gelap, 0 error runtime) dan 13 tes `tests/e2e/ux-shell.spec.js`
  terhadap **server preview** (props tiruan, login dilewati).
- Anggaran aset terakhir: 1.357.138 / 1.500.000 B (sebelum sesi: 1.294.894 B).

## Yang dikerjakan

| Area | Perubahan | Berkas utama |
|---|---|---|
| Memory | knowledge base repo (arsitektur, bisnis, kendala) | `memory/2026-10-04-repo-knowledge-base.md` |
| Mode gelap | pemetaan ulang skala warna, toggle, skrip tema eksternal, login/landing/bagan/cetak | `css/dark.css`, `public/theme-init.js`, `lib/theme.js`, `components/ThemeToggle.jsx` |
| Refactor | `Pages/App.jsx` 1.738 → 263 baris | `components/app/*` |
| Palet & pintasan | Ctrl/Cmd+K, `/`, `[`, `T`, `?`, tautan lewati | `CommandPalette.jsx`, `ShortcutsHelp.jsx`, `Pages/App.jsx` |
| Umpan balik | progres navigasi, toast notifikasi, skeleton, keadaan kosong/galat, error boundary | `NavigationProgress.jsx`, `ToastHost.jsx`, `ErrorBoundary.jsx`, `Widgets.jsx` |
| Data viz | bagan interaktif (tooltip, crosshair, legenda, keyboard, tabel sr-only) + animasi masuk | `components/NativeCharts.jsx` |
| Angka | penggulung angka StatCard | `components/AnimatedNumber.jsx` |
| Tabel/form | label kolom dan status Indonesia, ikon menu kontekstual | `AppDialogs.jsx`, `appSupport.jsx` |
| Login | token `--lg-*`, dark, Caps Lock, gerak masuk, getar saat gagal | `Pages/Login.jsx`, `css/login.css` |
| Landing | progres scroll, dock, filter peran, alur aktif, tombol ke atas, dark | `Pages/Landing.jsx`, `css/landing-extras.css` |
| Cetak | sembunyikan chrome, tabel penuh, tema terang otomatis | `css/shell.css`, `lib/theme.js` |
| Tes | tema, palet, pintasan, reduced motion, ketahanan chunk, landing | `tests/e2e/ux-shell.spec.js` |
| Dokumen | sistem desain; backlog diperbarui | `docs/ui/design-system.md`, `docs/backlog/improvement-backlog.md` |
| Alat | server preview Inertia tiruan, screenshot, crawl | `backend/scripts/ui-preview/` |

## Bug nyata yang ditemukan dan diperbaiki

1. **Dialog terpotong di area konten**: keyframe `pk-fade-up` berakhir di `translate3d(0,0,0)` dengan
   `fill-mode: both` (RC8), sehingga `.workspace-content > *` menjadi containing block untuk
   `position: fixed`. Modal CRUD tampil di dasar halaman dan backdrop tidak menutupi sidebar/topbar.
   Diperbaiki (`transform: none`, `backwards`, hapus `will-change` permanen).
2. **Kontras tombol CTA akhir landing**: `.landing-shell a { color: inherit }` mengalahkan
   `.landing-button-light`; teks terang di atas krem. Diperbaiki dengan spesifisitas lebih tinggi.
3. Sidebar mobile/desktop mengulang markup yang sama (dua salinan `NavList`); kini satu sumber.

## Pelajaran teknis (jangan diulang)

- CSS tanpa layer mengalahkan utilitas Tailwind v4; jangan memakai utilitas untuk menimpa kelas komponen.
- Skrip tema harus eksternal (CSP). Jangan memindahkannya inline.
- `React.lazy` yang gagal import tidak dapat di-retry dalam proses yang sama; `ErrorBoundary` memakai
  `onRetry` = muat ulang halaman untuk bagan.
- Playwright headless: callback `IntersectionObserver` baru terkirim setelah frame berikutnya; tunggu rAF
  sebelum membaca status berbasis observer.
- Jangan `pkill -f` dengan pola yang juga muncul di perintah shell yang sama (membunuh shell). Pakai
  `fuser -k PORT/tcp`.

## Belum dikerjakan / saran lanjutan

1. Jalankan `php artisan test` dan `npm run test:e2e` pada PHP 8.4 sebelum menyebut perubahan ini terverifikasi
   end-to-end. Khusus: alur login pada halaman Login yang ditulis ulang (selector `input[autocomplete=username]`,
   `input[type=password]`, tombol `/Masuk/` dipertahankan), dan pintasan keyboard setelah login nyata.
2. ~~Audit kontras otomatis~~ Selesai: `scripts/ui-preview/a11y-audit.mjs` (axe-core) 0 temuan pada 90 halaman/keadaan; ulangi terhadap backend sungguhan.
3. Mobile Flutter belum disentuh (tidak ada SDK di sesi). Tema gelap mobile adalah kelanjutan alami.
4. `CrudController` (~930 baris) masih monolit (backlog 87).
5. Pencarian lintas modul (backlog 166) baru mencakup menu, bukan isi data.
6. Onboarding/tur per peran (backlog 169-170) belum ada.
