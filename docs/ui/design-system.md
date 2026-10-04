# Sistem desain Pesantrenku (web)

Dokumen hidup untuk token, tema, gerak, dan komponen UI panel web. Sumber kebenaran tetap kode di
`backend/resources/css/` dan `backend/resources/js/components/`; dokumen ini menjelaskan *mengapa* dan
*aturan pakainya*. Perbarui bila Anda mengubah salah satu aturan di bawah.

## 1. Berkas gaya dan urutan

`resources/css/app.css` mengimpor, berurutan:

| Berkas | Isi |
|---|---|
| `app.css` (inline) | `@theme` Tailwind (skala `primary`, `neutral`), token `:root`, token gelap `[data-theme='dark']`, komponen shell (sidebar, topbar, tabel, dialog, bagan) |
| `motion.css` | keyframe dasar, reveal, hover/press, skeleton, kebijakan reduced motion |
| `ui-kit.css` | primitif opt-in: `pk-badge`, `pk-meter`, `pk-track`, `pk-figure` |
| `landing-motion.css` | reveal landing berbasis `animation-timeline: view()` |
| `shell.css` | tautan lewati, progres navigasi, palet perintah, toast, skeleton, keadaan kosong, notifikasi, cetak |
| `dark.css` | pemetaan ulang skala warna untuk mode gelap + aturan untuk komponen berwarna literal |

Halaman memuat CSS tambahan lewat impor di JSX (otomatis ter-*split*): `login.css` (Login),
`landing.css` + `landing-extras.css` (Landing).

**Kaskade yang perlu diingat**: Tailwind v4 menaruh utilitas di `@layer utilities`. CSS tanpa layer
(semua berkas di atas) selalu **mengalahkan** utilitas, berapa pun spesifisitasnya. Konsekuensinya:
(1) jangan memasang utilitas (`pr-14`, `left-auto`) untuk menimpa properti yang sudah diatur kelas
komponen; buat kelas modifier; (2) `dark.css` memanfaatkan sifat ini untuk menimpa `.bg-white` dsb. tanpa
`!important`.

## 2. Token

| Token | Terang | Gelap | Pemakaian |
|---|---|---|---|
| `--surface` | `#FFFFFF` | `#111A26` | kartu, panel, dialog |
| `--surface-muted` | `#F6F8FA` | `#0C141E` | latar kolom, kbd, segmented |
| `--page-bg` | `#EEF3F5` | `#0A0F16` | latar halaman |
| `--border` | `#DDE4EA` | `#26344A` | garis |
| `--ink` | `#172033` | `#E6EDF5` | teks utama |
| `--muted` | `#5F6E82` | `#8CA0B8` | teks sekunder |
| `--action` | `primary-600` | `primary-400` | warna aksi/tautan |
| `--focus` | `rgba(23,135,91,.2)` | `rgba(53,168,114,.35)` | ring fokus |

Merek: primary `#126D4A` (600). Font `Poppins` → `ui-sans-serif`. Radius 8px untuk panel, kontrol,
dan dialog. Landing memakai token sendiri `--landing-*`, login `--lg-*` (keduanya punya varian gelap).

### Mode gelap

- Atribut `data-theme="light|dark"` pada `<html>`; preferensi (`light|dark|system`) di
  `localStorage['pk-theme']`; `system` mengikuti `prefers-color-scheme` dan bereaksi saat berubah.
- `public/theme-init.js` dimuat sebagai **skrip eksternal** di `<head>` agar tema terpasang sebelum cat
  pertama tanpa melanggar CSP (skrip inline diblokir). Jangan menggantinya dengan `<script>` inline.
- `dark.css` memetakan ulang `--color-neutral-*`, `--color-primary-*` dan palet status. Semua utilitas
  `text-neutral-*`, `border-neutral-*`, `bg-primary-50`, dst. otomatis ikut. Komponen dengan hex literal
  butuh aturan `[data-theme='dark'] ...` sendiri.
- Cetak selalu terang (`beforeprint`/`afterprint` di `lib/theme.js`).
- Kontras: nilai teks utama/sekunder di atas dipilih ≥ 4.5:1 terhadap `--surface` gelap (hitungan
  manual, lihat komentar di `dark.css`). **Belum** diaudit dengan alat otomatis (axe/Lighthouse).

## 3. Gerak

- Durasi: `--pk-fast` 140ms, `--pk-normal` 240ms, `--pk-slow` 620ms. Easing `--pk-ease`, `--pk-ease-out`.
- Semua animasi berada di balik `@media (prefers-reduced-motion: no-preference)` atau dimatikan di blok
  `reduce`. Uji: `tests/e2e/ux-shell.spec.js` ("kebijakan reduced motion").
- Keadaan dasar elemen **selalu terlihat**; animasi hanya tambahan.
- **Jebakan**: keyframe yang berakhir di `transform: translate3d(0,0,0)` dengan `fill-mode: both`
  membuat elemen menjadi *containing block* bagi turunan `position: fixed` → overlay dialog terpotong.
  Selalu akhiri dengan `transform: none` dan pakai `backwards` bila keadaan akhir = keadaan alami.
  Hindari `will-change: transform` permanen untuk alasan yang sama.
- Scroll-driven (`animation-timeline`) hanya dalam `@supports`; gunakan properti *longhand* (shorthand
  `animation` mereset timeline).

## 4. Komponen shell (`resources/js/components/app/`)

| Komponen | Catatan |
|---|---|
| `Sidebar.jsx` | `DesktopSidebar`, `MobileSidebar`, kartu pesantren aktif, state ciut tersimpan (`pk-sidebar-collapsed`) |
| `Topbar.jsx` | pencarian menu, switcher tenant, notifikasi (polling/SSE/WebSocket) + toast kenaikan, tema, akun |
| `CommandPalette.jsx` | Ctrl/Cmd+K atau `/`; pola combobox ARIA; riwayat 5 halaman terakhir; pindah tenant; ganti tema; keluar |
| `ShortcutsHelp.jsx` | `?` — daftar pintasan |
| `NavigationProgress.jsx` | bilah progres selama Inertia memuat (muncul setelah 120 ms) |
| `ToastHost.jsx` + `toast.js` | `toast.success/info/warning/error(message, {title})`. Pesan penting tetap inline |
| `ErrorBoundary.jsx` | galat render → `StatePanel` dengan aksi; `resetKey` memulihkan saat pindah halaman |
| `Widgets.jsx` | `Header` (breadcrumb), `StatCard` (angka bergulir), `Table` (+skeleton, keadaan kosong), feed, daftar |
| `CrudModule.jsx` | tabel + filter + impor/ekspor + dialog; keadaan kosong membimbing (reset filter / tambah data) |
| `../NativeCharts.jsx` | bagan SVG native interaktif (tooltip, crosshair, legenda, keyboard, tabel sr-only) |
| `../AnimatedNumber.jsx` | penggulung angka; nilai akhir persis string asli; mati saat reduced motion |
| `../ThemeToggle.jsx` | `ThemeToggleButton`, `ThemeSegmented` |

Pintasan: `Ctrl/Cmd+K` palet, `/` palet, `[` sidebar, `T` tema, `?` bantuan, `Esc` tutup. Pintasan satu
tombol tidak aktif saat mengetik atau ketika ada dialog modal.

## 5. Aturan keras (dijaga CI)

1. Tanpa inline style / `<style>` / `.style.x =` di `resources/js` dan `app.blade.php` (`npm run lint:csp`).
   Nilai dinamis → kelas bertingkat; atribut SVG (`x`, `y`, `transform`) tidak dilarang.
2. Anggaran aset: total 1.500.000 B, JS per berkas 550.000 B, CSS per berkas 250.000 B
   (`npm run check:performance-budget`).
3. Tanpa dependensi UI berat (lihat `figma-prototype-adoption.md`).
4. Tanpa klaim palsu di halaman publik (testimoni, SLA, persentase kesiapan).

## 6. Cara memverifikasi tanpa PHP

`backend/scripts/ui-preview/` menyajikan hasil `npm run build` dengan props Inertia tiruan:

```bash
cd backend && npm run build
node scripts/ui-preview/server.mjs 4173 &
node scripts/ui-preview/crawl.mjs light      # 148 halaman, laporkan error runtime
node scripts/ui-preview/shoot.mjs shots dark # screenshot desktop+mobile
node scripts/ui-preview/shoot-scroll.mjs shots dark / 1440 900 900
```

Ini **bukan** pengganti `npm run test:e2e` terhadap backend sungguhan (login, CRUD, workflow).
