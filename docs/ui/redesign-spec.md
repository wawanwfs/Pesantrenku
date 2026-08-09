# Spesifikasi Redesign UI/UX Pesantrenku

Dokumen hidup untuk redesign web panel dan landing. Semua keputusan di sini
berakar pada token yang sudah ada di `backend/resources/css/app.css`,
primitif `ui-kit.css`, sistem `motion.css`, serta batasan produksi:
CSP tanpa inline style (`scripts/check-csp-compatibility.mjs`), budget aset
(total 1.500.000 B, JS 550.000 B), dan larangan library berat
(shadcn/MUI/motion/recharts ditolak, lihat `docs/ui/figma-prototype-adoption.md`).

## 1. Prinsip

1. **Data nyata, bukan dekorasi.** Angka besar (`.pk-figure-value`) hanya dari
   data sesungguhnya. Tidak ada klaim marketing tanpa dasar.
2. **Peran menentukan tampilan.** Lima panel (Super Admin, Owner, Keuangan,
   Akademik, TU) berbagi shell yang sama tetapi kartu, menu, dan aksi berbeda
   per role dari `DashboardService`.
3. **CSS murni, tanpa inline style.** Semua state visual lewat kelas.
4. **Aksesibel sejak desain**, bukan ditambal: WCAG AA, keyboard-first,
   reduced-motion dihormati.
5. **Bahasa Indonesia konsisten.** Satu glosarium istilah: santri, wali,
   musyrif, pengasuh, tahfidz, murajaah (backlog #174).

## 2. Design token

### 2.1 Warna (sudah ada, dipertahankan)

Primary hijau merek `#126D4A` (`--color-primary-600`) dengan skala 50-900,
netral slate 50-900, font Poppins. Token permukaan:
`--surface`, `--surface-muted`, `--border`, `--ink`, `--muted`, `--focus`,
`--shadow-surface`.

### 2.2 Mode gelap (baru, backlog #159)

Implementasi lewat `data-theme="dark"` pada `<html>` + `color-scheme: dark`,
dipicu toggle yang menyimpan preferensi di localStorage dan menghormati
`prefers-color-scheme` sebagai default. Token gelap:

| Token | Terang | Gelap |
|---|---|---|
| `--surface` | `#FFFFFF` | `#111A26` |
| `--surface-muted` | `#F6F8FA` | `#0C141E` |
| `--border` | `#DDE4EA` | `#26344A` |
| `--ink` | `#172033` | `#E6EDF5` |
| `--muted` | `#5F6E82` | `#8CA0B8` |
| latar html/body | `#EEF3F5` | `#0A0F16` |
| primary aksi | `600 #126D4A` | `400 #35A872` (kontras di latar gelap) |

Syarat rilis: audit kontras WCAG AA otomatis (axe) untuk kedua tema, termasuk
chart `NativeCharts` yang kini memakai warna grid/axis hardcoded
(`#E2E8F0`, `#CBD5E1`, `#64748B`) dan harus dipindah ke token.

### 2.3 Skala jarak, radius, bayangan

Didokumentasikan sebagai token eksplisit (backlog #161): spacing 4/8/12/16/24/32,
radius 8 (kontrol), 12 (kartu), 16 (dialog), bayangan hanya `--shadow-surface`
dan varian hover dari `.pk-card-glow`.

## 3. Arsitektur frontend (prasyarat redesign)

Redesign tidak boleh dikerjakan di atas `Pages/App.jsx` 101 KB (backlog #143).
Urutan wajib:

1. Pecah `App.jsx` menjadi: `layouts/AppShell.jsx` (sidebar + topbar),
   `pages/<role>/Dashboard.jsx`, `pages/crud/ResourceIndex.jsx`,
   `pages/crud/ResourceForm.jsx`, dan modul per domain.
2. Code-splitting per rute (`React.lazy` via resolver Inertia) agar dasbor
   tidak memuat seluruh aplikasi (#144).
3. Impor ikon lucide selektif per file (#145).
4. Error boundary per halaman dan per kartu dasbor (#154).

## 4. Redesign per area

### 4.1 App shell

- Sidebar role-based: kelompokkan menu per domain (Akademik, Asrama, Keuangan,
  Klinik, Operasional) dengan label kelompok, bukan daftar datar.
- Topbar: pencarian global lintas modul (#166), switcher pesantren untuk user
  multi-tenant, pemilih tahun ajaran, notifikasi realtime (Reverb), menu user.
- Breadcrumb konsisten di setiap halaman modul.
- Target sentuh minimum 44 px, fokus terlihat dengan `--focus`.

### 4.2 Dasbor per peran

- Stat card memakai `.pk-figure*` dengan data nyata + sparkline `NativeCharts`.
- Progress (hafalan, pembayaran semester) memakai `.pk-meter`.
- Status memakai `.pk-badge` varian `success/warning/danger/info/neutral`;
  makna tidak boleh disampaikan lewat warna saja - selalu ada teks (#184).
- Skeleton loading (`motion.css`) untuk setiap kartu, bukan spinner kosong (#153).
- Tabel panjang divirtualisasi (#152) dan kolom dapat dikonfigurasi per
  pengguna (#176).

### 4.3 CRUD dan form

- Empty state membimbing: jelaskan apa resource ini, tombol aksi pertama, dan
  tautan impor bila relevan (#162).
- Error state menawarkan tindakan (coba lagi, hubungi admin), bukan kode
  status mentah (#163). Respons 402 entitlement dirender sebagai ajakan
  upgrade paket yang manusiawi (#71).
- Konfirmasi destruktif menyebut dampak konkret, misalnya jumlah baris
  terhapus (#164).
- Umpan balik optimistis untuk aksi cepat dengan rollback saat gagal (#165).
- Form dinamis berbasis schema server dipertahankan; tambahkan penanda field
  wajib, bantuan inline, dan galat per field dari API.

### 4.4 Workflow (izin, pembayaran, klinik)

- Setiap resource ber-workflow menampilkan stepper status horizontal sesuai
  state machine di `docs/business-logic.md`.
- Aksi transisi hanya muncul bila role berhak; tombol menyebut akibat
  ("Setujui dan teruskan ke pengasuh").
- Timeline audit di panel samping memakai data `CrudController::timeline`
  yang sudah diredaksi `TimelineRedactor`.

### 4.5 Landing

- Indikator progres scroll (#171), satu momen puncak transisi (#172).
- Three.js tetap dimuat setelah first paint dan mati saat
  `prefers-reduced-data` / `prefers-reduced-motion` (#150-151).
- Konten hanya klaim terverifikasi.

## 5. Aksesibilitas (gerbang rilis)

1. Kontras AA kedua tema (#177).
2. Seluruh alur selesai dengan keyboard saja; urutan tab logis (#178).
3. Fokus dikelola saat dialog buka/tutup, pola sama dengan menu seluler
   landing yang sudah benar (#179-180).
4. ARIA untuk tabel data dan aksi baris (#181); perubahan asinkron diumumkan
   lewat live region (#182).
5. Reduced-motion mematikan semua animasi, diverifikasi Playwright (#186).
6. Uji pembaca layar berbahasa Indonesia (#185).

## 6. Mikrokopi

Glosarium tunggal (id konsisten di UI, API error, dan notifikasi):
santri, wali santri, musyrif, pengasuh, ustadz/ustadzah, tahfidz, setoran,
murajaah, mudif, tahun ajaran, tagihan, keringanan. Pesan galat API satu
bahasa (Indonesia) sesuai backlog #78.

## 7. Tahapan eksekusi

| Tahap | Isi | Gerbang selesai |
|---|---|---|
| R1 | Pecah App.jsx + code splitting + error boundary | budget JS per rute terpenuhi, e2e hijau |
| R2 | Token gelap + toggle + migrasi warna chart ke token | audit kontras AA kedua tema |
| R3 | Shell baru: sidebar berkelompok, topbar, breadcrumb | e2e navigasi + keyboard |
| R4 | Dasbor per peran dengan primitif ui-kit | visual regression Playwright |
| R5 | CRUD: empty/error/konfirmasi/optimistis | axe lolos di alur utama |
| R6 | Stepper workflow + timeline | e2e workflow izin/pembayaran/klinik |
| R7 | Landing polish + reduced-data | Lighthouse dan budget aset |

Setiap tahap adalah MR terpisah agar visual regression mudah ditinjau.
