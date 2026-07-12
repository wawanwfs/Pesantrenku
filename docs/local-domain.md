# Local Domain Development

Pesantrenku can run locally through HTTPS without visible application ports.

## URLs

- Web: `https://pesantrenku.localhost`
- API: `https://api.pesantrenku.localhost`
- WebSocket: `wss://ws.pesantrenku.localhost`
- Grafana: `https://monitor.pesantrenku.localhost`
- phpMyAdmin: `https://db.pesantrenku.localhost`

Only Caddy publishes host ports `80/443`. Laravel, MySQL, Redis, Reverb, queue, scheduler, Prometheus, Tempo, Collector, Grafana, and phpMyAdmin stay on the internal Compose network.

The local runtime uses MySQL and Redis named volumes, matching the production service topology and avoiding SQLite contention between the web, queue, scheduler, and Reverb containers.

## Start

```powershell
./deploy/scripts/local-domain-up.ps1
```

Rebuild the local image after backend or frontend source changes:

```powershell
./deploy/scripts/local-domain-up.ps1 -Build
```

Reset and reseed the isolated local database:

```powershell
./deploy/scripts/reset-local-domain.ps1
```

The script imports the Caddy local root certificate into the current Windows user's trusted root store.

## Optional `.test` Alias

`pesantrenku.localhost` works without changing Windows DNS. To also enable `pesantrenku.test`, run the start script once from an Administrator PowerShell. The hosts entry generated at `build/pesantrenku-hosts.txt` contains:

```text
127.0.0.1 pesantrenku.test www.pesantrenku.test api.pesantrenku.test ws.pesantrenku.test monitor.pesantrenku.test db.pesantrenku.test
```

The `.test` aliases redirect to the canonical `.localhost` URL so session and WebSocket configuration stay consistent.

## Stop

```powershell
./deploy/scripts/local-domain-down.ps1
```

Windows `curl.exe` may require `--ssl-no-revoke` for certificates from a local CA. Chromium-based browsers use the trusted user certificate normally.
