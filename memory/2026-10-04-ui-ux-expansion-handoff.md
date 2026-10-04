# Handoff pengembangan UI/UX (4 Oktober 2026)

Status rilis: **NO-GO production** (tidak berubah). Sesi ini menyentuh frontend, dokumentasi, tes e2e,
mobile Flutter (tema gelap/animasi), dan satu perbaikan backend kecil (SQL tahun/bulan lintas DB).

## Keterbatasan lingkungan sesi (penting untuk membaca klaim di bawah)

- Sesi cloud hanya punya PHP 8.3; `composer.json` meminta ^8.4.1 dan dependensi Symfony 8 memakai
  property hooks (parse error di 8.3). Artinya **`php artisan test` TIDAK dijalankan** (PHPUnit juga
  crash di php-wasm: "unreachable"). Tidak ada klaim hijau untuk suite PHP.
- Backend Laravel sungguhan **berhasil dijalankan lewat php-wasm 8.4** (eksperimen di luar repo, port 8100,
  SQLite) dan dipakai menjalankan seluruh 19 tes e2e `ux-shell.spec.js` dengan login nyata (19/19 lulus, 12 menit). Catatan teknis: instance PHP
  tidak boleh dipakai ulang antar-request; cookieStore bawaan menelan cookie; `flock(LOCK_SH)` pada fd
  read-only mengembalikan false sehingga sesi hilang (perlu tambalan vendor lokal + lock manager no-op).
  Ini bukan pengganti `npm run test:e2e` di PHP 8.4 asli dengan MySQL.
- Yang dijalankan dan lolos: `npm run build`, `npm run lint:csp`, `npm run check:performance-budget`,
  eslint `no-undef`/`rules-of-hooks`, `scripts/ui-preview/crawl.mjs` (148 halaman, 0 error runtime),
  `a11y-audit.mjs` (axe, 0 temuan), 19 tes `ux-shell.spec.js` terhadap server preview, serta
  `flutter analyze` + `flutter test` untuk mobile.
- Anggaran aset terakhir: 1.390.954 / 1.500.000 B (sebelum sesi: 1.294.894 B).

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
| Tabel | pemilih kolom tersimpan, caption/scope/aria-rowcount, label aksi per baris, tanggal id-ID | `Widgets.jsx`, `AppDialogs.jsx`, `css/shell.css` |
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
4. **Dasbor 500 di SQLite** (dev/e2e): `DashboardAnalyticsConcern` memakai `YEAR()`/`MONTH()` MySQL.
   Kini lewat `sqlYear()`/`sqlMonth()` per driver (sqlite `strftime`, pgsql `EXTRACT`, default MySQL
   tidak berubah). Terverifikasi: `/dashboard/keuangan`, `/super-admin`, `/owner` = 200 di SQLite.
5. **CI mobile rusak sebelum sesi**: tes tanpa impor `MobileShell`, `FakeApiClient` tak meng-override
   `notifications()` (pumpAndSettle timeout), API usang. Diperbaiki.

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

1. Jalankan `php artisan test` dan `npm run test:e2e` pada PHP 8.4 + MySQL asli sebelum menyebut perubahan
   ini terverifikasi penuh (19 tes ux-shell sudah lulus dengan login nyata di php-wasm/SQLite; `pesantrenku.spec.js` TIDAK layak di php-wasm: tiap halaman ~27 dtk sehingga `page.goto` melewati batas 45 dtk; bukan kegagalan produk).
2. ~~Audit kontras otomatis~~ Selesai: `scripts/ui-preview/a11y-audit.mjs` (axe-core) 0 temuan pada 90 halaman/keadaan; ulangi terhadap backend sungguhan.
3. Mobile: tema gelap (`ThemeController`), skeleton, FadeSlideIn, pencarian menu, haptics sudah ada;
   belum diuji di perangkat/emulator (hanya `flutter test`/`analyze`).
4. `CrudController` (~930 baris) masih monolit (backlog 87).
5. Pencarian lintas modul (backlog 166) baru mencakup menu, bukan isi data.
6. Onboarding/tur per peran (backlog 169-170) belum ada.
