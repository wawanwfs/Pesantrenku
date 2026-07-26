# Pesantrenku Backend

Laravel 13 backend untuk API, auth Sanctum, dan web panel Inertia React.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Mode development frontend:

```bash
npm run dev -- --host 127.0.0.1 --port 5173
```

## SaaS Tenant

- Super Admin mengelola platform.
- Owner Pesantren mengelola tenant dan bisa switch pesantren.
- Role operasional divalidasi lewat `pesantren_user`.
- API multi-tenant memakai header `X-Pesantren-Id` untuk memilih pesantren aktif.

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

## Verifikasi

```bash
vendor/bin/pint
php artisan test
npm run build
```
