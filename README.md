# Pesantenku

Monorepo Pesantenku berisi backend Laravel 13 dan aplikasi mobile Flutter multi-role untuk manajemen pesantren berbasis SaaS: Super Admin platform, Owner Pesantren, Keuangan, Akademik, Tata Usaha, Santri, Wali Murid, Musyrif, Guru, dan Klinik.

## Struktur

- `backend/` Laravel 13 API + web panel Inertia React.
- `mobile/` Flutter stable multi-role app.
- `docs/` dokumentasi arsitektur dan OpenAPI.
- `docs/production-deployment.md` runbook deployment, queue, Redis, WebSocket, backup, dan rollback.
- `docker-compose.yml` layanan database, redis, mailhog, dan app PHP.
- `docker-compose.production.yml` deployment publik melalui Caddy dengan SSL otomatis untuk `pesantrenku.ai` dan subdomain service.
- `docker-compose.local-domain.yml` development HTTPS tanpa port melalui `pesantrenku.localhost`; lihat `docs/local-domain.md`.

## Menjalankan Backend

```bash
cd backend
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Web panel tersedia di `http://127.0.0.1:8000`.

Alternatif Docker (MySQL, Redis, Reverb, queue worker, dan MailHog):

```bash
docker compose build
docker compose run --rm app php artisan migrate:fresh --seed --force
docker compose up -d
```

Setelah container sehat, panel tersedia di `http://localhost:8000` dan MailHog di `http://localhost:8025`.

Untuk mode development frontend:

```bash
cd backend
npm run dev -- --host 127.0.0.1 --port 5173
```

Catatan: jalankan `php artisan serve --host=127.0.0.1 --port=8000` dan Vite dev server bersamaan saat memakai mode development.

## Menjalankan Mobile

```bash
cd mobile
flutter pub get
flutter run
```

Android emulator memakai base URL `http://10.0.2.2:8000/api/v1`. Jika menjalankan di device fisik, ubah `baseUrl` di `mobile/lib/core/network/api_client.dart`.

Catatan Windows: plugin Flutter seperti `mobile_scanner` memerlukan Developer Mode karena symlink.

## Akun Demo

Semua password: `password`.

- `superadmin@pesantenku.test`
- `owner@pesantenku.test`
- `keuangan@pesantenku.test`
- `akademik@pesantenku.test`
- `tu@pesantenku.test`
- `santri@pesantenku.test`
- `wali@pesantenku.test`
- `guru@pesantenku.test`
- `musyrif@pesantenku.test`
- `klinik@pesantenku.test`

Seed membuat dua tenant:

- `Pondok Pesantren Darul Hikmah`
- `Pesantren Al-Falah`

`owner@pesantenku.test` punya akses ke keduanya dan bisa mengganti pesantren aktif dari topbar. Role operasional seperti Keuangan, Akademik, TU, Santri, Wali, Guru, Musyrif, dan Klinik hanya membaca data sesuai membership pesantrennya.

## Endpoint Utama

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/tenant/switch`
- `GET /api/v1/dashboard/super-admin`
- `GET /api/v1/dashboard/owner`
- `GET /api/v1/dashboard/keuangan`
- `GET /api/v1/dashboard/akademik`
- `GET /api/v1/dashboard/tu`
- `GET /api/v1/dashboard/santri`
- `GET /api/v1/dashboard/wali-murid`
- `GET /api/v1/dashboard/musyrif`
- `GET /api/v1/dashboard/guru`
- `GET /api/v1/dashboard/klinik`

CRUD generik tersedia untuk resource utama, misalnya `students`, `classes`, `subjects`, `invoices`, `payments`, `rooms`, `medical-records`, `announcements`, dengan format:

- `GET /api/v1/{resource}`
- `POST /api/v1/{resource}`
- `GET /api/v1/{resource}/{id}`
- `PUT/PATCH /api/v1/{resource}/{id}`
- `DELETE /api/v1/{resource}/{id}`
- `GET /api/v1/{resource}/export`
- `POST /api/v1/{resource}/import`

Untuk API tenant, kirim header `X-Pesantren-Id` jika user punya lebih dari satu membership. Jika header kosong, backend memakai membership aktif/default. User tanpa membership ke tenant tersebut akan menerima `403`.

## Verifikasi

```bash
cd backend
php artisan test
npm run build

cd ../mobile
flutter analyze
flutter test
```

## Cakupan Saat Ini

Implementasi ini berisi fondasi production-ready: Laravel 13, Sanctum, role-based user, tenant membership `pesantren_user`, migration dan seed data multi-pesantren, dashboard API tenant-scoped, CRUD generik tenant-scoped, audit/notification/backup dummy, web panel Inertia React responsive, Flutter multi-role dengan API login dan fallback offline, OpenAPI, dan test isolasi tenant. Fitur kompleks seperti workflow approval detail, PDF raport penuh, dan scanner kamera real sudah disiapkan struktur dan dependency-nya untuk pengembangan lanjutan.
