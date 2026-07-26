# Laporan Production Hardening Pesantenku

**Tanggal:** 25 Juli 2026  
**Baseline:** audit `Pesantrenku_Audit_Total_2026-07-25.md`  
**Status hasil:** **production release candidate** — seluruh temuan Critical dan High telah ditutup atau dibatasi pada lapisan kode, tetapi rilis aktual tetap wajib melewati CI lengkap, staging MySQL, konfigurasi secret/infrastruktur, uji beban, dan drill pemulihan.

## Ringkasan perubahan

Hardening mengubah fondasi otorisasi, CRUD, workflow, backup/restore, QR, import/export, mobile, dan deployment. Perubahan utama berada pada 69 entri working tree dengan lebih dari 6.000 baris penambahan serta regression test baru.

### Boundary tenant dan RBAC

- `TenantContext` menjadi sumber kebenaran tenant aktif dan role membership tenant.
- Hanya `super_admin` yang diperlakukan sebagai role global.
- ID tenant malformed, tenant nonaktif, dan membership yang tidak sesuai ditolak.
- Header tenant eksplisit memiliki precedence yang konsisten dan tidak dapat ditimpa query string.
- `ResourceCatalog` memisahkan resource service-managed, non-delegable, dan workflow.
- Custom role tenant tidak dapat memperoleh permission resource platform/global.
- Assignment custom role ditolak secara atomik bila ada membership dari tenant lain.
- Permission matrix resource global bersifat read-only bagi tenant.

### CRUD, invariant, dan workflow

- Payload create/update memakai allowlist dan menolak field yang tidak dikenal.
- Audit log, backup, role, plan, notification, dan resource service-managed lain tidak dapat dimutasi melalui CRUD generik.
- Bulk status dinonaktifkan untuk permit, payment, dan medical queue.
- Payment tidak dapat dibuat langsung dalam status final.
- Record workflow menjadi immutable setelah meninggalkan state awal, kecuali melalui transition service.
- Seluruh mutasi sensitif menggunakan `Idempotency-Key` dengan scope user, tenant, method, route, payload, dan fingerprint file.
- Lease idempotensi memiliki TTL; respons 5xx tidak disimpan sebagai replay permanen.

### Integritas database

- Ditambahkan katalog 118 foreign key dengan aksi delete eksplisit.
- Migration hardening aman terhadap retry setelah DDL parsial dan mengecek constraint/index sebelum menambah atau menghapusnya.
- Command preflight mendeteksi orphan serta konflik domain sebelum migration production.
- CI menjalankan migration dan suite hardening pada MySQL 8.4, bukan hanya SQLite.

### QR dan presensi

- Token QR disimpan dalam bentuk hash, memiliki expiry, single-use, dan diklaim menggunakan row lock.
- Purpose dibatasi dan scan menghasilkan side effect presensi kelas/asrama yang sesuai tenant.
- Status presensi QR dinormalkan ke enum domain `hadir`.
- Kode QR mentah tidak dicatat pada audit log.
- Web dan mobile mengirim idempotency key untuk scan/generate.

### Import dan export

- Batas ukuran, MIME/signature, jumlah baris, dan jumlah kolom diterapkan sebelum parsing.
- Jalur import sinkron dan queued memakai aturan validasi domain yang sama.
- Baris antrean ditandatangani HMAC agar file spool yang dimodifikasi ditolak.
- XLS biner palsu ditolak; XLSX diproses sebagai format spreadsheet nyata.
- CSV dan SpreadsheetML menetralkan formula injection.
- PDF mendukung banyak halaman dan tidak lagi dibatasi 40 baris satu halaman.
- Export besar diantrikan dan operation storage memiliki scope tenant.

### Backup dan restore

- Snapshot memakai manifest versi, checksum per tabel, enkripsi envelope, dan batas baris/ukuran.
- Snapshot MySQL dibaca dalam transaksi `REPEATABLE READ` untuk konsistensi lintas tabel.
- Backup aktif ganda untuk tenant yang sama ditolak sebelum metadata dibuat.
- Metadata, audit, dan claim dibuat atomik; object storage dibersihkan bila penyimpanan metadata gagal.
- Restore memvalidasi versi manifest, checksum, tenant, ukuran, jumlah baris, dan foreign key tanpa menonaktifkan constraint.
- Restore memerlukan quorum owner + super admin, MFA, expiry, row lock, dan claim eksekusi tunggal.
- Request restore aktif ganda untuk backup yang sama ditolak.
- Dispatch failure mengembalikan status backup semula dan menghasilkan audit failure.
- Queue backup dipisahkan dengan nama berversi untuk rolling deployment.

### Realtime dan HTTP security

- SSE melakukan polling berkelanjutan dengan heartbeat, timeout, dan disconnect handling.
- Production CSP tidak mengizinkan inline script maupun inline style; HSTS aktif hanya pada HTTPS production.
- Grafik memakai SVG native, progress dinamis memakai elemen `<progress>`, dan canvas Three.js disediakan oleh React agar tidak membuat style runtime.
- Guard `npm run lint:csp` mencegah pola inline-style kembali masuk dan dijalankan pada workflow CI serta release.
- Permissions Policy menonaktifkan kamera, mikrofon, dan geolokasi pada web; scanner kamera hanya tersedia pada aplikasi mobile.
- Allowed hosts dan trusted proxy berasal dari konfigurasi eksplisit.
- Cache response autentikasi/API diset `private, no-store`.
- Readiness memvalidasi konfigurasi Reverb, WSS, origin eksplisit, dan Redis scaling.

### Mobile Flutter

- Login demo/offline fallback dihapus dari build production.
- 401/422 tidak pernah membuka authenticated shell.
- Token disimpan dengan `flutter_secure_storage` dan session bootstrap/logout nyata.
- Base URL memakai `--dart-define=API_BASE_URL` dan release hanya menerima HTTPS.
- Tenant switch mengembalikan state semula bila server gagal.
- Scanner memakai kamera nyata, permission Android/iOS tersedia, dan QR action terhubung ke API.
- Data dashboard/detail berasal dari API; data contoh tidak diperlakukan sebagai hasil autentikasi.
- Lockfile diselaraskan dengan dependency langsung dan diverifikasi CI dengan `--enforce-lockfile`.
- CI mengompilasi APK debug; release menghasilkan Android App Bundle bertanda tangan dari secret manager.
- Toolchain dipin pada Flutter 3.44.7 dan `subosito/flutter-action` v2.23.0.

### CI/CD dan supply chain

- Release tag wajib melewati backend test, Composer validation/audit, frontend build/audit, Playwright, MySQL integration, Flutter analyze/test, dan compile mobile.
- Kontrak OpenAPI 1.2 memiliki `operationId` unik, respons 4xx, tag terdeskripsi, dan lint Redocly 2.40.0 yang dipin serta menonaktifkan telemetry.
- Pipeline memblokir regresi CSP frontend melalui source compatibility gate.
- Trivy action yang lama diganti dengan v0.36.0 dan dipin ke commit immutable.
- Dependabot dikonfigurasi untuk Composer, npm, pub, GitHub Actions, dan Docker.
- Image production harus menggunakan digest `repository@sha256:...`; tag bergerak dan placeholder ditolak.
- Gate deployment memvalidasi APP_KEY terhadap cipher, secret minimum, bucket/KMS, transport mail, Reverb, Redis, serta operation storage.
- Domain preflight dijalankan sebelum dan sesudah migration.
- Canary deployment, health check, migration, queue worker, dan rollback memiliki guard eksplisit.

## Status temuan audit awal

### Critical — 4/4 ditutup di kode

| ID | Status | Implementasi |
|---|---|---|
| C-01 | Ditutup | Role tenant berasal dari membership aktif; regression test dua tenant ditambahkan. |
| C-02 | Ditutup | Resource global/non-delegable tidak dapat dimasukkan ke custom role tenant. |
| C-03 | Ditutup | Resource service-managed dikeluarkan dari writable CRUD; audit append-only. |
| C-04 | Ditutup | Bulk workflow diblokir; semua transisi memakai state machine. |

### High — 8/8 ditutup atau dimitigasi di kode

| ID | Status | Implementasi |
|---|---|---|
| H-01 | Ditutup | Validated allowlist dan unknown-field rejection. |
| H-02 | Ditutup | Mobile typed auth errors; login gagal tetap di layar login. |
| H-03 | Dimutakhirkan | Scanner, auth, tenant switch, data API, dan action utama nyata; parity admin penuh tetap backlog produk. |
| H-04 | Ditutup | 118 FK, preflight orphan, dan gate MySQL. |
| H-05 | Ditutup | QR hashed, atomic, purpose-specific, dan menghasilkan presensi. |
| H-06 | Ditutup | Manifest konsisten, encrypted snapshot, checksum, dan guarded restore. |
| H-07 | Ditutup | Batas upload/parser, streaming/queued validation, dan XLS palsu ditolak. |
| H-08 | Ditutup | Formula neutralization, SpreadsheetML valid, dan PDF multipage. |

## Verifikasi lokal yang lulus

- PHP syntax: **123 file**.
- JavaScript/MJS syntax: **11 file**.
- Shell syntax: **3 script**.
- YAML parse: **22 file / 24 dokumen**.
- JSON parse: **29 file**.
- `git diff --check`: lulus.
- OpenAPI: **44 operasi route literal** seluruhnya terdokumentasi; total **45 operasi** termasuk template dashboard.
- Idempotency: **24 operasi** seluruhnya memiliki header serta respons 409/428 pada OpenAPI.
- Foreign key catalog: **118 relasi**, tanpa nama constraint ganda, tanpa `SET NULL` pada kolom non-nullable.
- Test inventory: **80 method PHPUnit** dan **2 deklarasi test Flutter**.
- Flutter lockfile: **6 dependency langsung / 93 package terkunci**, konsisten dengan `pubspec.yaml`.

Detail mesin tersedia pada `Pesantrenku_Production_Hardening_Verification_2026-07-25.json`.

## Verifikasi yang wajib dijalankan CI/staging

Lingkungan hardening lokal tidak memiliki Composer/vendor, Flutter/Dart, Docker, atau akses registry. Karena itu status berikut belum boleh dianggap lulus sampai workflow CI berjalan:

1. `composer install`, `composer validate`, `composer audit`, dan `php artisan test`.
2. Fresh migration, domain preflight, hardening tests, dan backup tests pada MySQL 8.4.
3. `npm ci`, `npm audit`, `npm run build`, dan Playwright E2E.
4. `flutter pub get --enforce-lockfile`, `flutter analyze`, `flutter test`, APK debug, dan signed AAB.
5. Docker image build, Trivy scan, signature verification, canary smoke, dan rollback exercise.
6. Restore drill ke database staging yang terisolasi dan perbandingan checksum manifest.

## Sisa risiko sebelum go-live

- `CrudController`, `ActionController`, `DashboardService`, dan `App.jsx` masih besar; pemecahan bounded-context tetap diperlukan untuk maintainability jangka panjang.
- Mobile sudah menjadi klien API nyata, tetapi belum menyamai seluruh workflow administrasi web.
- Docker Compose adalah baseline satu node. Production HA memerlukan load balancer, minimal dua app replica, Redis/KMS/S3/SMTP/Reverb terkelola, serta database dengan backup infrastruktur.
- GitHub Actions selain Trivy masih memakai release tags; organisasi dengan threat model tinggi sebaiknya memin semua third-party action ke full commit SHA.
- Belum ada bukti uji beban, chaos test, penetration test independen, restore drill aktual, atau observability soak test.

## Gate keputusan rilis

Rilis hanya boleh dipromosikan setelah seluruh job workflow hijau dan checklist berikut ditandatangani:

- [ ] CI backend, MySQL, frontend/E2E, dan mobile lulus pada commit yang sama.
- [ ] Image digest hasil CI telah dipindai dan diverifikasi signature-nya.
- [ ] Secret production tersimpan di secret manager dan tidak ada `.env`/keystore dalam artifact.
- [ ] Domain preflight bersih pada salinan data production.
- [ ] Canary sehat, queue worker aktif, Reverb/Redis terhubung, dan alarm tidak firing.
- [ ] Restore drill staging berhasil dengan checksum manifest sama.
- [ ] Uji beban memenuhi target latency/error-rate organisasi.
- [ ] Rollback image dan migration telah disimulasikan.
- [ ] Security review independen menyetujui boundary tenant dan restore workflow.

## Kesimpulan

Kode hasil hardening ini jauh melampaui kondisi audit awal dan menutup boundary keamanan yang memblokir rilis. Artifact harus diperlakukan sebagai **production release candidate**, bukan bukti bahwa sistem sudah aman beroperasi tanpa validasi runtime dan kesiapan infrastruktur. CI dan staging kini berfungsi sebagai mandatory gate sehingga kegagalan dependency, migration MySQL, test, build, scan, signing, atau canary akan menghentikan release sebelum production.
