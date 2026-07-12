import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://host.docker.internal:8000';
const errors = new Rate('pesantenku_errors');
const tenantLatency = new Trend('tenant_api_duration', true);

export const options = {
  scenarios: {
    tenant_api: { executor: 'constant-vus', exec: 'tenantApi', vus: Number(__ENV.API_VUS || 5), duration: __ENV.DURATION || '20s' },
    payment_reads: { executor: 'constant-arrival-rate', exec: 'paymentReads', rate: Number(__ENV.PAYMENT_RPS || 3), timeUnit: '1s', duration: __ENV.DURATION || '20s', preAllocatedVUs: 3, maxVUs: 10 },
    queued_exports: { executor: 'constant-arrival-rate', exec: 'queuedExports', rate: Number(__ENV.EXPORT_RPS || 1), timeUnit: '5s', duration: __ENV.DURATION || '20s', preAllocatedVUs: 1, maxVUs: 3 },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<750'],
    tenant_api_duration: ['p(95)<600'],
    pesantenku_errors: ['rate<0.01'],
    checks: ['rate>0.99'],
  },
};

export function setup() {
  return {
    owner: login('owner@pesantenku.test'),
    finance: login('keuangan@pesantenku.test'),
  };
}

function login(email) {
  const response = http.post(`${baseUrl}/api/v1/auth/login`, JSON.stringify({ login: email, password: 'password' }), { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } });
  check(response, { [`login ${email}`]: (result) => result.status === 200 && Boolean(result.json('token')) });
  return response.json('token');
}

function headers(token, tenantId = null) {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
    ...(tenantId ? { 'X-Pesantren-Id': String(tenantId) } : {}),
  };
}

export function tenantApi(data) {
  const tenantId = __ITER % 2 === 0 ? 1 : 2;
  const response = http.get(`${baseUrl}/api/v1/students?per_page=20&page=1`, { headers: headers(data.owner, tenantId), tags: { operation: 'tenant_students' } });
  tenantLatency.add(response.timings.duration);
  const ok = check(response, { 'tenant API 200': (result) => result.status === 200, 'tenant data isolated': (result) => (result.json('data') || []).every((row) => Number(row.pesantren_id) === tenantId) });
  errors.add(!ok);
  sleep(0.25);
}

export function paymentReads(data) {
  const response = http.get(`${baseUrl}/api/v1/payments?per_page=20&page=1`, { headers: headers(data.finance, 1), tags: { operation: 'payment_list' } });
  errors.add(!check(response, { 'payment list 200': (result) => result.status === 200 }));
}

export function queuedExports(data) {
  const response = http.post(`${baseUrl}/api/v1/payments/export/queue`, JSON.stringify({ format: 'csv' }), { headers: headers(data.finance, 1), tags: { operation: 'queued_export' } });
  errors.add(!check(response, { 'queued export accepted': (result) => result.status === 202 && Boolean(result.json('job.uuid')) }));
}
