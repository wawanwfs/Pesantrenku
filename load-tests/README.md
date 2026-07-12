# Pesantenku Load Tests

```bash
docker run --rm -i grafana/k6:latest run -e BASE_URL=http://host.docker.internal:8000 - < load-tests/operational.js
docker run --rm -i grafana/k6:latest run -e WS_URL=ws://host.docker.internal:8080 -e REVERB_APP_KEY=<key> - < load-tests/websocket.js
```

Default thresholds enforce less than 1 percent request failures, API p95 below 750 ms, tenant isolation checks, accepted queue exports, and WebSocket handshake p95 below 500 ms. The WebSocket scenario holds one connection per VU for 10 seconds by default so it measures concurrent sessions instead of connection churn.
