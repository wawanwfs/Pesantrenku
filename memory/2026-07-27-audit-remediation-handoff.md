# Handoff Audit dan Perbaikan RC7

Tanggal lokal: 27 Juli 2026  
Status rilis: **NO-GO production**

## Hasil

Audit total dilakukan paralel pada backend, keamanan/data, landing page, mobile, deployment, supply chain, OpenAPI, dan dokumentasi. Temuan deterministik yang dapat diperbaiki di container sudah diterapkan. Arsip landing page kedua tidak digabung mentah; elemen yang aman dipindahkan ke landing Inertia utama.

Riwayat memory sebelumnya tetap dipertahankan. Detail terstruktur terbaru berada di `memory/2026-07-27-total-system-audit-rc7.json`.

## Perbaikan utama

| Area | Perbaikan |
|---|---|
| Runtime Laravel | Menghapus collision `EnsureActiveUser::terminate`, memperbaiki capture trusted proxy, dan memastikan sesi web ikut dicabut pada penolakan same-origin API. |
| Tenant dan audit | Audit platform menyimpan `pesantren_id = null`; dashboard super-admin tidak mencampur notifikasi tenant. |
| Import dan queue | Quota import sinkron/queued ditegakkan, worker memeriksa ulang quota, dan chunk lanjutan didispatch setelah lock dilepas. |
| MFA dan job artifact | Step-up terikat purpose; list/status job tidak lagi mengeluarkan signed URL langsung. |
| Kontrak API | OpenAPI memuat MFA, QR scanner/challenge/proof, dan device binding; CI membandingkan 50 operasi literal route. |
| Recovery dan CI | PITR menerima dua image digest dan memvalidasi reference immutable; script CI dijalankan melalui `bash` agar aman setelah ekstraksi ZIP. |
| Supply chain mobile | Signature AAB diverifikasi dan checksum SHA-256 dipublikasikan bersama artifact. |
| Availability | Caddy tidak lagi bergantung pada Grafana sehat untuk cold start. |
| Landing page | Menambahkan Modul, 10 Peran, dan Alur Kerja tanpa klaim/testimoni palsu; mempertahankan CSP, aksesibilitas, progressive Three.js, dan budget. |
| Dependency/style | Guzzle diperbarui; Composer/npm audit bersih; 34 file baseline dirapikan dengan Pint. |

## Validasi lokal

- PHPUnit: 123 test, 122 lulus, 1 skip, 3.715 assertion.
- Landing feature: 2 test, 12 assertion.
- PHP syntax: 157 file.
- Pint, Composer validate strict, Composer audit: lulus.
- npm audit: 0 vulnerability.
- CSP: 13 file; hardening: 130/130.
- OpenAPI schema dan parity 50 route: lulus.
- Vite: 2.557 modul; performance budget 1.295.822 / 1.500.000 byte.
- YAML: 21 file; seluruh shell script lulus syntax.
- `git diff --check`: lulus.

## Yang belum boleh dianggap selesai

1. Queued import masih harus mengulang operational row-scope terhadap assignment terkini.
2. Provisioning plan/subscription production perlu workflow idempoten dan teraudit.
3. Queued export perlu strategi scope yang tidak membeku pada query awal.
4. Canary perlu membuktikan backup/import/export worker, heartbeat, scheduler, dan Reverb.
5. Kebijakan disable MFA perlu memasukkan privileged custom role.
6. Timeline historis perlu redaction pemilik sebelumnya.
7. AAB masih membutuhkan dependency verification/locking, SBOM, dan attestation khusus mobile.

## Gate eksternal

- Playwright belum berjalan karena binary Chromium tidak tersedia.
- Flutter analyze/test/AAB belum berjalan karena Flutter SDK tidak tersedia.
- Docker Compose build/runtime belum berjalan karena Compose plugin dan daemon tidak tersedia.
- MySQL 8.4, PITR, Kubernetes canary, KMS/S3/SMTP/Reverb, scheduler, worker panjang, dan observability drill wajib dibuktikan di CI/staging terhadap commit dan image digest yang sama.

Jangan ubah status menjadi GO hanya berdasarkan static checks atau SQLite test.
