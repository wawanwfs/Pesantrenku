# Arsitektur Pesantenku

## Backend

Backend memakai Laravel 13 dengan Sanctum untuk token mobile dan session web. `DashboardService` menyediakan agregasi data untuk web panel dan mobile. CRUD resource utama ditangani oleh `CrudController` dengan allow-list resource agar endpoint tetap terkendali dan tenant-scoped.

Lapisan utama:

- Auth: `AuthController`, `LoginRequest`, Sanctum token.
- Authorization: `roles`, `role_user`, `pesantren_user`, `TenantContext`, `RoleMiddleware`, serta permission matrix tenant yang memakai allow-list resource.
- SaaS tenant: Super Admin mengelola platform, sedangkan Owner/Keuangan/Akademik/TU/mobile role divalidasi lewat membership pesantren aktif.
- Domain data: migration `2026_07_06_000001_create_pesantenku_domain_tables.php`.
- Dashboard: `DashboardController` + `DashboardService`.
- Web: Inertia React di `resources/js/Pages`.
- Audit/notification/backup: tabel `audit_logs`, `notification_logs`, `backups`.

## Web Panel

Web panel menggunakan layout reusable:

- sidebar role-based
- topbar global search/tahun ajaran/notifikasi/user
- stat cards
- chart cards Recharts
- table preview dan module table
- form CRUD dinamis berbasis schema server dengan validasi field allowlist

Panel yang tersedia: Super Admin platform, Owner Pesantren, Keuangan, Akademik, dan Tata Usaha.

Topbar menampilkan switcher pesantren untuk user multi-tenant. `TenantContext` memakai session web atau header API `X-Pesantren-Id` untuk menentukan pesantren aktif.

## Mobile

Flutter memakai struktur:

- `lib/core/theme`
- `lib/core/network`
- `lib/core/widgets`
- `lib/features/dashboard`

Mobile melakukan autentikasi nyata ke API Laravel, menyimpan token dan tenant aktif di secure storage, lalu mengarahkan ke shell sesuai membership role tenant aktif. Kegagalan jaringan atau autentikasi ditampilkan sebagai error; aplikasi tidak membuat sesi palsu dan tidak memakai fallback data seed lokal.

## Data Seed

Seeder membuat akun demo, dua pesantren (`Pondok Pesantren Darul Hikmah` dan `Pesantren Al-Falah`), paket SaaS, subscription, tenant modules, membership role, tahun ajaran, kelas, guru/staf, santri, kamar, nilai, raport, tagihan, pembayaran, bank, pengumuman, pelanggaran, izin, obat, dokter, antrian klinik, surat, alumni, audit log, dan notification log.

## Production UI boundaries

The web navigation only exposes application capabilities backed by a real domain resource or service. Infrastructure operations such as application deployment, cache flushing, and platform secret/API integration management are intentionally excluded from tenant-facing CRUD screens. They must be performed through the deployment platform, secret manager, and audited operations runbooks. Batch payment and academic-calendar menus are also hidden until dedicated domain workflows exist; unrelated tables are never reused under misleading labels.


## Security Boundaries

- Role efektif selalu berasal dari membership tenant aktif melalui `TenantContext`.
- `ResourceCatalog` memisahkan resource tenant delegable, resource platform, service-managed, dan workflow-managed.
- Mutasi sensitif menggunakan `Idempotency-Key` yang terscope user, tenant, method, route, payload, dan fingerprint file.
- Import antrean hanya memproses baris yang telah divalidasi dan ditandatangani HMAC. Insert satu chunk dan pembaruan progress berada dalam transaksi yang sama, serta distributed lock mencegah eksekusi paralel.
- Queue memakai protokol bernama dan berversi (`backups-v1`, `imports-v3`, `exports-v3`) agar worker lama tidak mengonsumsi payload baru saat rolling deployment.
- Scheduler memulihkan job import/export yang kehilangan heartbeat dengan recovery count terbatas; export mematuhi hard row limit meskipun pagination berjalan bertahap.
- Backup tenant menggunakan manifest dependency-safe, checksum per tabel, envelope encryption, approval berlapis, dan restore dengan foreign key tetap aktif.
