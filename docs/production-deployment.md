# Deployment Production Pesantenku

## Arsitektur Minimum

- Caddy sebagai reverse proxy publik pada port `80/443` dan penerbit sertifikat TLS otomatis.
- Dua atau lebih instance web Laravel di belakang reverse proxy/load balancer.
- MySQL 8 dengan binary log `ROW` untuk point-in-time recovery.
- Redis untuk cache, session, queue, rate limit, dan Reverb scaling.
- Worker queue terpisah untuk `default`, `imports`, dan `exports`.
- Scheduler Laravel, service Reverb, S3-compatible object storage, Prometheus, dan structured log collector.

## Environment Wajib

Gunakan [`.env.production.example`](../backend/.env.production.example) sebagai baseline. Nilai minimum yang harus disediakan secret manager:

Salin menjadi `backend/.env.production` pada host Compose. `PESANTRENKU_IMAGE` harus menunjuk image immutable yang sudah dipublikasikan ke registry.
Token `PESANTRENKU_METRICS_TOKEN` dipasang oleh Compose sebagai secret file khusus Prometheus; tidak memerlukan Docker Swarm.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pesantrenku.ai
PRIMARY_DOMAIN=pesantrenku.ai
ACME_EMAIL=admin@pesantrenku.ai
LOG_CHANNEL=structured

DB_CONNECTION=mysql
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=pesantenku
REVERB_APP_KEY=change-me
REVERB_APP_SECRET=change-me
REVERB_HOST=ws.pesantrenku.ai
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SCALING_ENABLED=true

FILESYSTEM_DISK=s3
PESANTRENKU_OPERATION_DISK=s3
PESANTRENKU_METRICS_TOKEN=change-me
PESANTRENKU_REQUIRE_IDEMPOTENCY_KEY=true
```

Jangan gunakan SQLite, `APP_DEBUG=true`, credential default, atau local operation storage pada deployment multi-server.

## Domain Publik dan SSL

Siapkan record DNS berikut ke public IPv4 server production:

| Host | Type | Target |
| --- | --- | --- |
| `@` | `A` | public IPv4 server |
| `www` | `CNAME` | `pesantrenku.ai` |
| `api` | `CNAME` | `pesantrenku.ai` |
| `ws` | `CNAME` | `pesantrenku.ai` |
| `monitor` | `CNAME` | `pesantrenku.ai` |

Tambahkan record `AAAA` hanya jika server mempunyai IPv6 publik yang benar. Buka inbound TCP `80` dan TCP/UDP `443`; jangan buka `8000`, `8080`, `3000`, `3200`, `4317`, `4318`, `6379`, `9090`, atau `3306` ke internet. Jika server berada di balik NAT, forward `80/443` ke host Docker. Jika koneksi memakai CGNAT, gunakan VPS/public load balancer atau tunnel yang dikelola sebelum launch.

Caddy pada `docker-compose.production.yml` menerbitkan dan memperbarui sertifikat Let's Encrypt otomatis, serta menyediakan:

- `https://pesantrenku.ai` untuk web utama.
- `https://api.pesantrenku.ai` untuk API publik berbasis token.
- `wss://ws.pesantrenku.ai` untuk Reverb WebSocket.
- `https://monitor.pesantrenku.ai` untuk Grafana yang tetap membutuhkan login.
- `https://www.pesantrenku.ai` redirect permanen ke domain utama.

MySQL, Redis, Prometheus, Tempo, dan OpenTelemetry Collector hanya berada pada network internal Docker. Akses operasionalnya melalui VPN, SSH tunnel, atau Grafana; jangan menambahkan subdomain publik langsung untuk service tersebut.

Launch dari root repository:

```bash
cp backend/.env.production.example backend/.env.production
# Isi seluruh secret dan gunakan image immutable, idealnya image@sha256:digest.
chmod +x deploy/scripts/production-up.sh
./deploy/scripts/production-up.sh
```

Jika memakai Cloudflare, gunakan SSL mode `Full (strict)`, aktifkan WebSocket, dan jangan mengubah origin port dari `443`.

## Build dan Release

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan reverb:restart
```

Frontend memisahkan halaman, grafik, Laravel Echo, dan Pusher ke chunk terpisah. Build artifact harus dibuat sekali dan digunakan oleh semua instance web.

## Worker, Scheduler, dan Reverb

```bash
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=120
php artisan queue:work redis --queue=imports --sleep=1 --tries=3 --timeout=900
php artisan queue:work redis --queue=exports --sleep=1 --tries=3 --timeout=900
php artisan schedule:work
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Reverb mengirim `notifications.changed` melalui private channel `tenant.{tenantId}.user.{userId}` dan autentikasi `/broadcasting/auth`. Aktifkan `REVERB_SCALING_ENABLED=true` agar beberapa node Reverb berbagi event melalui Redis. Reverse proxy harus meneruskan WebSocket upgrade dan memiliki idle timeout yang memadai.

## Object Storage

Semua temporary import, chunk report, export, backup, signed download, cleanup, dan DR report menggunakan `PESANTRENKU_OPERATION_DISK`. Bucket wajib private, versioning dan server-side encryption aktif, serta memiliki lifecycle rule untuk prefix `imports/`, `exports/`, `backups/`, dan `dr-drills/`.

Untuk MinIO atau provider S3-compatible, isi `AWS_ENDPOINT` dan aktifkan `AWS_USE_PATH_STYLE_ENDPOINT=true` bila diperlukan. Signed URL mengikuti `PESANTRENKU_SIGNED_URL_MINUTES`.

## Metrics dan Tracing

- Liveness: `GET /up`.
- Readiness: `GET /health`.
- Prometheus exposition: `GET /metrics` dengan bearer token `PESANTRENKU_METRICS_TOKEN`.
- Response menyertakan `X-Correlation-ID` dan W3C `traceparent`.
- Structured JSON log memuat route, status, latency, user, tenant, query count, dan database duration.
- Metrics mencakup HTTP request, latency, query database, serta queue started/completed/exception/failed.

Contoh scrape Prometheus:

```yaml
scrape_configs:
  - job_name: pesantenku
    metrics_path: /metrics
    authorization:
      type: Bearer
      credentials_file: /run/secrets/pesantenku_metrics_token
    static_configs:
      - targets: ['app:8000']
```

Forward `stderr` ke OpenTelemetry Collector, Loki, OpenSearch, atau provider observability lain. Index `correlation_id` dan `trace_id` untuk penelusuran insiden.

## Idempotency

Workflow, pembayaran, dan restore mendukung `Idempotency-Key`. Production mewajibkan key pada operasi kritis dengan `PESANTRENKU_REQUIRE_IDEMPOTENCY_KEY=true`.

- Key yang sama dan payload identik mengembalikan response tersimpan dengan `Idempotency-Replayed: true`.
- Key yang sama dengan payload berbeda ditolak `409`.
- Request kritis tanpa key ditolak `428`.
- Record idempotency kedaluwarsa dibersihkan scheduler setiap jam.

## Backup dan Disaster Recovery

- Backup tenant dibuat dan dipulihkan dari UI dengan scope tenant.
- Download object storage menggunakan signed URL berumur pendek.
- Restore membutuhkan konfirmasi `RESTORE`.
- Jalankan `php artisan pesantenku:dr-drill --backup-id=<id>` untuk memverifikasi checksum, umur backup, tenant scope, object storage, dan kesiapan binary log.
- Ikuti [disaster-recovery.md](disaster-recovery.md) untuk full database restore dan MySQL point-in-time recovery.

## CI/CD

Workflow [ci.yml](../.github/workflows/ci.yml) menjalankan backend test, frontend build, Composer audit, npm audit, Playwright desktop/mobile, dan deployment smoke test. Set repository variable `DEPLOYMENT_URL` untuk smoke test manual.

Pipeline deployment harus memakai immutable image digest, menjalankan migrasi satu kali, me-restart worker dan Reverb, lalu memverifikasi health, metrics, login, workflow idempotent, queue, dan notifikasi realtime.

## Rollout dan Rollback

1. Verifikasi backup, DR drill, dan migrasi di staging.
2. Deploy instance web baru secara bertahap.
3. Jalankan migrasi kompatibel ke belakang.
4. Restart queue dan Reverb setelah semua web instance siap.
5. Audit `/health`, `/metrics`, login, queue monitor, download manager, signed download, dan workflow tenant.
6. Rollback image bila error rate naik; jangan rollback migration destruktif tanpa prosedur data khusus.

## Verifikasi Release

```bash
php artisan test
npm run build
npm run test:e2e
composer audit --locked
npm audit --audit-level=high
curl -fsS https://pesantrenku.ai/health
curl -fsS https://api.pesantrenku.ai/up
curl -fsS https://monitor.pesantrenku.ai/api/health
curl -fsS -H "Authorization: Bearer $PESANTRENKU_METRICS_TOKEN" https://pesantrenku.ai/metrics
```
