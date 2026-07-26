# Pesantrenku Production Candidate 2026-07-26

## Status

Kandidat ini melanjutkan baseline hardening 25 Juli 2026. Perubahan menutup risiko inline style pada CSP produksi, membuat validasi OpenAPI reproducible, dan mengurangi beban chart frontend. Kandidat tetap harus melewati seluruh job CI dan staging sebelum dipromosikan.

## Perubahan

### Kontrak API

- OpenAPI dinaikkan ke versi `1.2.0`.
- Seluruh 45 operasi memiliki `operationId` unik dan minimal satu respons 4xx.
- Seluruh tag memiliki deskripsi dan metadata lisensi tersedia.
- Redocly CLI dipin pada `2.40.0`; telemetry dimatikan melalui konfigurasi repository.
- Lint kontrak dijalankan oleh workflow CI dan release tanpa instalasi `npx` ad-hoc.

### CSP dan browser security

- `style-src 'unsafe-inline'` dihapus dari CSP production/testing dan hanya dipertahankan pada local development untuk Vite.
- Recharts diganti dengan grafik SVG native yang responsif dan dapat dibaca assistive technology.
- Progress bar dinamis memakai elemen HTML `<progress>` dan stylesheet terkompilasi.
- Preview warna template PDF memakai atribut SVG, bukan inline CSS.
- Inertia runtime progress dimatikan; dialog mengunci scroll melalui class.
- Three.js memakai canvas yang telah dirender React sehingga tidak membuat inline style.
- Web Permissions Policy menonaktifkan kamera, mikrofon, dan geolokasi.
- `npm run lint:csp` memblokir regresi inline style pada source JavaScript.

### Frontend dependency

- Dependensi `recharts` dan 37 paket transitive dihapus.
- Build produksi menghasilkan modul chart native sebesar sekitar 6,1 kB minified.
- Realtime tetap dibatasi ke transport WebSocket `ws`/`wss`.

## Verifikasi lokal

| Gate | Hasil |
| --- | --- |
| `npm ci --ignore-scripts` | Lulus |
| `npm run lint:csp` | Lulus |
| `npm run build` | Lulus |
| `npm audit --omit=dev --audit-level=high` | 0 vulnerability |
| `npm audit --audit-level=high` | 0 vulnerability |
| OpenAPI structural validation | 45 operasi; 0 ID hilang/duplikat; 0 respons 4xx hilang; 0 tag tanpa deskripsi |
| Source inline-style scan | 0 temuan |
| `git diff --check` | Lulus |

PHP, MySQL integration, Playwright, Flutter, Docker image, Trivy, canary, dan restore drill tetap menjadi mandatory remote/staging gates. Runtime lokal pembuatan kandidat ini tidak menyediakan PHP, Docker, atau Flutter.

## Keputusan rilis

Jangan deploy langsung dari archive ini. Promosikan hanya commit yang sama setelah job `test`, `mysql-integration`, `e2e`, `mobile`, build/sign image, dan canary smoke seluruhnya hijau.
