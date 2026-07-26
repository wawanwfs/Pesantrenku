# Pesantrenku

Monorepo Pesantrenku berisi backend Laravel 13, panel web Inertia React, dan aplikasi Flutter multi-role untuk pengelolaan pesantren berbasis SaaS multi-tenant.

## Komponen

- `backend/`: API Sanctum, panel web, queue, Reverb, import/export, audit, backup tenant terenkripsi, dan workflow domain.
- `mobile/`: aplikasi Flutter dengan autentikasi API, secure storage, pemilihan tenant, pembacaan resource sesuai permission, dan pemindai QR kamera.
- `docs/`: arsitektur, kontrak OpenAPI, deployment production, observability, dan disaster recovery.
- `docker-compose.yml`: development MySQL, Redis, Reverb, worker, scheduler, dan MailHog.
- `docker-compose.production.yml`: deployment Caddy/TLS, aplikasi, worker, Reverb, observability, MySQL, dan Redis.

## Development Backend

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

Panel web tersedia di `http://127.0.0.1:8000`.

Dengan Docker:

```bash
docker compose build
docker compose run --rm app php artisan migrate:fresh --seed --force
docker compose up -d
```

## Development Mobile

Android emulator:

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Device fisik harus memakai alamat backend yang dapat dijangkau device. Build release hanya menerima HTTPS:

```bash
flutter build appbundle \
  --release \
  --dart-define=API_BASE_URL=https://api.example.id/api/v1
```

Konfigurasi signing Android dijelaskan di [`mobile/README.md`](mobile/README.md). Token sesi disimpan melalui secure storage; kegagalan autentikasi tidak membuat sesi demo atau fallback offline palsu.

## Tenant dan Otorisasi

- Role efektif diambil dari membership `pesantren_user` pada tenant aktif, bukan dari role global pengguna.
- Header `X-Pesantren-Id` memiliki prioritas tertinggi untuk request API dan hanya diterima bila pengguna memiliki membership yang sesuai; super admin dapat memilih tenant secara eksplisit.
- Custom role hanya dapat mendelegasikan resource tenant yang masuk katalog aman. Resource platform, audit, backup, role, plan, dan workflow khusus tidak dapat dibuka melalui CRUD generik.
- Workflow permit, payment, dan antrean medis memakai state transition khusus, row lock, audit, dan idempotency.
- Import/export/backup berjalan di queue berversi untuk rolling deployment; import chunk atomik dan idempoten, export memiliki hard row cap, dan scheduler memulihkan worker yang kehilangan heartbeat secara terbatas.

## API Utama

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/tenant/switch`
- `GET /api/v1/dashboard/{role}`
- `POST /api/v1/qr/generate`
- `POST /api/v1/qr/scan`
- `POST /api/v1/workflow/{resource}/{id}/{transition}`
- `GET|POST /api/v1/{resource}` untuk resource yang terdaftar dan diizinkan

Kontrak lengkap tersedia di [`docs/openapi.yaml`](docs/openapi.yaml). Mutasi yang ditandai wajib mengirim `Idempotency-Key` berukuran 8–128 karakter pada production.

## Data Demo

Seeder development membuat dua tenant dan akun dengan password `password`. Data tersebut hanya untuk local/test dan tidak boleh dijalankan pada database production.

## Quality Gates

```bash
cd backend
php artisan test
php artisan pesantenku:domain-preflight
npm run lint:openapi
npm run lint:csp
npm run build
npm run test:e2e
composer audit --locked
npm audit --audit-level=high

cd ../mobile
flutter analyze
flutter test
```

Sebelum deployment, jalankan juga:

```bash
php artisan pesantenku:production-check
```

Command tersebut memblokir konfigurasi production yang tidak aman, antara lain debug aktif, storage lokal, queue sinkron, cookie tidak aman, mailer log, origin Reverb wildcard, KMS/bucket kosong, dan idempotency opsional.

## Deployment

Gunakan [`backend/.env.production.example`](backend/.env.production.example) dan image container immutable berbentuk `repository@sha256:digest`.

```bash
cp backend/.env.production.example backend/.env.production
# Isi seluruh secret, KMS key, object storage, mail, domain, dan image digest.
bash deploy/scripts/production-up.sh
```

Runbook lengkap: [`docs/production-deployment.md`](docs/production-deployment.md). Prosedur restore dan PITR: [`docs/disaster-recovery.md`](docs/disaster-recovery.md).

## Batas Operasional

Backup dari UI adalah snapshot tenant terenkripsi dan dibatasi jumlah baris/ukuran agar tidak menghabiskan memori worker. Full database backup, binary log, point-in-time recovery, rotasi secret, monitoring, dan uji restore berkala tetap merupakan tanggung jawab lapisan infrastruktur.
