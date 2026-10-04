// Screenshot halaman panjang per posisi scroll: node scripts/ui-preview/shoot-scroll.mjs <outDir> <theme> <path> [width height step]
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const [outDir = 'shots', theme = 'light', path = '/', width = '1440', height = '900', step = '800'] = process.argv.slice(2);
const base = process.env.PREVIEW_URL || 'http://127.0.0.1:4173';
await mkdir(outDir, { recursive: true });

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const context = await browser.newContext({ viewport: { width: Number(width), height: Number(height) }, colorScheme: theme === 'dark' ? 'dark' : 'light' });
await context.addInitScript((value) => window.localStorage.setItem('pk-theme', value), theme);
const page = await context.newPage();
page.on('pageerror', (error) => console.error(`[pageerror] ${error.message}`));
page.on('console', (message) => { if (message.type() === 'error') console.error(`[console] ${message.text()}`); });
await page.goto(base + path, { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);
const total = await page.evaluate(() => document.documentElement.scrollHeight);
let index = 0;
for (let y = 0; y < total; y += Number(step)) {
  await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
  await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  await page.waitForTimeout(900);
  const name = `${width}-${theme}-${String(index).padStart(2, '0')}.png`;
  await page.screenshot({ path: `${outDir}/${name}` });
  console.log('saved', name, `y=${y}/${total}`);
  index += 1;
}
await browser.close();
