# Pesantrenku — Knowledge Base Repo (memory induk)

Ditulis: 4 Oktober 2026 (sesi pembelajaran repo + pengembangan UI/UX).
Tujuan: satu berkas yang cukup untuk membuat agen atau pengembang baru produktif tanpa
membaca ulang seluruh repo. Berkas `memory/*` lain tetap berlaku sebagai catatan historis;
berkas ini merangkum dan menautkannya. Status rilis produksi **tetap NO-GO** (lihat bagian 10).

## 1. Apa produknya

Pesantrenku (kode lama menulis `pesantenku`/`Pesantenku` di banyak identifier, jangan
"diperbaiki" tanpa migrasi) adalah SaaS multi-tenant untuk pengelolaan pesantren.
Satu platform, banyak pesantren (tenant). Tiga permukaan:

| Permukaan | Teknologi | Lokasi |
|---|---|---|
| API + panel web | Laravel 13, PHP ^8.4.1, Sanctum, Inertia 3 + React 19, Tailwind 4, Vite 8 | `backend/` |
| Aplikasi mobile multi-role | Flutter (Dart ^3.10), dio, flutter_secure_storage, mobile_scanner, qr_flutter | `mobile/` |
| Infra/ops | Docker Compose, Caddy, Prometheus/Loki/Tempo/Grafana, k8s canary, PITR | `deploy/`, `docker-compose*.yml` |

Bahasa antarmuka dan dokumen: Indonesia. Komentar kode campuran, istilah domain dalam Indonesia.

## 2. Peta repo

```
backend/app/Http/Controllers   CrudController (generik, ~930 baris, pecah = backlog 87), ActionController
                               (workflow, notif, job, QR, backup), AuthController, MfaController, WebController,
                               DashboardController, Concerns/* (Crud*/Action* trait)
backend/app/Services           TenantContext, ResourceAuthorization, EntitlementService, DashboardService,
                               QrScannerService, Backup*/Restore*, StepUpMfaService, TotpService, SubscriptionProvisioner,
                               OperationalScope, OperationJobRecoveryService, UserAccessRevoker, Telemetry/Metric*
backend/app/Support            ResourceCatalog, DashboardModuleRegistry, PrivilegedAccess, TimelineRedactor,
                               ImportRowScope, SignedImportRow, SafeSpreadsheet, TenantIntegrityCatalog, QueueNames
backend/app/Jobs               ProcessResourceImportJob, BuildResourceExportJob, Create/RestoreTenantBackupJob, CanaryRoundTripJob
backend/routes                 web.php (panel + web-actions + web-api), api.php (/api/v1 untuk mobile), console.php (scheduler)
backend/resources/js           app.jsx (entry Inertia), Pages/{App,Landing,Login}.jsx, components/*, lib/*
backend/resources/css          app.css (token+shell), motion.css, ui-kit.css, landing*.css, dark.css, shell.css, login.css, landing-extras.css (sesi 2026-10-04)
backend/tests                  Feature/Unit/Integration (PHPUnit), e2e (Playwright, butuh server+DB)
backend/scripts                lint CSP, anggaran performa, OpenAPI, hardening, e2e prep
docs/                          architecture, openapi.yaml, production-deployment, disaster-recovery, backlog (232 butir)
memory/                        handoff audit + catatan sesi (versi-kan; hanya *.tmp yang diabaikan)
```

## 3. Model multi-tenant dan otorisasi (inti bisnis)

- **Tenant = `pesantrens`**. Hampir semua tabel domain punya `pesantren_id`. `TenantContext`
  adalah satu-satunya pembaca `X-Pesantren-Id`/`pesantren_id`/session `active_pesantren_id`
  (method privat `requestedPesantrenId`). Semua konsumen memanggil `activePesantrenId()` /
  `requireActivePesantrenId()` yang memvalidasi keanggotaan (kecuali super admin, yang memilih
  tenant eksplisit). Jangan membaca header tenant di tempat lain.
- **Role efektif = membership `pesantren_user`** pada tenant aktif, bukan role global.
  Role tenant bawaan: `owner_pesantren`, `admin_keuangan`, `admin_akademik`, `admin_tu`, `santri`,
  `wali_murid`, `guru`, `musyrif`, `klinik`; global: `super_admin`. Custom role tenant ada
  tetapi hanya bisa mendelegasikan resource di luar `ResourceCatalog::NON_DELEGABLE`.
- **`ResourceCatalog`** adalah allow-list resource CRUD generik (`ALL`, 55 resource), peta
  akses bawaan per role (`DEFAULT_ROLE_ACCESS`: read/write/actions), `SERVICE_MANAGED`
  (roles, plans, subscriptions, notifications, audit-logs, backups), `NON_DELEGABLE`,
  `WORKFLOW` (permits, payments, medical-queues) dan `WORKFLOW_ACTION_ROLES`.
- **`DashboardModuleRegistry`**: URL `/{panel}/module/{module}` adalah batas otorisasi,
  modul harus terdaftar eksplisit per panel (super-admin, owner, keuangan, akademik, tu,
  santri, wali-murid, guru, musyrif, klinik).
- **`EntitlementService`**: subscription aktif + modul tenant aktif (`akademik`, `keuangan`,
  `tata_usaha`, `asrama`, `klinik`) dipetakan per resource (`RESOURCE_MODULE`); kuota
  `max_students`/`max_users` ditegakkan di dalam transaksi tulis (`assertWriteQuota`, HTTP 402/409).
  Resource control-plane dikecualikan dari cek modul.
- **Cakupan operasional** (`OperationalScope`, `DashboardScopeConcern`): santri/wali hanya
  melihat santri sendiri/anak (via `students.user_id` / `guardians.user_id`); musyrif hanya
  santri di kamar aktif dari `musyrif_room_assignments` ∩ `room_assignments` (bertanggal).
  Import/export antrean memeriksa ulang scope per halaman/chunk.
- **Keamanan berlapis**: `Idempotency-Key` (8–128 char, wajib di produksi) terscope
  user+tenant+method+route+payload; step-up MFA (`StepUpMfaService`, per purpose) untuk
  mutasi akses (users, memberships, backup/restore); `PrivilegedAccess` menandai akun
  privileged (owner, super admin, custom role dengan tulis sensitif) → audit `privileged_reasons`;
  HMAC pada baris import (`SignedImportRow`); backup tenant terenkripsi envelope (`BackupCipher`)
  dengan approval berlapis (`RestoreApprovalService`); CSP ketat tanpa inline.

## 4. Workflow domain (state machine di `ActionWorkflowConcern`)

`POST /web-actions/workflow/{resource}/{id}/{transition}` (dan `/api/v1/workflow/...`):

| Resource | Transisi | Catatan |
|---|---|---|
| `permits` (perizinan santri) | approve, reject, cancel | role: musyrif, admin_tu, owner (cancel juga santri/wali); musyrif dibatasi santri binaan |
| `payments` | verify, reject | role: admin_keuangan, owner. `verify` mengunci invoice, menolak jika total terbayar > nominal, set invoice `paid`/`unpaid` (aritmetika sen, bukan float) |
| `medical-queues` | start, finish, cancel | role: klinik |

Aturan umum: `reject`/`cancel` wajib catatan (422); `lockForUpdate` + update bersyarat status
lama (409 bila berubah); audit `audit_logs` dengan before/after; `permits`/`payments`
mengirim `notification_logs` + event `NotificationChanged` ke santri dan wali.

Alur uang: `invoices` (+`invoice_items`, `discounts`) → `payments` (pending → success/failed)
→ `cashflow_entries`/laporan. Alur akademik: `academic_years` → `programs` → `classes` →
`teacher_assignments`/`schedules` → `attendances`/`grades` → `report_cards`; tahfidz di
`memorization_records`. Asrama: `rooms`, `room_assignments`, `dorm_attendances`, `violations`,
`permits`, `daily_notes`, `room_mutations`. Klinik: `medical_queues` → `medical_examinations`
→ `medical_records`/`prescriptions`/`sick_letters`/`referrals`, stok obat di `medicine_stocks`.

## 5. QR (presensi/identitas)

`qr_tokens` menyimpan hash SHA-256 kode 64 karakter, berumur singkat dan sekali pakai.
Pemindai harus terdaftar (`qr_scanner_devices`, secret terenkripsi), meminta challenge
(`qr_scan_challenges`), lalu memindai dengan bukti HMAC; ada pembatasan jaringan dan role per
`purpose` (`attendance` untuk santri, `identity`). Web hanya menghasilkan QR; pemindaian ada di mobile.

## 6. Operasi asinkron

Queue bernama dan berversi (`backups-v1`, `imports-v3`, `exports-v3`). Import dipratinjau lalu
diantrekan di atas ambang 500 baris (chunk 250, atomik, idempoten, distributed lock); export
sinkron ≤1000 baris, antrean ≤10.000. Backup tenant: manifest dependency-safe, checksum per
tabel, batas 25.000 baris/50 MB. Scheduler memulihkan job yang kehilangan heartbeat
(`OperationJobRecoveryService`, maksimum 3 percobaan). Notifikasi: polling 20 dtk + SSE
(`/web-actions/notifications/stream`) + Reverb opsional (`websocket_enabled`).

## 7. Frontend web (yang paling sering diubah)

- Entry `resources/js/app.jsx`: `createInertiaApp` dengan tiga halaman lazy: `App`, `Landing`, `Login`.
- `Pages/App.jsx` (263 baris sejak 2026-10-04) kini hanya merakit shell; isinya dipecah ke `components/app/`. Sebelumnya (~1.7k baris) memuat seluruh shell: sidebar role-based (`panels` di
  `components/app/appSupport.jsx`), `Topbar` (pencarian menu, switcher tenant, notifikasi, akun),
  `Dashboard`/`RoleDashboard`, `CrudModule` (tabel+filter+import/export+modal), `SystemModule`,
  `QrTool`. Dialog dan modal besar ada di `components/app/AppDialogs.jsx`.
- Chart native SVG (`components/NativeCharts.jsx`), tanpa Recharts. Ikon: `lib/deps.jsx`
  (re-ekspor lucide terpilih; tambah ikon baru di sana).
- Gaya: Tailwind 4 `@theme` untuk token (`--color-primary-*`, `--color-neutral-*`), `app.css`
  untuk komponen shell, `motion.css` (reveal, hover, dialog, skeleton), `ui-kit.css`
  (`pk-badge`, `pk-meter`, `pk-track`, `pk-figure`), `landing*.css`.
- Merek: primary `#126D4A`; font Poppins (`--font-sans`); radius 8px.
- Sistem desain lengkap (token, tema gelap, gerak, komponen, jebakan CSS): `docs/ui/design-system.md`.

### Kendala keras (dijaga gate; jangan dilanggar)

1. **CSP**: dilarang `style=`/`style={{}}`, `<style>`, `.style.x =`, `setAttribute('style')`,
   `createElement('style')`, `cssText` di `resources/js/**` dan `app.blade.php`
   (`npm run lint:csp`). Skrip inline juga diblokir CSP → skrip kecil harus berkas eksternal
   (`public/*.js`). Nilai dinamis → kelas CSS bertingkat atau CSS custom property lewat `className` (bukan style).
2. **Anggaran aset** (`npm run check:performance-budget`): total 1.500.000 B, JS per berkas
   550.000 B, CSS per berkas 250.000 B. Baseline sesi ini: 1.294.894 B (LandingThreeRuntime 535 KB
   memakan sebagian besar).
3. **Tanpa dependensi UI berat**: tidak boleh shadcn/MUI/Radix/`motion`/recharts/confetti,
   tidak boleh entry Vite kedua atau konfigurasi pnpm (lihat `docs/ui/figma-prototype-adoption.md`).
4. **Konten jujur**: tidak ada testimoni palsu, angka SLA (`99.9%`), persentase kesiapan,
   atau klaim "production-ready" di halaman publik.
5. **Reduced motion**: semua gerak baru wajib mati pada `prefers-reduced-motion: reduce`.
6. **Progressive enhancement**: keadaan dasar elemen selalu terlihat (tanpa animasi tetap utuh).

## 8. Cara menjalankan dan memverifikasi

```bash
cd backend && npm ci && npm run build            # aset
npm run lint:csp && npm run check:performance-budget
php artisan test                                  # butuh PHP 8.4 (composer.json ^8.4.1)
npm run test:e2e                                  # butuh server + DB + Chromium
```

Catatan lingkungan sesi cloud ini: PHP 8.3 saja (symfony 8 memakai property hooks → parse
error), jadi **backend tidak bisa dijalankan**. Frontend diverifikasi lewat build Vite,
lint CSP, anggaran aset, dan harness statis `backend/scripts/ui-preview/` (Playwright +
props Inertia tiruan) — lihat handoff sesi ini.

## 9. Mobile

`mobile/lib`: `main.dart` (restore sesi dari secure storage, penanganan 401), `core/network/api_client.dart`
(dio, token, tenant aktif), `core/theme/app_theme.dart`, `core/widgets/app_widgets.dart`,
`features/dashboard/{mobile_shell,mobile_data}.dart` (dashboard per role, resource list, QR generator
& scanner, notifikasi, akun). Build rilis hanya HTTPS. Belum pernah dijalankan di sesi ini (tanpa Flutter SDK).

## 10. Status dan utang (jangan ubah menjadi GO tanpa bukti)

Terbuka: M-REMAIN-04 (bukti kanari worker/scheduler/Reverb), pemecahan `App.jsx` dan
`CrudController`, gate e2e/MySQL 8.4/Flutter belum berjalan. Backlog lengkap: `docs/backlog/improvement-backlog.md`
(232 butir, item 159 mode gelap dan 143 pemecahan App.jsx adalah yang disentuh sesi ini).
Handoff terkait: `2026-07-27-rc9-remediation-handoff.md`, `2026-07-27-ui-motion-handoff.md`,
`2026-07-27-figma-prototype-study.md`, audit RC6–RC8 (`*.json`), dan
`2026-10-04-ui-ux-expansion-handoff.md` (sesi ini).

## 11. Akun demo (hanya local/test)

Seeder membuat dua tenant (Pondok Pesantren Darul Hikmah, Pesantren Al-Falah), password `password`,
akun `superadmin@/owner@/keuangan@/akademik@/tu@/santri@/wali@/guru@/musyrif@/klinik@pesantenku.test`.
Jangan pernah menjalankan seeder di produksi.

## 12. Aturan kerja yang terbukti berguna

- Verifikasi sebelum klaim: bedakan "dibaca/dibangun" dari "dijalankan". Catat apa yang tidak sempat diuji.
- Ubah satu hal per commit; sebutkan gate yang dijalankan di pesan commit.
- Perubahan UI: build + lint:csp + budget wajib hijau sebelum push.
- Jangan menambah resource ke `ResourceCatalog` tanpa memperbarui OpenAPI dan tes parity.
