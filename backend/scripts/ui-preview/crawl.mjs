// Smoke test visual: buka setiap dashboard dan setiap modul pada sidebar, laporkan error runtime.
//   node scripts/ui-preview/crawl.mjs [theme]
import { chromium } from '@playwright/test';

const theme = process.argv[2] || 'light';
const base = process.env.PREVIEW_URL || 'http://127.0.0.1:4173';
const panels = ['super-admin', 'owner', 'keuangan', 'akademik', 'tu', 'santri', 'wali-murid', 'guru', 'musyrif', 'klinik'];
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: theme });
await context.addInitScript((value) => window.localStorage.setItem('pk-theme', value), theme);
const page = await context.newPage();
let problems = 0;
let visited = 0;
page.on('pageerror', (error) => { problems += 1; console.error(`  [pageerror] ${error.message}`); });
page.on('console', (message) => {
  if (message.type() === 'error' && !/Failed to load resource/.test(message.text())) { problems += 1; console.error(`  [console] ${message.text()}`); }
});

for (const panel of panels) {
  await page.goto(`${base}/dashboard/${panel}`, { waitUntil: 'networkidle' });
  const links = await page.$$eval('aside nav a', (anchors) => [...new Set(anchors.map((a) => a.getAttribute('href')))]);
  for (const href of [`/dashboard/${panel}`, ...links]) {
    console.log(`${panel} ${href}`);
    await page.goto(base + href, { waitUntil: 'networkidle' });
    await page.waitForTimeout(150);
    const heading = await page.locator('main h1').first().textContent({ timeout: 3000 }).catch(() => null);
    if (!heading) { problems += 1; console.error('  [missing] tidak ada <h1> di main'); }
    visited += 1;
  }
}
console.log(`\nHalaman diperiksa: ${visited}, masalah: ${problems}`);
await browser.close();
process.exit(problems ? 1 : 0);
