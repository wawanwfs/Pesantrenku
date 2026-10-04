// Ambil screenshot halaman preview: node scripts/ui-preview/shoot.mjs <outDir> [theme] [path...]
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const [outDir = 'shots', theme = 'light', ...paths] = process.argv.slice(2);
const targets = paths.length ? paths : ['/', '/login', '/dashboard/super-admin', '/dashboard/keuangan', '/keuangan/module/santri'];
const base = process.env.PREVIEW_URL || 'http://127.0.0.1:4173';
await mkdir(outDir, { recursive: true });

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
for (const [label, viewport] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
  const context = await browser.newContext({ viewport, colorScheme: theme === 'dark' ? 'dark' : 'light' });
  await context.addInitScript((value) => window.localStorage.setItem('pk-theme', value), theme);
  const page = await context.newPage();
  page.on('pageerror', (error) => console.error(`[pageerror] ${error.message}`));
  page.on('console', (message) => { if (message.type() === 'error') console.error(`[console] ${message.text()}`); });
  for (const path of targets) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    const name = `${label}-${theme}-${path.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}.png`;
    await page.screenshot({ path: `${outDir}/${name}`, fullPage: label === 'desktop' && path === '/' });
    console.log('saved', name);
  }
  await context.close();
}
await browser.close();
