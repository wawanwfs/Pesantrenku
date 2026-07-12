import ws from 'k6/ws';
import { check } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const wsUrl = __ENV.WS_URL || 'ws://host.docker.internal:8080';
const appKey = __ENV.REVERB_APP_KEY;
const connected = new Counter('websocket_connections');
const failures = new Rate('websocket_failures');

export const options = {
  scenarios: {
    concurrent_connections: {
      executor: 'per-vu-iterations',
      vus: Number(__ENV.WS_VUS || 20),
      iterations: 1,
      maxDuration: __ENV.WS_MAX_DURATION || '30s',
    },
  },
  thresholds: {
    websocket_failures: ['rate<0.01'],
    ws_connecting: ['p(95)<500'],
  },
};

export default function () {
  const url = `${wsUrl}/app/${appKey}?protocol=7&client=js&version=8.4.0&flash=false`;
  let established = false;
  const response = ws.connect(url, { headers: { Origin: __ENV.APP_ORIGIN || 'http://localhost:8000' } }, (socket) => {
    socket.on('message', (message) => {
      const payload = String(message);
      if (!established && payload.includes('pusher:connection_established')) {
        established = true;
        connected.add(1);
      }
      if (payload.includes('pusher:ping')) {
        socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
      }
    });
    socket.setTimeout(() => socket.close(), Number(__ENV.WS_HOLD_MS || 10000));
  });
  const upgraded = check(response, { 'websocket upgraded': (result) => result && result.status === 101 });
  failures.add(!upgraded || !established);
}
