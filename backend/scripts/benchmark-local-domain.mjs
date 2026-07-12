import { chromium } from '@playwright/test';
import { writeFile } from 'node:fs/promises';

const baseURL = process.env.PESANTRENKU_BENCHMARK_URL || 'https://pesantrenku.localhost';
const output = process.argv[2] || 'storage/app/benchmark-local-domain.json';
const scenarios = [
  { panel: 'owner', email: 'owner@pesantenku.test', paths: ['/dashboard/owner', '/owner/module/user-dan-role'] },
  { panel: 'guru', email: 'guru@pesantenku.test', paths: ['/dashboard/guru', '/guru/module/nilai'] },
  { panel: 'musyrif', email: 'musyrif@pesantenku.test', paths: ['/dashboard/musyrif', '/musyrif/module/perizinan'] },
];

const browser = await chromium.launch({ headless: true });
const results = [];

for (const scenario of scenarios) {
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[autocomplete="username"]').fill(scenario.email);
  await page.locator('input[type="password"]').fill('password');
  await Promise.all([
    page.waitForURL(new RegExp(`/dashboard/${scenario.panel}`), { waitUntil: 'domcontentloaded', timeout: 45_000 }),
    page.getByRole('button', { name: 'Masuk' }).click(),
  ]);
  await page.waitForLoadState('domcontentloaded');
  await page.locator(`a[href^="/${scenario.panel}/module/"]`).first().waitFor({ state: 'attached', timeout: 45_000 });

  for (const path of scenario.paths) {
    const startedAt = performance.now();
    const response = await page.goto(`${baseURL}${path}`, { waitUntil: 'domcontentloaded', timeout: 60_000 });
    const body = await response.body();
    const queryHeader = response.headers()['x-db-query-count'];
    const slowQueryHeader = response.headers()['x-db-slow-query-count'];
    results.push({
      panel: scenario.panel,
      path,
      status: response.status(),
      duration_ms: Math.round((performance.now() - startedAt) * 100) / 100,
      payload_bytes: body.byteLength,
      db_queries: queryHeader === undefined ? null : Number(queryHeader),
      slow_queries: slowQueryHeader === undefined ? null : Number(slowQueryHeader),
      correlation_id: response.headers()['x-correlation-id'] || null,
      server_timing: response.headers()['server-timing'] || null,
    });
  }

  await context.close();
}

await browser.close();
await writeFile(output, `${JSON.stringify({ baseURL, measured_at: new Date().toISOString(), results }, null, 2)}\n`);
console.log(JSON.stringify(results, null, 2));
