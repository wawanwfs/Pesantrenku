# Pesantrenku Production Release 2026-07-12

## Artifact

```text
Image: wawanwfs/pesantrenku@sha256:c9d5be195f340a75d53239a98ef680e7cd6ddfdfdf9b8803f626661dc40f85a3
Source tag: wawanwfs/pesantrenku:20260712-release
```

Production must use the digest value in `PESANTRENKU_IMAGE`. Do not deploy from `latest`.

## Required DNS

Point the following records to the production load balancer or VPS public IPv4:

| Host | Type | Target |
| --- | --- | --- |
| `@` | `A` | Production public IPv4 |
| `www` | `CNAME` | `pesantrenku.ai` |
| `api` | `CNAME` | `pesantrenku.ai` |
| `ws` | `CNAME` | `pesantrenku.ai` |
| `monitor` | `CNAME` | `pesantrenku.ai` |

Only TCP `80`, TCP `443`, and UDP `443` may be exposed publicly.

## Deploy

```bash
export PESANTRENKU_ENV_FILE=./backend/.env.production
sed -i 's|^PESANTRENKU_IMAGE=.*|PESANTRENKU_IMAGE=wawanwfs/pesantrenku@sha256:c9d5be195f340a75d53239a98ef680e7cd6ddfdfdf9b8803f626661dc40f85a3|' "$PESANTRENKU_ENV_FILE"
./deploy/scripts/production-up.sh
```

Record the previous image digest before deployment:

```bash
docker inspect --format '{{.Image}}' "$(docker compose -f docker-compose.production.yml ps -q app)" > build/previous-production-image.txt
```

## Verify

```bash
curl -fsS https://pesantrenku.ai/health
curl -fsS https://api.pesantrenku.ai/up
curl -fsS https://monitor.pesantrenku.ai/api/health
docker compose -f docker-compose.production.yml ps
docker compose -f docker-compose.production.yml exec app php artisan migrate:status
docker compose -f docker-compose.production.yml exec app php artisan queue:monitor default,imports,exports --max=100
```

Verify WebSocket upgrade against `wss://ws.pesantrenku.ai/app/<REVERB_APP_KEY>` and execute a signed object-storage download plus a tenant backup before accepting production traffic.

## Rollback

Rollback changes the image digest only. Database migrations must remain backward compatible; never run `migrate:rollback` automatically on production data.

```bash
PREVIOUS_IMAGE="wawanwfs/pesantrenku@sha256:<previous-digest>"
sed -i "s|^PESANTRENKU_IMAGE=.*|PESANTRENKU_IMAGE=${PREVIOUS_IMAGE}|" backend/.env.production
docker compose --env-file backend/.env.production -f docker-compose.production.yml pull app queue scheduler reverb
docker compose --env-file backend/.env.production -f docker-compose.production.yml up -d --no-deps app queue scheduler reverb
curl -fsS --retry 12 --retry-delay 5 https://pesantrenku.ai/health
```

If the release changes data incompatibly, restore through the approved tenant restore workflow or the documented PITR procedure instead of reversing schema migrations in place.

## External Status At Release Preparation

On 2026-07-12, public resolvers returned `NXDOMAIN` for `pesantrenku.ai` and the `.ai` registry RDAP endpoint returned `Object not found`. Public deployment cannot start until the domain is registered/delegated and VPS SSH access is available.
