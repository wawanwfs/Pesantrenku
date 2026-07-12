# Arsitektur Pesantenku

## Backend

Backend memakai Laravel 13 dengan Sanctum untuk token mobile dan session web. `DashboardService` menyediakan agregasi data untuk web panel dan mobile. CRUD resource utama ditangani oleh `CrudController` dengan allow-list resource agar endpoint tetap terkendali dan tenant-scoped.

Lapisan utama:

- Auth: `AuthController`, `LoginRequest`, Sanctum token.
- Authorization: `roles`, `role_user`, `pesantren_user`, `TenantContext`, `RoleMiddleware`, dan `GenericPolicy`.
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
- form placeholder CRUD-ready

Panel yang tersedia: Super Admin platform, Owner Pesantren, Keuangan, Akademik, dan Tata Usaha.

Topbar menampilkan switcher pesantren untuk user multi-tenant. `TenantContext` memakai session web atau header API `X-Pesantren-Id` untuk menentukan pesantren aktif.

## Mobile

Flutter memakai struktur:

- `lib/core/theme`
- `lib/core/network`
- `lib/core/widgets`
- `lib/features/dashboard`

Login demo mencoba autentikasi ke API Laravel memakai akun demo, menyimpan token dan pesantren aktif, lalu mengarahkan ke shell mobile sesuai role. App mencoba mengambil dashboard tenant dari API dan fallback ke seed lokal bila server tidak aktif.

## Data Seed

Seeder membuat akun demo, dua pesantren (`Pondok Pesantren Darul Hikmah` dan `Pesantren Al-Falah`), paket SaaS, subscription, tenant modules, membership role, tahun ajaran, kelas, guru/staf, santri, kamar, nilai, raport, tagihan, pembayaran, bank, pengumuman, pelanggaran, izin, obat, dokter, antrian klinik, surat, alumni, audit log, dan notification log.
