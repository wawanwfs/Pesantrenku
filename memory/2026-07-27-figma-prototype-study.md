# Catatan Studi Prototipe Figma Make (27 Juli 2026)

## Objek yang dipelajari

- Bundle: `Pesantrenku.zip`, sha256 `1cd7f5c8516f78ddd0866f7b287ef47cd400c60175487af29b41a6a8ef8bc5fa`
- 78 file, 488 KB terekstrak, sumber `figma.com/design/VbngqT7ai7UMAd2royZ8Cg/Pesantrenku`
- Isi: Vite 6 + React 18 mandiri, shadcn/Radix (48 komponen `src/app/components/ui`),
  MUI 7, `motion` 12, `recharts`, `canvas-confetti`, `react-router`, konfigurasi pnpm,
  token tema shadcn, dan 10 komponen landing
  (`Navbar`, `Hero`, `Features`, `Stats`, `Roles`, `Workflow`, `AuditReport`,
  `Testimonials`, `Footer`, `ThreeCampus`) plus `ThemeContext` untuk mode gelap.

## Temuan penting

| Kode | Temuan | Tindakan |
|---|---|---|
| PROTO-01 | 6 testimoni fiktif dengan rating 5 bintang seragam | Ditolak, klaim palsu |
| PROTO-02 | Klaim `99.9% Uptime SLA` tanpa dasar pengukuran | Ditolak |
| PROTO-03 | Section `AuditReport` publik dengan angka Backend 100% / Web 95% / Mobile 22% | Ditolak untuk halaman publik; status kesiapan tetap di `memory/` |
| PROTO-04 | 42 pemakaian `style={{ ... }}` | Ditolak, dilarang gate CSP |
| PROTO-05 | shadcn + MUI + motion + recharts + confetti | Ditolak, menembus budget aset |
| PROTO-06 | Konfigurasi pnpm, `vite.config.ts`, `index.html` mandiri | Ditolak, bertabrakan dengan entry Vite Laravel |
| PROTO-07 | Token shadcn dengan primary `#030213` | Ditolak, bertentangan dengan token merek `#126D4A` |
| PROTO-08 | Primitif visual yang berguna: badge status, hairline gradien, glow sweep, glow kartu, meter animatif, deret bergulir, blok angka | Diadopsi, ditulis ulang sebagai CSS murni |
| PROTO-09 | Ide toggle mode gelap dengan token light/dark terpisah | Ditunda, perlu audit kontras menyeluruh |

## Perubahan yang masuk ke `main`

- `backend/resources/css/ui-kit.css` (baru): primitif `pk-badge`, `pk-hairline`,
  `pk-glow-sweep`, `pk-card-glow`, `pk-meter` + kelas step, `pk-track`, `pk-figure`.
  Semua opt-in, tanpa dependensi baru, tanpa inline style, menghormati reduced motion.
- `backend/resources/css/app.css`: mengimpor `ui-kit.css`.
- `docs/ui/figma-prototype-adoption.md` (baru): keputusan adopsi dan penolakan.

## Status

- Status rilis tetap **NO-GO production**. Studi ini tidak mengubah temuan RC7/RC8 yang
  masih terbuka.
- Gate belum dijalankan terhadap perubahan ini. Jalankan dari `backend/`:
  `npm run build`, `npm run check:performance-budget`, `npm run lint:csp`,
  `php artisan test`, `npm run test:e2e`, `npm run audit:ui`.
