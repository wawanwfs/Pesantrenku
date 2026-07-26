# Adopsi Prototipe Figma Make ke Aplikasi

Dokumen ini mencatat keputusan teknis atas bundle prototipe `Pesantrenku.zip`
(sha256 `1cd7f5c8516f78ddd0866f7b287ef47cd400c60175487af29b41a6a8ef8bc5fa`,
78 file, sumber `figma.com/design/VbngqT7ai7UMAd2royZ8Cg/Pesantrenku`).

Prototipe adalah aplikasi Vite + React 18 mandiri dengan shadcn/Radix (48 komponen),
MUI, `motion` 12, `recharts`, `canvas-confetti`, dan konfigurasi pnpm. Ia **tidak**
diintegrasikan apa adanya karena stack produksi kita adalah Laravel + Inertia React 19
dengan gate CSP, budget aset, dan aturan konten yang lebih ketat.

## Diadopsi

Ditulis ulang sebagai CSS murni di `backend/resources/css/ui-kit.css`:

| Ide dari prototipe | Implementasi kita |
|---|---|
| Badge status `Selesai / Sebagian / Belum` | `.pk-badge` + varian `success/warning/danger/info/neutral` |
| Hairline gradien pemisah section | `.pk-hairline` |
| Sapuan glow radial di bawah section | `.pk-glow-sweep` |
| Glow lembut saat kartu di-hover | `.pk-card-glow` |
| Progress bar animatif | `.pk-meter` + kelas step `0..100` (transform, tanpa inline style) |
| Carousel testimoni bergulir | `.pk-track` + `.pk-track-scroll`, murni CSS, pause saat hover |
| Blok angka statistik besar | `.pk-figure*` sebagai *frame*, nilainya diisi dari data nyata |

Selain itu, animasi reveal, hover lift, press, dialog, dan skeleton sudah lebih dulu
ditangani `backend/resources/css/motion.css`.

## Tidak diadopsi, beserta alasannya

1. **Testimoni, nama tokoh, dan rating bintang.** Seluruhnya fiktif (6 testimoni,
   semuanya 5 bintang, nama pesantren rekaan). Menayangkannya di situs publik adalah
   klaim palsu.
2. **Angka `99.9% Uptime SLA`, `50+ Modul`, `10+ Peran` sebagai marketing.** Tidak ada
   perjanjian layanan maupun pengukuran yang mendukungnya.
3. **Section `AuditReport` publik** dengan matriks kesiapan dan angka `Backend 100%`,
   `Web 95%`, `Mobile 22%`. Status internal tidak layak jadi konten pemasaran, dan
   angkanya tidak terverifikasi. Status kesiapan tetap hidup di folder `memory/`.
4. **shadcn/Radix, MUI, `motion`, `recharts`, `canvas-confetti`.** Menambah ratusan
   kilobyte dan akan menembus budget aset (total 1.500.000 B, JS 550.000 B).
5. **42 pemakaian `style={{ ... }}`** pada komponen prototipe. Dilarang oleh
   `scripts/check-csp-compatibility.mjs`.
6. **Konfigurasi pnpm, `vite.config.ts` mandiri, dan `index.html` sendiri.** Bertabrakan
   dengan entry Vite Laravel yang sudah ada.
7. **Token tema shadcn (`theme.css`, `default_shadcn_theme.css`)** dengan primary
   `#030213`. Bertentangan dengan token merek kita (`--color-primary-600: #126D4A`).
8. **Toggle mode gelap.** Idenya bagus, tetapi belum dikerjakan karena butuh penulisan
   ulang palet landing secara menyeluruh plus pengujian kontras. Dicatat sebagai
   pekerjaan lanjutan, bukan ditolak.

## Cara memakai primitif baru

```jsx
<span className="pk-badge pk-badge-success">Selesai</span>

<div className="pk-meter pk-meter-step-70">
    <span />
</div>

<div className="pk-track">
    <div className="pk-track-scroll flex gap-4">{/* item, digandakan 2x */}</div>
</div>
```

Gunakan `.pk-figure-value` hanya untuk angka yang berasal dari data nyata.

## Pekerjaan lanjutan

1. Mode gelap untuk landing dan app shell, dengan audit kontras WCAG.
2. Pecah `resources/js/Pages/App.jsx` (~101 KB) agar primitif ini bisa dipakai bertahap.
3. Assertion Playwright pada emulasi `prefers-reduced-motion`.
