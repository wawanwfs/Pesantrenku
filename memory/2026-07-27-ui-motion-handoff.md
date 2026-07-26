# Handoff Audit Total dan UI/UX Motion RC8

Tanggal lokal: 27 Juli 2026  
Commit yang diaudit: `639923200a958b127f787bd739dd7e62f87a23b0`  
Status rilis: **NO-GO production** (tidak berubah dari RC7)

## Metode audit

Audit dilakukan secara statis melalui GitHub API: struktur monorepo, README, dokumentasi, memory RC6/RC7, entry Vite, `app.css`, `landing.css`, `Landing.jsx`, `App.jsx`, `Login.jsx`, dan seluruh script quality gate. **Tidak ada** perintah build, test, lint, container, atau browser yang dijalankan pada sesi ini. Karena itu tidak ada satu pun klaim runtime baru di file ini.

## Ringkasan temuan

### Masih terbuka dari RC7 (tidak boleh dianggap selesai)

1. Queued import belum mengulang operational row-scope terhadap assignment terkini.
2. Provisioning plan/subscription production belum menjadi workflow idempoten dan teraudit.
3. Queued export masih membekukan scope query awal.
4. Canary belum membuktikan worker panjang, scheduler, dan Reverb.
5. Kebijakan disable MFA belum memasukkan privileged custom role.
6. Timeline historis belum meredaksi pemilik sebelumnya.
7. Supply chain Android belum memiliki dependency verification, SBOM, dan attestation.

### Temuan baru RC8

| ID | Area | Temuan | Status |
|---|---|---|---|
| UX-01 | Web UI | App shell hampir tanpa umpan balik gerak; hanya `.icon-button` dan progress bar punya transisi. | Diperbaiki |
| UX-02 | Web UI | Belum ada strategi scroll reveal yang aman tanpa JS tambahan. | Diperbaiki |
| UX-03 | Web UI | Blok `.app-shell { min-width: 0 }` terduplikasi di `app.css`. | Diperbaiki |
| UX-04 | Aksesibilitas | `prefers-reduced-motion` hanya mencakup `.app-shell`, belum `.landing-shell`. | Diperbaiki |
| MAINT-01 | Frontend | `Pages/App.jsx` ~101 KB dalam satu file, menyulitkan review dan code splitting. | Terbuka |
| MAINT-02 | Quality gate | Belum ada gate yang memverifikasi kebijakan reduced motion. | Terbuka |

## Perbaikan UI/UX yang diterapkan

Lapisan baru `backend/resources/css/motion.css`, diimpor dari `app.css`, tanpa dependensi npm baru:

- **Reveal saat masuk viewport** memakai `animation-timeline: view()` di dalam `@supports`, dengan fallback animasi sekali jalan. Base state selalu terlihat, jadi konten tidak mungkin terjebak transparan.
- **Panel, kartu statistik, tabel, dan baris aktivitas**: transisi shadow/transform, hover lift halus, dan animasi tumbuh pada aksen kartu statistik.
- **Sidebar dan menu**: item menggeser halus saat hover, item nav muncul berurutan, menu mengambang memakai scale-in dengan `transform-origin` yang benar.
- **Dialog**: overlay fade dan panel scale-in, konsisten dengan gaya sudut 8px yang sudah ada.
- **Tombol dan input**: micro-interaction hover-lift dan press-scale, ring fokus lebih jelas, tombol disabled dikunci agar tidak bergerak.
- **State loading**: utilitas `.pk-skeleton` (shimmer) dan `.pk-spinner`, plus `aria-busy` yang berdenyut halus.
- **Landing page**: hover lift pada kartu modul/peran, penekanan tombol, dan scroll reveal untuk heading, kartu, alur kerja, serta CTA akhir tanpa mengubah bahasa visual atau klaim apa pun.
- **Aksesibilitas**: seluruh gerak dinonaktifkan pada `prefers-reduced-motion: reduce`, untuk `.app-shell`, `.landing-shell`, dan utilitas `pk-*`.

Semua animasi murni CSS, jadi tetap kompatibel dengan CSP produksi (tanpa inline style, tanpa elemen `<style>` runtime) dan tidak menambah JavaScript ke bundle.

## Gate yang wajib dijalankan sebelum merge

```bash
cd backend
npm run build
npm run check:performance-budget
npm run lint:csp
php artisan test
npm run test:e2e
npm run audit:ui
```

Budget CSS 250.000 byte dan total 1.500.000 byte harus tetap lolos setelah penambahan `motion.css`.

## Langkah lanjut yang disarankan

1. Tutup tujuh temuan RC7 sebelum membahas promosi rilis.
2. Pecah `Pages/App.jsx` menjadi modul per domain dengan lazy import.
3. Tambahkan assertion Playwright pada emulasi `reduced-motion` agar kebijakan gerak ikut terjaga oleh CI.

Jangan mengubah status menjadi GO hanya berdasarkan pemeriksaan statis.
